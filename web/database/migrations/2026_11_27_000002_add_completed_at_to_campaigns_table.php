<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PB-36, PB-29: when a campaign finished. Set with status `completed` when its last instalment is
 * paid (conventional, Murabahah) or its capital is settled (Mudarabah).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('disbursed_at');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });
    }
};
