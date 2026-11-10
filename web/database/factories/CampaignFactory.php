<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    /**
     * Default: a conventional draft campaign. Use murabahah() or mudarabah() for Shariah mode;
     * each state also clears the other contracts' terms, as the CHECK constraints require.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'borrower_id' => User::factory()->borrower()->verified(),
            'title' => fake()->sentence(4),
            'purpose' => fake()->randomElement(['start_up', 'growth', 'education', 'personal']),
            'description' => fake()->paragraph(),
            'business_background' => fake()->paragraph(),
            'amount_requested' => 50_000,
            'tenure_months' => 12,
            'contract_type' => Campaign::CONTRACT_CONVENTIONAL,
            'interest_rate_percent' => 8.50,
            'markup_amount' => null,
            'asset_description' => null,
            'investor_profit_share_percent' => null,
        ];
    }

    public function conventional(): static
    {
        return $this->state(fn () => [
            'contract_type' => Campaign::CONTRACT_CONVENTIONAL,
            'interest_rate_percent' => 8.50,
            'markup_amount' => null,
            'asset_description' => null,
            'investor_profit_share_percent' => null,
        ]);
    }

    public function murabahah(): static
    {
        return $this->state(fn () => [
            'contract_type' => Campaign::CONTRACT_MURABAHAH,
            'interest_rate_percent' => null,
            'markup_amount' => 4_000,
            'asset_description' => 'Refrigerated delivery van',
            'investor_profit_share_percent' => null,
        ]);
    }

    public function mudarabah(): static
    {
        return $this->state(fn () => [
            'contract_type' => Campaign::CONTRACT_MUDARABAH,
            'interest_rate_percent' => null,
            'markup_amount' => null,
            'asset_description' => null,
            'investor_profit_share_percent' => 60,
        ]);
    }
}
