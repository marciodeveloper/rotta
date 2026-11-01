<?php

namespace Tests\Feature;

use App\Models\EmailConfirmationToken;
use App\Models\PreRegistration;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class EmailConfirmationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_valid_token_is_consumed_and_moves_registration_to_review(): void
    {
        $plainToken = str_repeat('a', 43);
        $registration = PreRegistration::factory()->create();
        $confirmation = EmailConfirmationToken::factory()->for($registration)->create([
            'token_hash' => hash('sha256', $plainToken), 'expires_at' => now()->addHour(),
        ]);

        $response = $this->get(route('pre-registration.confirm', ['token' => $plainToken]));

        $response->assertOk()->assertSee('E-mail confirmado.');
        $this->assertNotNull($confirmation->fresh()->used_at);
        $this->assertFalse($confirmation->fresh()->is_active);
        $this->assertSame(PreRegistration::STATUS_UNDER_REVIEW, $registration->fresh()->status);
        $this->assertSame(2, $registration->fresh()->status_version);
        $this->assertNotNull($registration->fresh()->email_confirmed_at);
    }

    public function test_used_token_returns_neutral_failure_without_changing_status(): void
    {
        $plainToken = str_repeat('b', 43);
        $registration = PreRegistration::factory()->create();
        EmailConfirmationToken::factory()->for($registration)->create([
            'token_hash' => hash('sha256', $plainToken), 'used_at' => now(), 'is_active' => false,
        ]);

        $response = $this->get(route('pre-registration.confirm', ['token' => $plainToken]));

        $response->assertOk()->assertSee('Não foi possível confirmar este link.');
        $this->assertSame(PreRegistration::STATUS_PENDING_EMAIL, $registration->fresh()->status);
    }
}
