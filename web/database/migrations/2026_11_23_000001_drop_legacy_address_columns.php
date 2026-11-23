<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PB-59: every environment has moved to structured addresses (PB-53), so the one-off legacy
 * columns go: the original single-line text and the "check your address" flag.
 *
 * Rolling back puts both columns back (empty, flag off), which is what the PB-53 migration's
 * own rollback expects to find.
 */
return new class extends Migration
{
    private const TABLES = ['borrower_profiles', 'investor_profiles'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn(['address_legacy', 'address_needs_review']);
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->text('address_legacy')->nullable()->after('postcode');
                $table->boolean('address_needs_review')->default(false)->after('address_legacy');
            });
        }
    }
};
