<?php

namespace App\Console\Commands;

use App\Services\RepaymentService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * PB-35: mark unpaid instalments late once their due date (Maldives date) has passed.
 * Conventional and Murabahah only; a Mudarabah's planned capital return is never late. No fee or
 * penalty is added: late is a status, not a charge. Scheduled every minute (routes/console.php);
 * safe to run by hand or twice at once.
 *
 *   php artisan repayments:mark-late
 */
#[Signature('repayments:mark-late')]
#[Description('Mark unpaid instalments past their due date as late (no fees or penalties)')]
class MarkLateInstalments extends Command
{
    public function handle(): int
    {
        $changed = RepaymentService::refreshLateStatus();
        $this->info("Instalment statuses updated: {$changed}.");

        return self::SUCCESS;
    }
}
