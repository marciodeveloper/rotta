<?php

namespace Tests\Feature;

use App\Contracts\TurnstileVerifier;
use App\Jobs\DeliverConfirmationEmail;
use App\Models\EmailConfirmationToken;
use App\Models\PreRegistration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ResendConfirmationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_resend_rotates_token_and_keeps_only_one_active_token(): void
    {
        Queue::fake();
        $registration = PreRegistration::factory()->create([
            'document' => '11222333000181',
            'document_hash' => hash_hmac('sha256', '11222333000181', (string) config('prelaunch.data_pepper')),
            'email' => 'contato@example.test',
            'email_hash' => hash_hmac('sha256', 'contato@example.test', (string) config('prelaunch.data_pepper')),
        ]);
        $oldToken = EmailConfirmationToken::factory()->for($registration)->create();
        $this->mock(TurnstileVerifier::class)->shouldReceive('verify')->twice()->andReturnTrue();
        $payload = $this->validPayload();

        $this->post(route('pre-registration.resend'), $payload)->assertRedirect(route('pre-registration.success'));
        $this->post(route('pre-registration.resend'), $payload)->assertRedirect(route('pre-registration.success'));

        $this->assertFalse($oldToken->fresh()->is_active);
        $this->assertSame(1, EmailConfirmationToken::query()->whereBelongsTo($registration)->where('is_active', true)->count());
        $this->assertDatabaseCount('email_confirmation_tokens', 3);
        $this->assertDatabaseCount('email_outbox_messages', 2);
        Queue::assertPushed(DeliverConfirmationEmail::class, 2);
    }

    public function test_unknown_identity_returns_neutral_response_without_outbox(): void
    {
        Queue::fake();
        $this->mock(TurnstileVerifier::class)->shouldReceive('verify')->once()->andReturnTrue();

        $response = $this->post(route('pre-registration.resend'), $this->validPayload());

        $response->assertRedirect(route('pre-registration.success'));
        $this->assertDatabaseCount('email_outbox_messages', 0);
        Queue::assertNothingPushed();
    }

    public function test_database_rejects_two_active_tokens_for_one_registration(): void
    {
        $registration = PreRegistration::factory()->create();
        EmailConfirmationToken::factory()->for($registration)->create();

        $this->expectException(QueryException::class);
        EmailConfirmationToken::factory()->for($registration)->create();
    }

    /** @return array<string, string> */
    private function validPayload(): array
    {
        return [
            'applicant_type' => 'company',
            'document' => '11.222.333/0001-81',
            'email' => 'contato@example.test',
            'cf-turnstile-response' => 'test-token',
        ];
    }
}
