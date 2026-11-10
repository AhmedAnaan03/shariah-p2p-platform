<?php

namespace Database\Factories;

use App\Models\BorrowerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BorrowerProfile>
 */
class BorrowerProfileFactory extends Factory
{
    /**
     * Default: an SME borrower.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->borrower(),
            'borrower_type' => 'sme',
            'phone' => '+960 7'.fake()->numerify('######'),
            'national_id_no' => 'A'.fake()->unique()->numerify('######'),
            'address' => fake()->streetAddress().', Malé',
            'business_name' => fake()->company(),
            'business_registration_no' => 'SP-'.fake()->numerify('#####'),
            'sector' => fake()->randomElement(['retail', 'fisheries', 'tourism', 'agriculture']),
            'years_operating' => fake()->numberBetween(1, 15),
            'declared_annual_income' => fake()->numberBetween(100_000, 2_000_000),
        ];
    }

    public function individual(): static
    {
        return $this->state(fn () => [
            'borrower_type' => 'individual',
            'business_name' => null,
            'business_registration_no' => null,
            'sector' => null,
            'years_operating' => null,
        ]);
    }
}
