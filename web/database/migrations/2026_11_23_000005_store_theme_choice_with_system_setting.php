<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PB-57: the theme switch is light or dark, and "the most recent change wins" between the
 * user's choice and the device's setting.
 *
 *   theme                    the user's last choice (light or dark), or NULL for none yet:
 *                            follow the device (was 'system')
 *   theme_system_at_choice   the device's setting when that choice was made
 *
 * On a later visit the choice is kept while the device still has that setting; if the device
 * has changed since, the device wins. Existing explicit choices have no recorded device setting,
 * so they are kept as they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('theme_system_at_choice', ['light', 'dark'])->nullable()->after('theme');
        });

        DB::statement("ALTER TABLE users MODIFY theme ENUM('light','dark','system') NULL DEFAULT NULL");
        DB::table('users')->where('theme', 'system')->update(['theme' => null]);
        DB::statement("ALTER TABLE users MODIFY theme ENUM('light','dark') NULL DEFAULT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY theme ENUM('light','dark','system') NULL DEFAULT NULL");
        DB::table('users')->whereNull('theme')->update(['theme' => 'system']);
        DB::statement("ALTER TABLE users MODIFY theme ENUM('light','dark','system') NOT NULL DEFAULT 'system'");

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('theme_system_at_choice');
        });
    }
};
