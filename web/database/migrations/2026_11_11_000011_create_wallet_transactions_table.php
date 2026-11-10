<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The ledger. Append-only: rows are inserted, never updated, so there is no updated_at.
     */
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_account_id')->constrained()->restrictOnDelete();
            // Both legs of one money movement (e.g. investor debit + escrow credit) share this.
            $table->uuid('transfer_ref')->index();
            $table->enum('type', [
                'top_up', 'withdrawal', 'pledge', 'pledge_refund',
                'disbursement', 'repayment', 'return_payout',
            ]);
            $table->enum('direction', ['credit', 'debit']);
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->foreignId('pledge_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('repayment_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('description')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE wallet_transactions
                ADD CONSTRAINT wallet_transactions_amount_check CHECK (amount > 0),
                ADD CONSTRAINT wallet_transactions_balance_after_check CHECK (balance_after >= 0),
                ADD CONSTRAINT wallet_transactions_return_payout_check CHECK (
                    type <> 'return_payout'
                    OR (pledge_id IS NOT NULL AND repayment_id IS NOT NULL)
                )
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
