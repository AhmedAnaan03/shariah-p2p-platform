<?php

namespace Database\Factories;

use App\Models\InvestorProfile;
use App\Models\Island;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvestorProfile>
 */
class InvestorProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->investor(),
            'phone' => '+960 9'.fake()->numerify('######'),
            'national_id_no' => 'A'.fake()->unique()->numerify('######'),
            'address_house' => 'H. '.fake()->lastName(),
            'address_road' => fake()->randomElement(['Majeedhee Magu', 'Ameenee Magu', 'Orchid Magu', 'Lily Magu']),
            'island_id' => fn () => Island::idFor('Malé City', 'Hulhumalé'),
            'postcode' => '2'.fake()->numerify('####'),
            'risk_acknowledged_at' => null,
            'shariah_only' => false,
        ];
    }

    /**
     * A profile saved in onboarding step 1, before the address step.
     */
    public function withoutAddress(): static
    {
        return $this->state(fn () => [
            'address_house' => null, 'address_road' => null, 'island_id' => null, 'postcode' => null,
        ]);
    }
}
