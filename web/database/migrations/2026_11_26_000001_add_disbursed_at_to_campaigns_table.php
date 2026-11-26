<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PB-34: when the escrow was paid out to the borrower. The repayment schedule counts from this
 * moment (the first due date is one month later), and it is set in the same transaction as the
 * disbursement transfer, so it is set exactly when the money has moved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->timestamp('disbursed_at')->nullable()->after('funded_at')
                ->comment('When the escrow was paid out to the borrower (PB-34); the schedule starts one month later');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn('disbursed_at');
        });
    }
};
