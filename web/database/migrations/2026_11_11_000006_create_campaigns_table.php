<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borrower_id')->constrained('users')->restrictOnDelete();
            $table->string('title', 150);
            $table->enum('purpose', ['start_up', 'growth', 'education', 'personal']);
            $table->text('description');
            $table->text('business_background')->nullable();
            // For a Murabahah this is the cost of the asset being bought.
            $table->decimal('amount_requested', 15, 2);
            $table->unsignedSmallInteger('tenure_months');
            $table->enum('contract_type', ['conventional', 'murabahah', 'mudarabah']);

            // Contract terms. Each one belongs to exactly one contract type (see the CHECKs below).
            $table->decimal('interest_rate_percent', 5, 2)->nullable();
            $table->decimal('markup_amount', 15, 2)->nullable();
            $table->string('asset_description')->nullable();
            $table->decimal('investor_profit_share_percent', 5, 2)->nullable();

            $table->enum('status', [
                'draft', 'submitted', 'live', 'rejected', 'suspended',
                'funded', 'failed', 'repaying', 'completed', 'defaulted',
            ])->default('draft');
            $table->string('status_reason')->nullable();
            $table->dateTime('funding_deadline')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('funded_at')->nullable();
            $table->timestamps();
        });

        // Laravel's schema builder has no CHECK method, so these are plain SQL. MySQL 8.0.16+
        // enforces CHECK constraints. Each contract rule is two-way: the term is required for
        // its own contract type and must be NULL for the others. This is what makes it
        // impossible to store an interest rate on a Shariah campaign.
        DB::statement(<<<'SQL'
            ALTER TABLE campaigns
                ADD CONSTRAINT campaigns_amount_requested_check CHECK (amount_requested > 0),
                ADD CONSTRAINT campaigns_tenure_months_check CHECK (tenure_months > 0),
                ADD CONSTRAINT campaigns_conventional_terms_check CHECK (
                    (contract_type = 'conventional'
                        AND interest_rate_percent IS NOT NULL
                        AND interest_rate_percent >= 0)
                    OR (contract_type <> 'conventional' AND interest_rate_percent IS NULL)
                ),
                ADD CONSTRAINT campaigns_murabahah_terms_check CHECK (
                    (contract_type = 'murabahah'
                        AND markup_amount IS NOT NULL
                        AND markup_amount >= 0
                        AND asset_description IS NOT NULL)
                    OR (contract_type <> 'murabahah'
                        AND markup_amount IS NULL
                        AND asset_description IS NULL)
                ),
                ADD CONSTRAINT campaigns_mudarabah_terms_check CHECK (
                    (contract_type = 'mudarabah'
                        AND investor_profit_share_percent IS NOT NULL
                        AND investor_profit_share_percent > 0
                        AND investor_profit_share_percent < 100)
                    OR (contract_type <> 'mudarabah' AND investor_profit_share_percent IS NULL)
                )
            SQL);
    }

    public function down(): void
    {
        // Dropping the table also drops its CHECK constraints.
        Schema::dropIfExists('campaigns');
    }
};
