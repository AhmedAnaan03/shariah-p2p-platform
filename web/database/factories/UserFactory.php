<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state: a borrower whose account is pending verification.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // A closure runs only when the user is actually made, after the roles exist.
            'role_id' => fn () => Role::idFor(Role::BORROWER),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'status' => 'pending_verification',
            'remember_token' => Str::random(10),
        ];
    }

    public function borrower(): static
    {
        return $this->state(fn () => ['role_id' => Role::idFor(Role::BORROWER)]);
    }

    public function investor(): static
    {
        return $this->state(fn () => ['role_id' => Role::idFor(Role::INVESTOR)]);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role_id' => Role::idFor(Role::ADMIN), 'status' => 'verified']);
    }

    /**
     * An account an administrator has approved.
     */
    public function verified(): static
    {
        return $this->state(fn () => ['status' => 'verified']);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
