<?php

namespace App\Console\Commands;

use App\Support\LedgerReconciler;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * PB-18: prove the simulated ledger is consistent (see LedgerReconciler for the six checks).
 * Exits with 1 and lists the problems if it doesn't reconcile.
 *
 *   php artisan wallet:reconcile
 */
#[Signature('wallet:reconcile')]
#[Description('Check that every wallet balance reconciles with the ledger')]
class ReconcileLedger extends Command
{
    public function handle(): int
    {
        $problems = LedgerReconciler::problems();
        if ($problems === []) {
            $this->info('The ledger reconciles: double entry, balance chains, no negative wallets, money conserved, escrow matches pledges, repayments and Mudarabah reports match their payouts, no capital account below zero.');

            return self::SUCCESS;
        }

        foreach ($problems as $problem) {
            $this->error($problem);
        }

        return self::FAILURE;
    }
}
