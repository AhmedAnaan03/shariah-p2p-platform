<?php

namespace App\Services;

use App\Exceptions\InsufficientFunds;
use App\Models\AuditLog;
use App\Models\Pledge;
use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletTransaction;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The simulated wallet's ledger (PB-18). Every money movement goes through transfer(): one
 * debit and one credit of the same amount, sharing a transfer_ref, written in one database
 * transaction. Nothing else writes wallet_transactions.
 *
 * - Balances aren't stored: an account's balance is its latest entry's balance_after, which the
 *   reconciliation check (LedgerReconciler) proves equals the sum of all its entries.
 * - Both accounts are locked (SELECT … FOR UPDATE) before their balances are read, and the
 *   balances are read with a locking read, which always sees the latest committed entry. So two
 *   transfers touching the same wallet run one after the other and can't both spend the same
 *   money. Locks are always taken in id order, so two transfers can't deadlock each other.
 * - A user or escrow wallet can never go below zero: the transfer is refused before anything is
 *   written (and the database CHECK on balance_after refuses it again). Only the external
 *   account, the outside world, goes negative.
 *
 * Called inside a wider transaction (a pledge), it joins it: the locks are then held until the
 * whole pledge commits.
 */
class WalletLedger
{
    /** The most a single top-up can be (simulated money, but a typo guard). */
    public const MAX_TOP_UP_CENTS = 1_000_000_00;

    /**
     * Run a money transaction at READ COMMITTED isolation (when it is the outermost transaction).
     *
     * - Every read sees the latest committed data. Under MySQL's default REPEATABLE READ, a read
     *   made after waiting for a lock could still see the data as it was before the wait, so the
     *   same money could be spent twice (the concurrency tests caught exactly that).
     * - Locking reads lock only the rows they touch, not the gaps between index entries, so a
     *   pledge to one campaign never waits for a pledge to another.
     *
     * The order of money movements is still decided by explicit row locks (the campaign, then
     * the wallets). Nested inside another transaction (a test, a seeder) it simply joins it: the
     * isolation level can't change mid-transaction.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function run(callable $callback): mixed
    {
        if (DB::transactionLevel() === 0) {
            DB::statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED'); // the next transaction only
        }

        return DB::transaction($callback);
    }

    /**
     * Move $cents from one account to another. Returns the transfer_ref shared by both entries.
     *
     * @throws InsufficientFunds when $from is a user or escrow wallet without enough money
     */
    public function transfer(WalletAccount $from, WalletAccount $to, int $cents, string $type, ?Pledge $pledge = null, ?string $description = null): string
    {
        if ($cents <= 0) {
            throw new InvalidArgumentException('A transfer must be for more than zero.');
        }
        if ($from->is($to)) {
            throw new InvalidArgumentException('A transfer needs two different accounts.');
        }

        return self::run(function () use ($from, $to, $cents, $type, $pledge, $description) {
            // Lock both rows, lowest id first, before reading either balance.
            WalletAccount::whereKey([$from->id, $to->id])->orderBy('id')->lockForUpdate()->get();

            $fromBalance = $this->lockedBalance($from);
            if (! $from->isExternal() && $fromBalance < $cents) {
                throw new InsufficientFunds('Not enough money in the wallet for '.Money::format($cents).'.');
            }
            $toBalance = $this->lockedBalance($to);

            $ref = (string) Str::uuid();
            $this->entry($from, $ref, $type, WalletTransaction::DEBIT, $cents, $fromBalance - $cents, $pledge, $description);
            $this->entry($to, $ref, $type, WalletTransaction::CREDIT, $cents, $toBalance + $cents, $pledge, $description);

            return $ref;
        });
    }

    /**
     * Simulated top-up: money comes in from the outside world.
     */
    public function topUp(User $user, int $cents): string
    {
        if ($cents > self::MAX_TOP_UP_CENTS) {
            throw new InvalidArgumentException('A top-up can be at most '.Money::format(self::MAX_TOP_UP_CENTS).'.');
        }

        return self::run(function () use ($user, $cents) {
            $ref = $this->transfer(WalletAccount::external(), WalletAccount::forUser($user), $cents, WalletTransaction::TOP_UP, description: 'Simulated top-up');
            AuditLog::record('wallet.top_up', $user, ['amount' => Money::fromCents($cents), 'transfer_ref' => $ref], $user);

            return $ref;
        });
    }

    /**
     * Simulated withdrawal: money goes back to the outside world.
     *
     * @throws InsufficientFunds
     */
    public function withdraw(User $user, int $cents): string
    {
        return self::run(function () use ($user, $cents) {
            $ref = $this->transfer(WalletAccount::forUser($user), WalletAccount::external(), $cents, WalletTransaction::WITHDRAWAL, description: 'Simulated withdrawal');
            AuditLog::record('wallet.withdrawal', $user, ['amount' => Money::fromCents($cents), 'transfer_ref' => $ref], $user);

            return $ref;
        });
    }

    /**
     * An account's balance in cents, for showing it: its latest entry's balance_after, or 0 with
     * no entries. Transfers use lockedBalance() instead.
     */
    public function balance(WalletAccount $account): int
    {
        $latest = WalletTransaction::where('wallet_account_id', $account->id)->orderByDesc('id')->value('balance_after');

        return Money::fromDatabase($latest);
    }

    /**
     * The balance for deciding a transfer: a locking read, so it sees the latest committed entry.
     *
     * Why not balance()? MySQL's default isolation (REPEATABLE READ) fixes a transaction's view
     * of the data at its first plain read. If that happened before the lock was taken, a plain
     * read would still see the balance from before another transfer that committed while we
     * waited, and the same money could be spent twice. The concurrency tests caught exactly that.
     * A locking read always reads the latest committed row.
     */
    private function lockedBalance(WalletAccount $account): int
    {
        return Money::fromDatabase(
            WalletTransaction::where('wallet_account_id', $account->id)->orderByDesc('id')->sharedLock()->value('balance_after')
        );
    }

    /**
     * A user's balance without creating a wallet they don't have yet.
     */
    public function balanceFor(User $user): int
    {
        $account = WalletAccount::where('user_id', $user->id)->first();

        return $account ? $this->balance($account) : 0;
    }

    private function entry(WalletAccount $account, string $ref, string $type, string $direction, int $cents, int $balanceAfter, ?Pledge $pledge, ?string $description): void
    {
        $entry = new WalletTransaction([
            'wallet_type' => $account->type,
            'transfer_ref' => $ref,
            'type' => $type,
            'direction' => $direction,
            'amount' => Money::fromCents($cents),
            'balance_after' => Money::fromCents($balanceAfter),
            'pledge_id' => $pledge?->id,
            'description' => $description,
        ]);
        $entry->walletAccount()->associate($account);
        $entry->save();
    }
}
