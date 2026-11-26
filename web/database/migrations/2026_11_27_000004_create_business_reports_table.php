<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PB-29: a Mudarabah business's report for one planned capital return period: what it earned
 * and spent, the profit or loss that follows, and the capital it is returning. An administrator
 * approves it (and, for a loss, decides whether it was a genuine business loss or caused by
 * negligence or misconduct) or rejects it with a reason.
 *
 * - profit is signed: revenue − costs (CHECK), negative for a loss.
 * - The outcome columns (loss_restored … business_liable_loss) are worked out when the report is
 *   approved (MudarabahSettlement) and are NULL until then. Together they account for the whole
 *   profit, or the whole loss:
 *     profit ≥ 0: loss_restored + investors_profit + business_profit = profit
 *     loss, genuine: loss_borne (by investors) ≤ the loss; nothing is liable
 *     loss, negligence or misconduct: loss_borne = 0; business_liable_loss = the loss
 * - open_slot holds the schedule row's id while the report is submitted or approved, NULL once
 *   rejected; the unique index on it allows one open report per period, while rejected ones stay
 *   on record and the business can submit a new one.
 *
 * No column ever holds a fixed or expected return: only figures the business reported and what
 * was done with them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->restrictOnDelete();
            $table->foreignId('repayment_schedule_id')->constrained()->restrictOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->decimal('revenue', 15, 2);
            $table->decimal('costs', 15, 2);
            $table->decimal('profit', 15, 2)->comment('Revenue minus costs; negative for a loss');
            $table->decimal('capital_returned', 15, 2)->comment('Capital the business returns to the investors this period');
            $table->foreignId('kyc_document_id')->nullable()->constrained()->restrictOnDelete();
            $table->enum('status', ['submitted', 'approved', 'rejected'])->default('submitted');
            $table->enum('loss_cause', ['business', 'negligence'])->nullable()
                ->comment('For an approved loss: a genuine business loss (borne by investors) or negligence or misconduct (the business is liable)');
            $table->string('decision_reason', 1000)->nullable();
            $table->decimal('loss_restored', 15, 2)->nullable()->comment('Profit used to restore earlier genuine losses investors bore');
            $table->decimal('investors_profit', 15, 2)->nullable()->comment('Investors\' share of the profit left after restoring losses');
            $table->decimal('business_profit', 15, 2)->nullable()->comment('The business\'s share of that profit');
            $table->decimal('loss_borne', 15, 2)->nullable()->comment('Genuine loss borne by investors, reducing their capital');
            $table->decimal('business_liable_loss', 15, 2)->nullable()->comment('Loss caused by negligence or misconduct: the business restores the capital');
            $table->timestamp('submitted_at');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE business_reports
            ADD COLUMN open_slot BIGINT UNSIGNED
                GENERATED ALWAYS AS (IF(status = 'rejected', NULL, repayment_schedule_id)) STORED
                AFTER status
            SQL);
        Schema::table('business_reports', function (Blueprint $table) {
            $table->unique('open_slot', 'business_reports_one_open_per_period');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE business_reports
                ADD CONSTRAINT business_reports_amounts_check CHECK (revenue >= 0 AND costs >= 0 AND capital_returned >= 0),
                ADD CONSTRAINT business_reports_profit_check CHECK (profit = revenue - costs),
                ADD CONSTRAINT business_reports_review_check CHECK (
                    (status = 'submitted' AND reviewed_by IS NULL AND reviewed_at IS NULL)
                    OR (status <> 'submitted' AND reviewed_by IS NOT NULL AND reviewed_at IS NOT NULL)
                ),
                ADD CONSTRAINT business_reports_reason_check CHECK (
                    (status <> 'rejected' OR decision_reason IS NOT NULL)
                    AND (loss_cause IS NULL OR loss_cause <> 'negligence' OR decision_reason IS NOT NULL)
                ),
                ADD CONSTRAINT business_reports_loss_cause_check CHECK (
                    (status = 'approved' AND profit < 0 AND loss_cause IS NOT NULL)
                    OR (NOT (status = 'approved' AND profit < 0) AND loss_cause IS NULL)
                ),
                ADD CONSTRAINT business_reports_outcome_check CHECK (
                    (status <> 'approved'
                        AND loss_restored IS NULL AND investors_profit IS NULL AND business_profit IS NULL
                        AND loss_borne IS NULL AND business_liable_loss IS NULL)
                    OR (status = 'approved' AND profit >= 0
                        AND loss_restored >= 0 AND investors_profit >= 0 AND business_profit >= 0
                        AND loss_restored + investors_profit + business_profit = profit
                        AND loss_borne = 0 AND business_liable_loss = 0)
                    OR (status = 'approved' AND profit < 0 AND loss_cause = 'business'
                        AND loss_restored = 0 AND investors_profit = 0 AND business_profit = 0
                        AND loss_borne >= 0 AND loss_borne <= -profit AND business_liable_loss = 0)
                    OR (status = 'approved' AND profit < 0 AND loss_cause = 'negligence'
                        AND loss_restored = 0 AND investors_profit = 0 AND business_profit = 0
                        AND loss_borne = 0 AND business_liable_loss = -profit)
                )
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('business_reports');
    }
};
