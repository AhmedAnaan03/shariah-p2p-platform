<?php

namespace Tests\Feature\Wallet;

use App\Models\Campaign;
use App\Models\Pledge;
use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletTransaction;
use App\Services\PledgeService;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Concerns\MakesWallets;
use Tests\TestCase;

/**
 * PB-18 to PB-20: the ledger stays correct when money moves at the same moment in several
 * processes. Each worker is a separate PHP process with its own MySQL connection
 * (tests/Concurrency/worker.php).
 *
 * Deterministic, not timing luck: the test itself first locks the row the workers will compete
 * for, starts them, and waits until the MySQL process list shows every worker's connection
 * waiting inside its "… for update" query. Only then does it release the lock, so all of them
 * are genuinely contending for it. Every wait has a fixed timeout, and tearDown always stops the
 * workers and deletes everything the test created.
 *
 * These can't use RefreshDatabase: its rollback-only transaction would hide the test's rows from
 * the workers. The rows are committed and removed again in tearDown (every table, by id).
 */
class ConcurrencyTest extends TestCase
{
    use MakesWallets;

    private const START_TIMEOUT = 10; // seconds for every worker to reach the lock

    private const RUN_TIMEOUT = 20; // seconds for a worker to finish

    /** Tables the tests write, in an order that respects their foreign keys. */
    private const TABLES = [
        'wallet_transactions', 'pledges', 'audit_logs', 'wallet_accounts', 'repayment_schedules', 'campaign_financials',
        'kyc_documents', 'campaigns', 'investor_profiles', 'borrower_profiles', 'users',
    ];

    /** @var array<string, int> */
    private array $highWater = [];

    /** @var list<Process> */
    private array $workers = [];

    private string $startedAt;

    protected function setUp(): void
    {
        parent::setUp();

        // The schema, as RefreshDatabase would set it up, but without its wrapping transaction.
        if (! RefreshDatabaseState::$migrated) {
            $this->artisan('migrate:fresh');
            RefreshDatabaseState::$migrated = true;
        }

        foreach (self::TABLES as $table) {
            $this->highWater[$table] = (int) DB::table($table)->max('id');
        }
        $this->startedAt = now()->subSecond()->toDateTimeString();
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as $worker) {
            $worker->stop(0);
        }
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        foreach (self::TABLES as $table) {
            DB::table($table)->where('id', '>', $this->highWater[$table])->delete();
        }
        DB::table('notifications')->where('created_at', '>=', $this->startedAt)->delete();

        parent::tearDown();
    }

    // ----- The scenarios -----

    public function test_ten_investors_pledging_the_last_amount_at_once_fund_it_exactly_once(): void
    {
        $campaign = $this->liveCampaign(targetMvr: 5_000);
        $investors = collect(range(1, 10))->map(fn () => $this->investor(topUpCents: 5_000_00));

        $results = $this->race(
            table: 'campaigns',
            lock: fn () => Campaign::whereKey($campaign->id)->lockForUpdate()->first(),
            workers: $investors->map(fn (User $investor) => ['pledge', $investor->id, $campaign->id, 5_000_00])->all(),
        );

        $this->assertSame(1, collect($results)->where('ok', true)->count(), 'Exactly one pledge succeeds.');
        $this->assertSame(9, collect($results)->where('ok', false)->where('error', 'PledgeRefused')->count());
        $this->assertSame(1, Pledge::where('campaign_id', $campaign->id)->count());
        $this->assertSame('5000.00', (string) Pledge::where('campaign_id', $campaign->id)->sum('amount'), 'Never more than the target.');
        $this->assertSame(Campaign::STATUS_REPAYING, $campaign->fresh()->status, 'Funded once, then paid out once (PB-34).');
        $this->assertSame(1, $investors->filter(fn (User $investor) => $this->balance($investor) === 0)->count());
        $this->assertSame(9, $investors->filter(fn (User $investor) => $this->balance($investor) === 5_000_00)->count());
        $this->assertLedgerReconciles();
    }

    public function test_one_wallet_pledging_to_two_campaigns_at_once_never_goes_negative(): void
    {
        $investor = $this->investor(topUpCents: 3_000_00);
        $first = $this->liveCampaign(targetMvr: 10_000);
        $second = $this->liveCampaign(targetMvr: 10_000);
        $wallet = WalletAccount::forUser($investor);

        // Different campaigns, so both get past their campaign lock and meet at the wallet.
        $results = $this->race(
            table: 'wallet_accounts',
            lock: fn () => WalletAccount::whereKey($wallet->id)->lockForUpdate()->first(),
            workers: [['pledge', $investor->id, $first->id, 2_000_00], ['pledge', $investor->id, $second->id, 2_000_00]],
        );

        $this->assertSame(1, collect($results)->where('ok', true)->count());
        $this->assertSame('InsufficientFunds', collect($results)->firstWhere('ok', false)['error']);
        $this->assertSame(1_000_00, $this->balance($investor));
        $this->assertSame(1, Pledge::where('investor_id', $investor->id)->count(), 'The refused pledge left nothing behind.');
        $this->assertLedgerReconciles();
    }

    public function test_two_withdrawals_of_the_whole_balance_at_once_take_it_only_once(): void
    {
        $user = $this->investor(topUpCents: 100_00);
        $wallet = WalletAccount::forUser($user);

        $results = $this->race(
            table: 'wallet_accounts',
            lock: fn () => WalletAccount::whereKey($wallet->id)->lockForUpdate()->first(),
            workers: [['withdraw', $user->id, 100_00], ['withdraw', $user->id, 100_00]],
        );

        $this->assertSame(1, collect($results)->where('ok', true)->count());
        $this->assertSame('InsufficientFunds', collect($results)->firstWhere('ok', false)['error']);
        $this->assertSame(0, $this->balance($user));
        $this->assertSame(1, WalletTransaction::where('wallet_account_id', $wallet->id)->where('type', 'withdrawal')->count());
        $this->assertLedgerReconciles();
    }

    public function test_two_deadline_runs_and_a_late_pledge_at_once_refund_every_pledge_exactly_once(): void
    {
        $campaign = $this->liveCampaign(targetMvr: 10_000);
        $pledgers = collect([1_000_00, 2_000_00, 1_500_00])->map(function (int $cents) use ($campaign) {
            $investor = $this->investor(topUpCents: 5_000_00);
            app(PledgeService::class)->pledge($investor, $campaign, $cents);

            return [$investor, $cents];
        });
        $late = $this->investor(topUpCents: 5_000_00);
        $campaign->forceFill(['funding_deadline' => now()->subMinute()])->save(); // the deadline passes

        $results = $this->race(
            table: 'campaigns',
            lock: fn () => Campaign::whereKey($campaign->id)->lockForUpdate()->first(),
            workers: [['settle', $campaign->id], ['settle', $campaign->id], ['pledge', $late->id, $campaign->id, 1_000_00]],
        );

        $this->assertTrue($results[0]['ok'] && $results[1]['ok'], 'Both deadline runs finish without error.');
        $this->assertSame(1, collect([$results[0]['detail'], $results[1]['detail']])->filter()->count(), 'Only one of them had anything to do.');
        $this->assertSame('PledgeRefused', $results[2]['error'], 'The late pledge is refused.');
        $this->assertSame(Campaign::STATUS_FAILED, $campaign->fresh()->status);
        foreach ($pledgers as [$investor, $cents]) {
            $this->assertSame(5_000_00, $this->balance($investor), 'Every pledge came back, once.');
        }
        $this->assertSame(5_000_00, $this->balance($late));
        $this->assertSame(3, WalletTransaction::where('type', 'pledge_refund')->where('direction', 'credit')
            ->whereIn('pledge_id', Pledge::where('campaign_id', $campaign->id)->pluck('id'))->count());
        $this->assertLedgerReconciles();
    }

    // ----- The harness -----

    /**
     * Hold $lock's row lock (a row of $table), start every worker, wait until each one is blocked
     * in a locking statement on $table, then release the lock and collect their results (in
     * worker order).
     *
     * @param  callable(): mixed  $lock
     * @param  list<list<int|string>>  $workers
     * @return list<array{ok: bool, detail?: mixed, error?: string, message?: string}>
     */
    private function race(string $table, callable $lock, array $workers): array
    {
        DB::beginTransaction();
        $lock();

        $connections = [];
        foreach ($workers as $args) {
            $process = new Process([PHP_BINARY, base_path('tests/Concurrency/worker.php'), ...array_map('strval', $args)], base_path(), $this->workerEnvironment(), null, self::RUN_TIMEOUT);
            $process->start();
            $this->workers[] = $process;
        }

        // Every worker reports its connection id, then blocks on the lock we hold.
        $deadline = microtime(true) + self::START_TIMEOUT;
        foreach ($this->workers as $index => $process) {
            while (! preg_match('/^CONNECTION (\d+)$/m', $process->getOutput(), $match)) {
                $this->failIfLate($deadline, "Worker {$index} did not start: ".$process->getErrorOutput());
                usleep(10_000);
            }
            $connections[$index] = (int) $match[1];
        }
        while (count($waiting = $this->waitingOnLock($connections, $table)) < count($connections)) {
            $this->failIfLate($deadline, 'Only '.count($waiting).' of '.count($connections).' workers reached the lock. Running: '.$this->describe($connections));
            usleep(10_000);
        }

        DB::commit(); // release: they all contend now

        return array_map(function (Process $process) {
            $process->wait();
            $this->assertTrue(preg_match('/^RESULT (.+)$/m', $process->getOutput(), $match) === 1, 'Worker gave no result: '.$process->getErrorOutput());

            return json_decode($match[1], true);
        }, $this->workers);
    }

    /**
     * The worker connections currently inside a statement that needs a lock on $table (a locking
     * read or a write), which can only mean they are waiting for ours. Plain reads never wait, so
     * they are not counted.
     *
     * @param  array<int, int>  $connections
     * @return list<int>
     */
    private function waitingOnLock(array $connections, string $table): array
    {
        // SHOW PROCESSLIST lists your own connections without any extra privilege.
        $rows = collect(DB::select('SHOW FULL PROCESSLIST'))->keyBy('Id');

        return array_values(array_filter($connections, function (int $id) use ($rows, $table) {
            $sql = strtolower((string) ($rows[$id]->Info ?? ''));

            return str_contains($sql, "`{$table}`") && (str_contains($sql, 'for update') || str_contains($sql, 'lock in share mode') || str_contains($sql, 'for share') || str_starts_with($sql, 'insert') || str_starts_with($sql, 'update'));
        }));
    }

    /**
     * What each worker connection is running, for a timeout message.
     *
     * @param  array<int, int>  $connections
     */
    private function describe(array $connections): string
    {
        $rows = collect(DB::select('SHOW FULL PROCESSLIST'))->keyBy('Id');

        return collect($connections)->map(fn (int $id, int $index) => isset($rows[$id])
            ? "worker {$index}: [{$rows[$id]->State}] ".substr((string) ($rows[$id]->Info ?? 'idle'), 0, 160)
            : "worker {$index}: exited: ".trim($this->workers[$index]->getOutput().' '.$this->workers[$index]->getErrorOutput()))->implode(' | ');
    }

    private function failIfLate(float $deadline, string $message): void
    {
        if (microtime(true) > $deadline) {
            $this->fail($message);
        }
    }

    /**
     * The workers use the same database, mailer and settings as this test.
     *
     * @return array<string, string>
     */
    private function workerEnvironment(): array
    {
        $db = config('database.connections.mysql');

        return [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => (string) $db['host'],
            'DB_PORT' => (string) $db['port'],
            'DB_DATABASE' => (string) $db['database'],
            'DB_USERNAME' => (string) $db['username'],
            'DB_PASSWORD' => (string) $db['password'],
            'MAIL_MAILER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
        ];
    }
}
