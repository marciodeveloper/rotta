<?php

namespace Tests\Feature;

use App\Contracts\TurnstileVerifier;
use App\Mail\ConfirmPreRegistrationMail;
use App\Models\PreRegistration;
use App\Models\PrivacyNotice;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PreRegistrationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_valid_payload_creates_encrypted_record_and_sends_confirmation(): void
    {
        Mail::fake();
        $notice = PrivacyNotice::factory()->create(['version' => 'draft-2026.01', 'is_current' => true]);
        $this->mock(TurnstileVerifier::class)->shouldReceive('verify')->once()->with('test-token', '127.0.0.1')->andReturnTrue();

        $response = $this->post(route('pre-registration.store'), $this->validPayload($notice));

        $response->assertRedirect(route('pre-registration.success'));
        $this->assertDatabaseCount('pre_registrations', 1);
        $registration = PreRegistration::query()->firstOrFail();
        $this->assertSame('contato@example.test', $registration->email);
        $this->assertNotSame('contato@example.test', DB::table('pre_registrations')->value('email'));
        Mail::assertSent(ConfirmPreRegistrationMail::class, fn (ConfirmPreRegistrationMail $mail): bool => $mail->registration->is($registration));
    }

    public function test_duplicate_document_returns_neutral_response_without_new_record_or_email(): void
    {
        Mail::fake();
        $notice = PrivacyNotice::factory()->create(['version' => 'draft-2026.01', 'is_current' => true]);
        PreRegistration::factory()->for($notice)->create([
            'document' => '11222333000181',
            'document_hash' => hash_hmac('sha256', '11222333000181', (string) config('prelaunch.data_pepper')),
        ]);
        $this->mock(TurnstileVerifier::class)->shouldReceive('verify')->once()->andReturnTrue();

        $response = $this->post(route('pre-registration.store'), $this->validPayload($notice));

        $response->assertRedirect(route('pre-registration.success'));
        $this->assertDatabaseCount('pre_registrations', 1);
        Mail::assertNothingSent();
    }

    public function test_honeypot_returns_neutral_response_without_persisting_or_verifying_captcha(): void
    {
        Mail::fake();
        $notice = PrivacyNotice::factory()->create(['version' => 'draft-2026.01', 'is_current' => true]);
        $this->mock(TurnstileVerifier::class)->shouldNotReceive('verify');
        $payload = [...$this->validPayload($notice), 'company_website' => 'https://bot.invalid'];

        $response = $this->post(route('pre-registration.store'), $payload);

        $response->assertRedirect(route('pre-registration.success'));
        $this->assertDatabaseCount('pre_registrations', 0);
        Mail::assertNothingSent();
    }

    public function test_rejects_invalid_cnpj_before_persisting(): void
    {
        $notice = PrivacyNotice::factory()->create(['version' => 'draft-2026.01', 'is_current' => true]);
        $payload = [...$this->validPayload($notice), 'document' => '11.222.333/0001-80'];

        $response = $this->post(route('pre-registration.store'), $payload);

        $response->assertInvalid(['document' => 'Informe um CPF ou CNPJ válido']);
        $this->assertDatabaseCount('pre_registrations', 0);
    }

    public function test_rejects_failed_turnstile_without_persisting(): void
    {
        $notice = PrivacyNotice::factory()->create(['version' => 'draft-2026.01', 'is_current' => true]);
        $this->mock(TurnstileVerifier::class)->shouldReceive('verify')->once()->andReturnFalse();

        $response = $this->post(route('pre-registration.store'), $this->validPayload($notice));

        $response->assertInvalid(['cf-turnstile-response' => 'Não foi possível validar']);
        $this->assertDatabaseCount('pre_registrations', 0);
    }

    /** @return array<string, mixed> */
    private function validPayload(PrivacyNotice $notice): array
    {
        return [
            'applicant_type' => 'company', 'document' => '11.222.333/0001-81',
            'business_name' => 'Café do Vale', 'category' => 'Gastronomia',
            'short_description' => 'Café regional com produtos artesanais e atendimento local.',
            'responsible_name' => 'Maria de Teste', 'email' => 'contato@example.test',
            'phone' => '(24) 99999-8888', 'website' => 'https://example.test', 'instagram' => '@cafedovale',
            'address_mode' => 'public_address', 'postal_code' => '27700-000', 'street' => 'Rua de Teste',
            'street_number' => '100', 'neighborhood' => 'Centro', 'city' => 'Vassouras', 'state' => 'RJ',
            'marketing_opt_in' => false, 'privacy_accepted' => '1', 'privacy_notice_version' => $notice->version,
            'cf-turnstile-response' => 'test-token', 'company_website' => '',
        ];
    }
}
