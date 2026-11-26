<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PB-35, PB-29: the ledger entries for repayments and Mudarabah reports.
 *
 * - Two new types for a Mudarabah, whose money is never a "repayment" (its schedule is not a
 *   debt, PB-34): `mudarabah_payment` (the business pays the investors' profit share, restored
 *   losses and returned capital into the campaign's escrow) and `mudarabah_payout` (the escrow
 *   pays each investor their part).
 * - business_report_id links both to the approved report they come from.
 * - CHECKs so every such entry traces to its source: a repayment to its repayment, a Mudarabah
 *   payment to its report, a Mudarabah payout to the investor's pledge and the report (as
 *   return_payout already must to its pledge and repayment).
 */
return new class extends Migration
{
    private const TYPES_BEFORE = "'top_up','withdrawal','pledge','pledge_refund','disbursement','repayment','return_payout'";

    public function up(): void
    {
        DB::statement('ALTER TABLE wallet_transactions MODIFY type ENUM('.self::TYPES_BEFORE.",'mudarabah_payment','mudarabah_payout') NOT NULL");

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->foreignId('business_report_id')->nullable()->after('repayment_id')->constrained()->restrictOnDelete();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE wallet_transactions
                ADD CONSTRAINT wallet_transactions_repayment_check CHECK (type <> 'repayment' OR repayment_id IS NOT NULL),
                ADD CONSTRAINT wallet_transactions_mudarabah_payment_check CHECK (type <> 'mudarabah_payment' OR business_report_id IS NOT NULL),
                ADD CONSTRAINT wallet_transactions_mudarabah_payout_check CHECK (
                    type <> 'mudarabah_payout' OR (pledge_id IS NOT NULL AND business_report_id IS NOT NULL)
                )
            SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE wallet_transactions
                DROP CHECK wallet_transactions_repayment_check,
                DROP CHECK wallet_transactions_mudarabah_payment_check,
                DROP CHECK wallet_transactions_mudarabah_payout_check
            SQL);
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_report_id');
        });
        DB::statement('ALTER TABLE wallet_transactions MODIFY type ENUM('.self::TYPES_BEFORE.') NOT NULL');
    }
};
