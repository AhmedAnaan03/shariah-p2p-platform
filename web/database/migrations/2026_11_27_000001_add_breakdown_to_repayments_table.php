<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PB-35: a repayment pays one whole instalment, and records how much of it was capital and how
 * much interest or markup.
 *
 * - capital_amount + return_amount = amount (CHECK). Usually they equal the row's capital_due and
 *   return_due; a conventional early payoff charges no interest for periods not yet reached, so
 *   its return_amount is 0 for those rows (kind = early_payoff).
 * - One repayment per schedule row (UNIQUE): instalments are paid whole, never in parts, and the
 *   database itself refuses a second payment of the same row (the double-click guard behind the
 *   campaign lock in RepaymentService).
 *
 * No repayments were written before PB-35, so the new NOT NULL columns need no backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repayments', function (Blueprint $table) {
            $table->decimal('capital_amount', 15, 2)->after('amount');
            $table->decimal('return_amount', 15, 2)->after('capital_amount');
            $table->enum('kind', ['instalment', 'early_payoff'])->default('instalment')->after('return_amount');
            $table->unique('repayment_schedule_id', 'repayments_one_per_row');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE repayments
                ADD CONSTRAINT repayments_breakdown_check CHECK (
                    capital_amount >= 0 AND return_amount >= 0 AND amount = capital_amount + return_amount
                )
            SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE repayments DROP CHECK repayments_breakdown_check');
        // MySQL may have dropped the foreign key's own index when the unique index (on the same
        // column) arrived. The key needs an index, so put a plain one back first if it is gone.
        $needsIndex = ! Schema::hasIndex('repayments', 'repayments_repayment_schedule_id_foreign');
        Schema::table('repayments', function (Blueprint $table) use ($needsIndex) {
            if ($needsIndex) {
                $table->index('repayment_schedule_id', 'repayments_repayment_schedule_id_foreign');
            }
            $table->dropUnique('repayments_one_per_row');
            $table->dropColumn(['capital_amount', 'return_amount', 'kind']);
        });
    }
};
