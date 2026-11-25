<?php

/*
| A worker for tests/Feature/Wallet/ConcurrencyTest.php: one separate PHP process (its own MySQL
| connection) doing one money movement, so several can genuinely run at the same moment.
|
|   php tests/Concurrency/worker.php pledge   <investor id> <campaign id> <cents>
|   php tests/Concurrency/worker.php withdraw <user id> <cents>
|   php tests/Concurrency/worker.php settle   <campaign id>
|
| It prints "CONNECTION <id>" first (the test watches that connection in the MySQL process list to
| know when it is waiting on the lock), then "RESULT <json>" with ok / error.
*/

use App\Models\Campaign;
use App\Models\User;
use App\Services\PledgeService;
use App\Services\SettlementService;
use App\Services\WalletLedger;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$args = array_slice($argv, 1);
$action = array_shift($args);

fwrite(STDOUT, 'CONNECTION '.DB::selectOne('select connection_id() as id')->id.PHP_EOL);
fflush(STDOUT);

try {
    $detail = match ($action) {
        'pledge' => app(PledgeService::class)->pledge(User::findOrFail($args[0]), Campaign::findOrFail($args[1]), (int) $args[2])->id,
        'withdraw' => app(WalletLedger::class)->withdraw(User::findOrFail($args[0]), (int) $args[1]),
        'settle' => app(SettlementService::class)->settle((int) $args[0]),
    };
    $result = ['ok' => true, 'detail' => $detail];
} catch (Throwable $e) {
    $result = ['ok' => false, 'error' => class_basename($e), 'message' => $e->getMessage()];
}

fwrite(STDOUT, 'RESULT '.json_encode($result).PHP_EOL);
