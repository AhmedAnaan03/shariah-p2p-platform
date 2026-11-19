<?php

use App\Support\LegacyAddress;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PB-53: the single-line address on both profile tables becomes house/building name, road
 * (magu), island (and through it the atoll) and postcode.
 *
 * Nothing is lost on the way:
 *   - the old text is copied to address_legacy before the old column is dropped;
 *   - LegacyAddress makes a best guess at the new fields from it;
 *   - address_needs_review is set, so the address doesn't count as complete until the user
 *     has checked and saved it in the onboarding wizard.
 *
 * The new columns are nullable because migrated and half-finished profiles don't have them
 * yet; the address form requires every one of them.
 *
 * Rolling back puts a single-line address back: the original text while the user hasn't
 * reviewed it yet, otherwise the structured fields joined with commas.
 */
return new class extends Migration
{
    private const TABLES = ['borrower_profiles', 'investor_profiles'];

    public function up(): void
    {
        $islands = DB::table('islands')
            ->join('atolls', 'atolls.id', '=', 'islands.atoll_id')
            ->get(['islands.id', 'islands.name', 'atolls.name as atoll_name', 'atolls.code as atoll_code'])
            ->map(fn ($row) => (array) $row)
            ->all();

        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('address_house', 100)->nullable()->after('national_id_no');
                $table->string('address_road', 100)->nullable()->after('address_house');
                $table->foreignId('island_id')->nullable()->after('address_road')->constrained()->restrictOnDelete();
                $table->char('postcode', 5)->nullable()->after('island_id');
                $table->text('address_legacy')->nullable()->after('postcode');
                $table->boolean('address_needs_review')->default(false)->after('address_legacy');
            });

            DB::table($table)->orderBy('id')->select(['id', 'address'])->chunkById(200, function ($rows) use ($table, $islands) {
                foreach ($rows as $row) {
                    $parsed = LegacyAddress::parse($row->address, $islands);

                    DB::table($table)->where('id', $row->id)->update([
                        'address_house' => $parsed['house'],
                        'address_road' => $parsed['road'],
                        'island_id' => $parsed['island_id'],
                        'postcode' => $parsed['postcode'],
                        'address_legacy' => $row->address,
                        'address_needs_review' => true,
                    ]);
                }
            });

            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('address');
            });

            // Defence in depth: the form already checks the postcode is 5 digits.
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_postcode_check CHECK (postcode IS NULL OR postcode REGEXP '^[0-9]{5}$')");
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            DB::statement("ALTER TABLE {$table} DROP CHECK {$table}_postcode_check");

            Schema::table($table, function (Blueprint $table) {
                $table->text('address')->nullable()->after('national_id_no');
            });

            $rows = DB::table($table)
                ->leftJoin('islands', 'islands.id', '=', "{$table}.island_id")
                ->leftJoin('atolls', 'atolls.id', '=', 'islands.atoll_id')
                ->get(["{$table}.id", 'address_house', 'address_road', 'islands.name as island', 'atolls.name as atoll', 'postcode', 'address_legacy', 'address_needs_review']);

            foreach ($rows as $row) {
                $structured = implode(', ', array_filter([$row->address_house, $row->address_road, $row->island, $row->atoll, $row->postcode]));

                // Not yet reviewed: the fields are only the migration's guess, so the user's
                // original text goes back. Reviewed (or new): what the user entered.
                $address = $row->address_needs_review && $row->address_legacy !== null ? $row->address_legacy : $structured;

                DB::table($table)->where('id', $row->id)->update(['address' => $address !== '' ? $address : (string) $row->address_legacy]);
            }

            DB::statement("ALTER TABLE {$table} MODIFY address TEXT NOT NULL");

            Schema::table($table, function (Blueprint $table) {
                $table->dropConstrainedForeignId('island_id');
                $table->dropColumn(['address_house', 'address_road', 'postcode', 'address_legacy', 'address_needs_review']);
            });
        }
    }
};
