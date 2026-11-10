<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A payment the borrower made towards one instalment (several allowed: part payments).
     */
    public function up(): void
    {
        Schema::create('repayments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repayment_schedule_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->timestamp('paid_at');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE repayments ADD CONSTRAINT repayments_amount_check CHECK (amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('repayments');
    }
};
