<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PB-58: the borrower's financial profile, declared once in the onboarding wizard and verified by
 * an administrator with the rest of KYC, instead of on every financing request (PB-14).
 *
 * Monthly income replaces the profile's declared annual income: existing profiles get
 * annual ÷ 12. The other fields are copied from the borrower's most recent financing request, if
 * any, otherwise left empty for the wizard's finance step. Columns are nullable because a profile
 * exists from step 1, before the finance step is done; the form requires every one.
 *
 * Rolling back puts declared_annual_income back as monthly income × 12 (an amount that wasn't a
 * multiple of 12 can come back a few laari off) and drops the new columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('borrower_profiles', function (Blueprint $table) {
            $table->decimal('monthly_income', 15, 2)->nullable()->after('years_operating');
            // Existing loans, financing and cards, per month, excluding housing (LendingClub's dti).
            $table->decimal('monthly_debt_repayments', 15, 2)->nullable()->after('monthly_income');
            // 0 = less than a year, 10 = ten years or more.
            $table->unsignedTinyInteger('employment_years')->nullable()->after('monthly_debt_repayments');
            $table->enum('housing', ['rent', 'own', 'own_with_home_loan', 'family_home'])->nullable()->after('employment_years');
            // Self-declared credit history, checked against the MMA credit report.
            $table->unsignedSmallInteger('open_credit_lines')->nullable()->after('housing');
            $table->unsignedSmallInteger('late_payments_2y')->nullable()->after('open_credit_lines');
            $table->unsignedSmallInteger('public_records')->nullable()->after('late_payments_2y');
        });

        DB::statement('UPDATE borrower_profiles SET monthly_income = ROUND(declared_annual_income / 12, 2)');

        // The latest request's answers are the borrower's most recent declaration.
        DB::statement(<<<'SQL'
            UPDATE borrower_profiles p
            JOIN (
                SELECT c.borrower_id, MAX(c.id) AS campaign_id
                FROM campaigns c JOIN campaign_financials f ON f.campaign_id = c.id
                GROUP BY c.borrower_id
            ) latest ON latest.borrower_id = p.user_id
            JOIN campaign_financials f ON f.campaign_id = latest.campaign_id
            SET p.monthly_debt_repayments = f.monthly_debt_repayments,
                p.employment_years = f.employment_years,
                p.housing = f.housing,
                p.open_credit_lines = f.open_credit_lines,
                p.late_payments_2y = f.late_payments_2y,
                p.public_records = f.public_records
            SQL);

        Schema::table('borrower_profiles', function (Blueprint $table) {
            $table->dropColumn('declared_annual_income');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE borrower_profiles
                ADD CONSTRAINT borrower_profiles_monthly_income_check CHECK (monthly_income IS NULL OR monthly_income > 0),
                ADD CONSTRAINT borrower_profiles_employment_years_check CHECK (employment_years IS NULL OR employment_years <= 10)
            SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE borrower_profiles DROP CHECK borrower_profiles_monthly_income_check, DROP CHECK borrower_profiles_employment_years_check');

        Schema::table('borrower_profiles', function (Blueprint $table) {
            $table->decimal('declared_annual_income', 15, 2)->default(0)->after('years_operating');
        });

        DB::statement('UPDATE borrower_profiles SET declared_annual_income = COALESCE(monthly_income, 0) * 12');

        Schema::table('borrower_profiles', function (Blueprint $table) {
            $table->decimal('declared_annual_income', 15, 2)->default(null)->change();
            $table->dropColumn([
                'monthly_income', 'monthly_debt_repayments', 'employment_years', 'housing',
                'open_credit_lines', 'late_payments_2y', 'public_records',
            ]);
        });
    }
};
