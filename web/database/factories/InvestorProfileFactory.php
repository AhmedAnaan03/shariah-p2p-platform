<?php

namespace Database\Factories;

use App\Models\InvestorProfile;
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
            'address' => fake()->streetAddress().', Hulhumalé',
            'risk_acknowledged_at' => null,
            'shariah_only' => false,
        ];
    }
}
