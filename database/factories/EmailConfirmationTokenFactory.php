<?php

namespace Database\Factories;

use App\Models\EmailConfirmationToken;
use App\Models\PreRegistration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailConfirmationToken>
 */
class EmailConfirmationTokenFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $token = fake()->unique()->sha256();

        return [
            'pre_registration_id' => PreRegistration::factory(),
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addHour(),
        ];
    }
}
