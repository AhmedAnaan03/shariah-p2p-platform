<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PB-53: the reference list behind the address dropdowns. Atolls (with Malé City) and their
 * inhabited islands, read from database/data/maldives_islands.php (the source is cited there).
 *
 * The rows are inserted here rather than by a seeder because profiles point at islands with a
 * foreign key: every environment (development, tests, CI, the demo) must have the same list
 * before any address can be saved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atolls', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique(); // the letters used in addresses, e.g. "HA"
            $table->string('name', 50)->unique();
            $table->unsignedTinyInteger('sort_order'); // north to south, as listed
            $table->timestamps();
        });

        Schema::create('islands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('atoll_id')->constrained()->restrictOnDelete();
            $table->string('name', 50);
            $table->timestamps();

            // Names repeat across atolls (there is a Meedhoo in Raa, Dhaalu and Addu), never
            // within one.
            $table->unique(['atoll_id', 'name']);
        });

        $now = now();
        foreach (require database_path('data/maldives_islands.php') as $order => [$code, $name, $islands]) {
            $atollId = DB::table('atolls')->insertGetId([
                'code' => $code, 'name' => $name, 'sort_order' => $order + 1, 'created_at' => $now, 'updated_at' => $now,
            ]);

            DB::table('islands')->insert(array_map(
                fn (string $island) => ['atoll_id' => $atollId, 'name' => $island, 'created_at' => $now, 'updated_at' => $now],
                $islands,
            ));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('islands');
        Schema::dropIfExists('atolls');
    }
};
