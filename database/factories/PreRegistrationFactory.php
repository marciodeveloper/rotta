<?php

namespace Database\Factories;

use App\Models\PreRegistration;
use App\Models\PrivacyNotice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PreRegistration>
 */
class PreRegistrationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $document = fake()->unique()->numerify('##############');
        $email = fake()->unique()->safeEmail();
        $phone = '249'.fake()->numerify('########');

        return [
            'applicant_type' => 'company',
            'document' => $document,
            'document_hash' => hash('sha256', $document),
            'document_last_four' => substr($document, -4),
            'business_name' => fake()->company(),
            'category' => 'Gastronomia',
            'short_description' => fake()->sentence(12),
            'responsible_name' => fake()->name(),
            'email' => $email,
            'email_hash' => hash('sha256', $email),
            'phone' => $phone,
            'phone_hash' => hash('sha256', $phone),
            'address_mode' => 'public_address',
            'postal_code' => '27700000',
            'street' => 'Rua de Teste',
            'street_number' => '100',
            'neighborhood' => 'Centro',
            'city' => 'Vassouras',
            'state' => 'RJ',
            'marketing_opt_in' => false,
            'privacy_notice_id' => PrivacyNotice::factory(),
            'privacy_notice_version' => 'draft-2026.01',
            'status' => PreRegistration::STATUS_PENDING_EMAIL,
            'source' => 'prelaunch_web',
        ];
    }
}
