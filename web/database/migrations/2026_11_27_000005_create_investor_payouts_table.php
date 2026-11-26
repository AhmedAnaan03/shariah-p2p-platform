<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PB-36, PB-29: each investor's share of one repayment or one approved Mudarabah report, with
 * every part recorded separately. Append-only, like the ledger.
 *
 * - capital_amount: capital paid back to the investor.
 * - loss_restored_amount (Mudarabah): profit used to restore a genuine loss the investor bore in
 *   an earlier period, paid before any profit is shared (the classical rule, see decisions.md).
 * - return_amount: interest (conventional), markup (Murabahah) or profit share (Mudarabah).
 * - loss_amount (Mudarabah): a genuine business loss allocated to the investor. No money moves for
 *   it: it reduces their capital.
 *
 * The money parts are paid as one ledger transfer (transfer_ref) from the campaign's escrow to the
 * investor's wallet; a row that only allocates a loss has no transfer. Every row comes from
 * exactly one repayment or one report (CHECK), and an investor has at most one row per source.
 *
 * Each investor's capital account follows from these rows (MudarabahCapital):
 *   outstanding capital = settled pledges − Σ capital − Σ loss
 *   unrestored loss     = Σ loss − Σ loss_restored   (never below zero: LedgerReconciler)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investor_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->restrictOnDelete();
            $table->foreignId('investor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('pledge_id')->constrained()->restrictOnDelete();
            $table->foreignId('repayment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('business_report_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('capital_amount', 15, 2)->default(0);
            $table->decimal('loss_restored_amount', 15, 2)->default(0);
            $table->decimal('return_amount', 15, 2)->default(0);
            $table->decimal('loss_amount', 15, 2)->default(0);
            $table->uuid('transfer_ref')->nullable()->index();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['repayment_id', 'investor_id']);
            $table->unique(['business_report_id', 'investor_id']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE investor_payouts
                ADD CONSTRAINT investor_payouts_source_check CHECK (
                    (repayment_id IS NOT NULL AND business_report_id IS NULL AND loss_restored_amount = 0 AND loss_amount = 0)
                    OR (repayment_id IS NULL AND business_report_id IS NOT NULL)
                ),
                ADD CONSTRAINT investor_payouts_amounts_check CHECK (
                    capital_amount >= 0 AND loss_restored_amount >= 0 AND return_amount >= 0 AND loss_amount >= 0
                    AND capital_amount + loss_restored_amount + return_amount + loss_amount > 0
                ),
                ADD CONSTRAINT investor_payouts_transfer_check CHECK (
                    (transfer_ref IS NULL) = (capital_amount + loss_restored_amount + return_amount = 0)
                )
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('investor_payouts');
    }
};
