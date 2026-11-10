<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Compares the migrated MySQL schema with docs/erd.md: every column, whether it may be
 * NULL, every foreign key and every CHECK constraint.
 */
class SchemaMatchesErdTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Each table's columns from docs/erd.md. true = nullable, false = NOT NULL.
     * Laravel's timestamps() creates nullable created_at/updated_at; the append-only tables
     * have a NOT NULL created_at and no updated_at.
     */
    private const ERD = [
        'roles' => [
            'id' => false, 'name' => false, 'label' => false,
            'created_at' => true, 'updated_at' => true,
        ],
        'users' => [
            'id' => false, 'role_id' => false, 'name' => false, 'email' => false,
            'email_verified_at' => true, 'password' => false, 'status' => false,
            'status_reason' => true, 'remember_token' => true,
            'created_at' => true, 'updated_at' => true,
        ],
        'borrower_profiles' => [
            'id' => false, 'user_id' => false, 'borrower_type' => false, 'phone' => false,
            'national_id_no' => false, 'address' => false, 'business_name' => true,
            'business_registration_no' => true, 'sector' => true, 'years_operating' => true,
            'declared_annual_income' => false, 'created_at' => true, 'updated_at' => true,
        ],
        'investor_profiles' => [
            'id' => false, 'user_id' => false, 'phone' => false, 'national_id_no' => false,
            'address' => false, 'risk_acknowledged_at' => true, 'shariah_only' => false,
            'created_at' => true, 'updated_at' => true,
        ],
        'kyc_documents' => [
            'id' => false, 'user_id' => false, 'document_type' => false, 'original_name' => false,
            'storage_path' => false, 'mime_type' => false, 'size_bytes' => false, 'status' => false,
            'reviewed_by' => true, 'reviewed_at' => true, 'rejection_reason' => true,
            'created_at' => true, 'updated_at' => true,
        ],
        'campaigns' => [
            'id' => false, 'borrower_id' => false, 'title' => false, 'purpose' => false,
            'description' => false, 'business_background' => true, 'amount_requested' => false,
            'tenure_months' => false, 'contract_type' => false, 'interest_rate_percent' => true,
            'markup_amount' => true, 'asset_description' => true,
            'investor_profit_share_percent' => true, 'status' => false, 'status_reason' => true,
            'funding_deadline' => true, 'submitted_at' => true, 'approved_at' => true,
            'funded_at' => true, 'created_at' => true, 'updated_at' => true,
        ],
        'pledges' => [
            'id' => false, 'campaign_id' => false, 'investor_id' => false, 'amount' => false,
            'status' => false, 'created_at' => true, 'updated_at' => true,
        ],
        'wallet_accounts' => [
            'id' => false, 'type' => false, 'user_id' => true, 'campaign_id' => true,
            'created_at' => true, 'updated_at' => true,
        ],
        'wallet_transactions' => [
            'id' => false, 'wallet_account_id' => false, 'transfer_ref' => false, 'type' => false,
            'direction' => false, 'amount' => false, 'balance_after' => false, 'pledge_id' => true,
            'repayment_id' => true, 'description' => true, 'created_at' => false,
        ],
        'repayment_schedules' => [
            'id' => false, 'campaign_id' => false, 'instalment_no' => false, 'due_date' => false,
            'capital_due' => false, 'return_due' => true, 'status' => false, 'paid_at' => true,
            'created_at' => true, 'updated_at' => true,
        ],
        'repayments' => [
            'id' => false, 'repayment_schedule_id' => false, 'amount' => false, 'paid_at' => false,
            'created_at' => true, 'updated_at' => true,
        ],
        'risk_scores' => [
            'id' => false, 'campaign_id' => false, 'status' => false, 'input_features' => false,
            'probability_of_default' => true, 'grade' => true, 'top_factors' => true,
            'model_version' => true, 'attempts' => false, 'error_message' => true,
            'scored_at' => true, 'created_at' => true, 'updated_at' => true,
        ],
        'notifications' => [
            'id' => false, 'type' => false, 'notifiable_type' => false, 'notifiable_id' => false,
            'data' => false, 'read_at' => true, 'created_at' => true, 'updated_at' => true,
        ],
        'audit_logs' => [
            'id' => false, 'actor_id' => true, 'action' => false, 'subject_type' => true,
            'subject_id' => true, 'details' => true, 'ip_address' => true, 'created_at' => false,
        ],
    ];

    public static function erdTables(): array
    {
        return array_combine(array_keys(self::ERD), array_map(fn ($t) => [$t], array_keys(self::ERD)));
    }

    #[DataProvider('erdTables')]
    public function test_table_columns_and_nullability_match_the_erd(string $table): void
    {
        $rows = DB::select(
            'select COLUMN_NAME as name, IS_NULLABLE as nullable from information_schema.COLUMNS
             where TABLE_SCHEMA = database() and TABLE_NAME = ?',
            [$table]
        );

        $actual = [];
        foreach ($rows as $row) {
            $actual[$row->name] = $row->nullable === 'YES';
        }

        $expected = self::ERD[$table];
        ksort($expected);
        ksort($actual);

        $this->assertSame($expected, $actual, "Columns of {$table} differ from docs/erd.md");
    }

    public function test_foreign_keys_match_the_erd_and_all_restrict_deletes(): void
    {
        $rows = DB::select(
            'select k.TABLE_NAME as t, k.COLUMN_NAME as c, k.REFERENCED_TABLE_NAME as rt,
                    k.REFERENCED_COLUMN_NAME as rc, r.DELETE_RULE as on_delete
             from information_schema.KEY_COLUMN_USAGE k
             join information_schema.REFERENTIAL_CONSTRAINTS r
               on r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA and r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
             where k.TABLE_SCHEMA = database() and k.REFERENCED_TABLE_NAME is not null'
        );

        $actual = [];
        foreach ($rows as $row) {
            $actual["{$row->t}.{$row->c}"] = "{$row->rt}.{$row->rc} ON DELETE {$row->on_delete}";
        }
        ksort($actual);

        $expected = [
            'audit_logs.actor_id' => 'users.id',
            'borrower_profiles.user_id' => 'users.id',
            'campaigns.borrower_id' => 'users.id',
            'investor_profiles.user_id' => 'users.id',
            'kyc_documents.reviewed_by' => 'users.id',
            'kyc_documents.user_id' => 'users.id',
            'pledges.campaign_id' => 'campaigns.id',
            'pledges.investor_id' => 'users.id',
            'repayment_schedules.campaign_id' => 'campaigns.id',
            'repayments.repayment_schedule_id' => 'repayment_schedules.id',
            'risk_scores.campaign_id' => 'campaigns.id',
            'users.role_id' => 'roles.id',
            'wallet_accounts.campaign_id' => 'campaigns.id',
            'wallet_accounts.user_id' => 'users.id',
            'wallet_transactions.pledge_id' => 'pledges.id',
            'wallet_transactions.repayment_id' => 'repayments.id',
            'wallet_transactions.wallet_account_id' => 'wallet_accounts.id',
        ];
        $expected = array_map(fn ($target) => "{$target} ON DELETE RESTRICT", $expected);

        $this->assertSame($expected, $actual);
    }

    public function test_check_constraints_from_the_erd_exist(): void
    {
        $actual = DB::table('information_schema.TABLE_CONSTRAINTS')
            ->whereRaw('TABLE_SCHEMA = database()')
            ->where('CONSTRAINT_TYPE', 'CHECK')
            ->orderBy('CONSTRAINT_NAME')
            ->pluck('CONSTRAINT_NAME')
            ->all();

        $this->assertSame([
            'campaigns_amount_requested_check',
            'campaigns_conventional_terms_check',
            'campaigns_mudarabah_terms_check',
            'campaigns_murabahah_terms_check',
            'campaigns_tenure_months_check',
            'pledges_amount_check',
            'repayment_schedules_capital_due_check',
            'repayment_schedules_return_due_check',
            'repayments_amount_check',
            'risk_scores_probability_check',
            'wallet_accounts_owner_check',
            'wallet_transactions_amount_check',
            'wallet_transactions_balance_after_check',
            'wallet_transactions_return_payout_check',
        ], $actual);
    }

    public function test_national_id_columns_are_text_so_ciphertext_fits(): void
    {
        $this->assertSame('text', Schema::getColumnType('borrower_profiles', 'national_id_no'));
        $this->assertSame('text', Schema::getColumnType('investor_profiles', 'national_id_no'));
    }
}
