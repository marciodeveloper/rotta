<?php

namespace Database\Factories;

use App\Models\PreRegistration;
use App\Models\PreRegistrationStatusHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PreRegistrationStatusHistory>
 */
class PreRegistrationStatusHistoryFactory extends Factory
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
            'from_status' => null,
            'to_status' => PreRegistration::STATUS_PENDING_EMAIL,
        ];
    }
}
