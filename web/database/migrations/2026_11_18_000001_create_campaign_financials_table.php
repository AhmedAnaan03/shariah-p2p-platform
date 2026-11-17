<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PB-14: the borrower's financial information for one financing request, in structured
 * columns the risk model can use (see docs/report-notes/PB-12.md, "What this means for
 * PB-14's form"). One row per campaign.
 *
 * Kept in its own table rather than as more columns on campaigns: campaigns holds the offer
 * investors see (amount, contract terms, status), this holds the facts the scoring uses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_financials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->unique()->constrained()->restrictOnDelete();

            // Copied from the profile when the request is made and kept with it, so a later
            // profile change doesn't change what this request was scored on.
            $table->decimal('annual_income', 15, 2);
            // Existing loans, financing and cards, per month. Housing (rent or a home loan)
            // is not included, matching how LendingClub's debt-to-income ratio is defined.
            $table->decimal('monthly_debt_repayments', 15, 2);
            // 0 = less than a year, 10 = ten years or more (LendingClub's "10+ years").
            $table->unsignedTinyInteger('employment_years');
            $table->enum('housing', ['rent', 'own', 'own_with_home_loan', 'family_home']);

            // Self-declared credit history. An administrator checks these against the MMA
            // Credit Information System report, when the borrower attaches one.
            $table->unsignedSmallInteger('open_credit_lines');
            $table->unsignedSmallInteger('late_payments_2y');
            $table->unsignedSmallInteger('public_records');

            $table->timestamps();
        });

        // Unsigned columns already refuse negatives; these cover the rest.
        DB::statement(<<<'SQL'
            ALTER TABLE campaign_financials
                ADD CONSTRAINT campaign_financials_annual_income_check CHECK (annual_income > 0),
                ADD CONSTRAINT campaign_financials_monthly_debt_check CHECK (monthly_debt_repayments >= 0),
                ADD CONSTRAINT campaign_financials_employment_years_check CHECK (employment_years <= 10)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_financials');
    }
};
