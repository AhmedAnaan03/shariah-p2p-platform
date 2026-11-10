<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The three platform roles. One role per user (users.role_id).
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 20)->unique();
            $table->string('label', 50);
            $table->timestamps();
        });

        // Roles are reference data the app can't run without, so they are inserted here rather
        // than in a seeder: every database built by `migrate` (including the test database)
        // has them.
        $now = now();

        DB::table('roles')->insert([
            ['name' => 'borrower', 'label' => 'Borrower / SME', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'investor', 'label' => 'Investor', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'admin', 'label' => 'Administrator', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
