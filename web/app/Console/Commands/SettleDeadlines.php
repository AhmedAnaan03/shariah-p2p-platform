<?php

namespace App\Console\Commands;

use App\Services\SettlementService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * PB-20: settle every live campaign whose funding deadline has passed: funded if the target was
 * reached, otherwise not funded with every pledge refunded. PB-34: then pay out every funded
 * campaign still waiting for its payout. Scheduled every minute
 * (routes/console.php); safe to run by hand or twice at once.
 *
 *   php artisan campaigns:settle-deadlines
 */
#[Signature('campaigns:settle-deadlines')]
#[Description('Settle campaigns past their funding deadline (funded, or refund every pledge) and pay out funded campaigns')]
class SettleDeadlines extends Command
{
    public function handle(SettlementService $settlement): int
    {
        $summary = $settlement->settleDue();
        $this->info("Funded: {$summary['funded']}. Not funded: {$summary['failed']}. Pledges refunded: {$summary['refunds']}. Paid out: {$summary['disbursed']}.");

        return self::SUCCESS;
    }
}
