<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per instalment of a funded campaign.
     */
    public function up(): void
    {
        Schema::create('repayment_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('instalment_no');
            $table->date('due_date');
            $table->decimal('capital_due', 15, 2);
            // Interest (conventional) or markup share (Murabahah). NULL for Mudarabah: the
            // return depends on profit reported later, so a fixed figure would be a guaranteed
            // return. A CHECK can't see campaigns.contract_type, so the RepaymentSchedule model
            // enforces this rule.
            $table->decimal('return_due', 15, 2)->nullable();
            $table->enum('status', ['due', 'paid', 'late'])->default('due');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'instalment_no']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE repayment_schedules
                ADD CONSTRAINT repayment_schedules_capital_due_check CHECK (capital_due >= 0),
                ADD CONSTRAINT repayment_schedules_return_due_check CHECK (return_due IS NULL OR return_due >= 0)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('repayment_schedules');
    }
};
