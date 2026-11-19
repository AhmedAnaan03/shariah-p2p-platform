<?php

namespace Tests\Feature\Database;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * PB-05 acceptance criterion: every migration rolls back cleanly.
 *
 * This class deliberately does not use RefreshDatabase: that trait wraps each test in a
 * transaction, and MySQL commits schema changes (CREATE/DROP) immediately, so they can't run
 * inside one. The test leaves the database fully migrated for the tests that run after it.
 */
class MigrationRollbackTest extends TestCase
{
    public function test_every_migration_rolls_back_one_at_a_time_and_then_migrates_again(): void
    {
        $this->assertSame(0, Artisan::call('migrate:fresh'));
        $migrationCount = DB::table('migrations')->count();
        $this->assertGreaterThanOrEqual(17, $migrationCount); // 3 Laravel defaults + 14 for PB-05

        // One step at a time, so a broken down() method is reported on its own.
        for ($step = 1; $step <= $migrationCount; $step++) {
            $this->assertSame(0, Artisan::call('migrate:rollback', ['--step' => 1]), "Rollback step {$step} failed");
        }

        // Nothing is left behind except Laravel's own (now empty) migrations table.
        $tables = DB::table('information_schema.TABLES')
            ->whereRaw('TABLE_SCHEMA = database()')
            ->pluck('TABLE_NAME')
            ->all();
        $this->assertSame(['migrations'], $tables);
        $this->assertSame(0, DB::table('migrations')->count());
        $this->assertSame(0, DB::table('information_schema.TABLE_CONSTRAINTS')
            ->whereRaw('TABLE_SCHEMA = database()')
            ->whereIn('CONSTRAINT_TYPE', ['FOREIGN KEY', 'CHECK'])
            ->count());

        // And the migrations run again from empty.
        $this->assertSame(0, Artisan::call('migrate'));
        foreach ([
            'users', 'roles', 'borrower_profiles', 'investor_profiles', 'kyc_documents', 'campaigns',
            'pledges', 'wallet_accounts', 'wallet_transactions', 'repayment_schedules', 'repayments',
            'risk_scores', 'notifications', 'audit_logs',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "{$table} missing after re-migrating");
        }
    }

    public function test_rolling_back_the_users_change_restores_the_original_users_table(): void
    {
        $this->assertSame(0, Artisan::call('migrate:fresh'));

        // Roll back everything down to (and including) add_role_and_status_to_users_table,
        // however many later migrations there are.
        $steps = DB::table('migrations')->where('migration', '>=', '2026_11_11_000002_add_role_and_status_to_users_table')->count();
        $this->assertSame(0, Artisan::call('migrate:rollback', ['--step' => $steps]));

        $this->assertFalse(Schema::hasColumn('users', 'role_id'));
        $this->assertFalse(Schema::hasColumn('users', 'status'));
        $this->assertFalse(Schema::hasColumn('users', 'status_reason'));
        $this->assertTrue(Schema::hasTable('roles'));

        $this->assertSame(0, Artisan::call('migrate'));
    }

    public function test_rolling_back_pb14_removes_the_financials_table_and_the_credit_report_link(): void
    {
        $this->assertSame(0, Artisan::call('migrate:fresh'));

        // The two PB-13 to PB-15 migrations are the last two.
        $this->assertSame(0, Artisan::call('migrate:rollback', ['--step' => 2]));

        $this->assertFalse(Schema::hasTable('campaign_financials'));
        $this->assertFalse(Schema::hasColumn('kyc_documents', 'campaign_id'));
        $type = DB::selectOne("select COLUMN_TYPE as t from information_schema.COLUMNS
            where TABLE_SCHEMA = database() and TABLE_NAME = 'kyc_documents' and COLUMN_NAME = 'document_type'")->t;
        $this->assertSame("enum('national_id','business_registration','financial_statement','proof_of_address','other')", $type);

        $this->assertSame(0, Artisan::call('migrate'));
        $this->assertTrue(Schema::hasTable('campaign_financials'));
        $this->assertTrue(Schema::hasColumn('kyc_documents', 'campaign_id'));
    }
}
