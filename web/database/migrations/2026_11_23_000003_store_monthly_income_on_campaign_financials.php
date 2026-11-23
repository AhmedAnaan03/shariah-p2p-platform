<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PB-58: a request's financial snapshot stores what the borrower declares, monthly income, as
 * the profile does. RiskFeatures (and the Python training code) work out annual income × 12 for
 * the model. Existing snapshots get annual ÷ 12; rolling back multiplies back (an amount that
 * wasn't a multiple of 12 can come back a few laari off).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_financials', function (Blueprint $table) {
            $table->decimal('monthly_income', 15, 2)->default(0)->after('campaign_id');
        });

        DB::statement('UPDATE campaign_financials SET monthly_income = ROUND(annual_income / 12, 2)');

        DB::statement('ALTER TABLE campaign_financials DROP CHECK campaign_financials_annual_income_check');
        Schema::table('campaign_financials', function (Blueprint $table) {
            $table->dropColumn('annual_income');
            $table->decimal('monthly_income', 15, 2)->default(null)->change();
        });
        DB::statement('ALTER TABLE campaign_financials ADD CONSTRAINT campaign_financials_monthly_income_check CHECK (monthly_income > 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE campaign_financials DROP CHECK campaign_financials_monthly_income_check');
        Schema::table('campaign_financials', function (Blueprint $table) {
            $table->decimal('annual_income', 15, 2)->default(0)->after('campaign_id');
        });

        DB::statement('UPDATE campaign_financials SET annual_income = monthly_income * 12');

        Schema::table('campaign_financials', function (Blueprint $table) {
            $table->dropColumn('monthly_income');
            $table->decimal('annual_income', 15, 2)->default(null)->change();
        });
        DB::statement('ALTER TABLE campaign_financials ADD CONSTRAINT campaign_financials_annual_income_check CHECK (annual_income > 0)');
    }
};
