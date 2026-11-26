<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Double entry for every money movement (PB-18).
 *
 * 1. An "external" wallet account: the outside world (a simulated bank). A top-up is a transfer
 *    from it to a user's wallet and a withdrawal a transfer back, so top-ups and withdrawals are
 *    pairs of ledger entries like every other movement. It has no owner, and there is only one
 *    (the unique `singleton` column is 1 for it and NULL for every other account).
 *
 * 2. The external account's balance goes below zero (money that came in from outside), so the
 *    "never negative" CHECK must exempt it. A CHECK can't read another table, so each ledger
 *    entry carries its account's type in `wallet_type`, and a composite foreign key
 *    (wallet_account_id, wallet_type) → wallet_accounts (id, type) guarantees it is the right
 *    one. The database itself still refuses a negative user or escrow balance.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE wallet_accounts MODIFY type ENUM('user', 'escrow', 'external') NOT NULL");
        DB::statement('ALTER TABLE wallet_accounts DROP CHECK wallet_accounts_owner_check');
        Schema::table('wallet_accounts', function (Blueprint $table) {
            $table->unsignedTinyInteger('singleton')->nullable()->unique()->after('campaign_id');
            $table->unique(['id', 'type']);
        });
        DB::statement(<<<'SQL'
            ALTER TABLE wallet_accounts ADD CONSTRAINT wallet_accounts_owner_check CHECK (
                (type = 'user' AND user_id IS NOT NULL AND campaign_id IS NULL AND singleton IS NULL)
                OR (type = 'escrow' AND campaign_id IS NOT NULL AND user_id IS NULL AND singleton IS NULL)
                OR (type = 'external' AND user_id IS NULL AND campaign_id IS NULL AND singleton = 1)
            )
            SQL);

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->enum('wallet_type', ['user', 'escrow', 'external'])->after('wallet_account_id');
        });
        DB::statement('ALTER TABLE wallet_transactions DROP CHECK wallet_transactions_balance_after_check');
        DB::statement(<<<'SQL'
            ALTER TABLE wallet_transactions
                ADD CONSTRAINT wallet_transactions_account_type_fk FOREIGN KEY (wallet_account_id, wallet_type)
                    REFERENCES wallet_accounts (id, type) ON UPDATE RESTRICT ON DELETE RESTRICT,
                ADD CONSTRAINT wallet_transactions_balance_after_check CHECK (wallet_type = 'external' OR balance_after >= 0)
            SQL);
    }

    public function down(): void
    {
        // The ledger is append-only: entries for the external account would break the old
        // constraints, so rolling back is only possible before any top-up has been made.
        DB::statement('ALTER TABLE wallet_transactions DROP FOREIGN KEY wallet_transactions_account_type_fk');
        DB::statement('ALTER TABLE wallet_transactions DROP CHECK wallet_transactions_balance_after_check');
        // Dropping the column shrinks the composite index back to (wallet_account_id), which the
        // original foreign key on wallet_account_id still needs.
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropColumn('wallet_type');
        });
        DB::statement('ALTER TABLE wallet_transactions ADD CONSTRAINT wallet_transactions_balance_after_check CHECK (balance_after >= 0)');

        DB::table('wallet_accounts')->where('type', 'external')->delete();
        DB::statement('ALTER TABLE wallet_accounts DROP CHECK wallet_accounts_owner_check');
        Schema::table('wallet_accounts', function (Blueprint $table) {
            $table->dropUnique(['id', 'type']);
            $table->dropUnique(['singleton']);
            $table->dropColumn('singleton');
        });
        DB::statement("ALTER TABLE wallet_accounts MODIFY type ENUM('user', 'escrow') NOT NULL");
        DB::statement(<<<'SQL'
            ALTER TABLE wallet_accounts ADD CONSTRAINT wallet_accounts_owner_check CHECK (
                (type = 'user' AND user_id IS NOT NULL AND campaign_id IS NULL)
                OR (type = 'escrow' AND campaign_id IS NOT NULL AND user_id IS NULL)
            )
            SQL);
    }
};
