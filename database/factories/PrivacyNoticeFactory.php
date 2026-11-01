<?php

namespace Database\Factories;

use App\Models\PrivacyNotice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrivacyNotice>
 */
class PrivacyNoticeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'version' => fake()->unique()->numerify('draft-2026.##'),
            'title' => 'Aviso de privacidade — versão de trabalho',
            'content' => 'Texto sujeito à aprovação jurídica antes da coleta pública.',
            'controller_name' => 'ROTTA — controlador a confirmar',
            'contact_email' => 'privacidade@example.test',
            'purpose' => 'Receber e avaliar manifestações de interesse de empresas e profissionais.',
            'legal_basis' => null,
            'retention_days' => null,
            'is_current' => false,
            'status' => 'draft',
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'version' => fake()->unique()->numerify('2026.##'),
            'legal_basis' => 'Base legal aprovada exclusivamente para teste sintético.',
            'retention_days' => 365,
            'is_current' => true,
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);
    }
}
