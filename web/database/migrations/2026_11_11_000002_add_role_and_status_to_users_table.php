<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Give every user exactly one role and an account status.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->after('id')->constrained('roles')->restrictOnDelete();
            $table->enum('status', ['pending_verification', 'verified', 'rejected', 'suspended'])
                ->default('pending_verification')
                ->after('password');
            $table->string('status_reason')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drops the foreign key first, then the column.
            $table->dropConstrainedForeignId('role_id');
            $table->dropColumn(['status', 'status_reason']);
        });
    }
};
