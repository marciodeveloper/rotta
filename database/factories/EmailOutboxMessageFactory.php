<?php

namespace Database\Factories;

use App\Models\EmailConfirmationToken;
use App\Models\EmailOutboxMessage;
use App\Models\PreRegistration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailOutboxMessage>
 */
class EmailOutboxMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pre_registration_id' => PreRegistration::factory(),
            'email_confirmation_token_id' => fn (array $attributes): string => EmailConfirmationToken::factory()->create([
                'pre_registration_id' => $attributes['pre_registration_id'],
            ])->id,
            'confirmation_token' => str_repeat('t', 43),
            'status' => EmailOutboxMessage::STATUS_PENDING,
            'attempts' => 0,
            'available_at' => now(),
        ];
    }
}
