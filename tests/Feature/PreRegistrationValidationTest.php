<?php

namespace Tests\Feature;

use App\Contracts\TurnstileVerifier;
use App\Models\PrivacyNotice;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PreRegistrationValidationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_application_uses_brazilian_portuguese_for_validation(): void
    {
        $this->assertSame('pt_BR', config('app.locale'));
        $this->assertSame('pt_BR', config('app.fallback_locale'));

        $response = $this->post(route('pre-registration.store'), []);

        $response->assertInvalid([
            'business_name' => 'Informe o nome comercial ou profissional.',
            'short_description' => 'Escreva uma descrição curta do negócio ou atuação.',
            'email' => 'Informe o e-mail de contato.',
            'city' => 'Informe a cidade.',
            'privacy_accepted' => 'Você precisa aceitar o aviso de privacidade para enviar.',
            'cf-turnstile-response' => 'Conclua a verificação de segurança.',
        ]);
    }

    public function test_description_with_19_characters_is_rejected(): void
    {
        $notice = PrivacyNotice::factory()->published()->create();
        $payload = [...$this->validPayload($notice), 'short_description' => '1234567890123456789'];

        $response = $this->post(route('pre-registration.store'), $payload);

        $response->assertInvalid(['short_description' => 'A descrição curta deve ter pelo menos 20 caracteres.']);
        $this->assertDatabaseCount('pre_registrations', 0);
        $this->assertDatabaseCount('email_outbox_messages', 0);
    }

    public function test_description_with_20_characters_is_accepted(): void
    {
        Queue::fake();
        $notice = PrivacyNotice::factory()->published()->create();
        $this->mock(TurnstileVerifier::class)->shouldReceive('verify')->once()->andReturnTrue();
        $payload = [...$this->validPayload($notice), 'short_description' => '12345678901234567890'];

        $response = $this->post(route('pre-registration.store'), $payload);

        $response->assertRedirect(route('pre-registration.success'));
        $this->assertDatabaseCount('pre_registrations', 1);
        $this->assertDatabaseCount('email_outbox_messages', 1);
    }

    #[DataProvider('invalidContactProvider')]
    public function test_contact_fields_return_specific_portuguese_messages(string $field, string $value, string $message): void
    {
        $notice = PrivacyNotice::factory()->published()->create();
        $payload = [...$this->validPayload($notice), $field => $value];

        $response = $this->post(route('pre-registration.store'), $payload);

        $response->assertInvalid([$field => $message]);
        $this->assertDatabaseCount('pre_registrations', 0);
    }

    /** @return array<string, array{string, string, string}> */
    public static function invalidContactProvider(): array
    {
        return [
            'email' => ['email', 'contato-invalido', 'Informe um endereço de e-mail válido.'],
            'telefone' => ['phone', '1234', 'Informe um telefone brasileiro válido, com DDD.'],
            'website sem protocolo' => ['website', 'example.test', 'Informe uma URL completa iniciada por http:// ou https://.'],
            'Instagram inválido' => ['instagram', '@perfil com espaço', 'Informe um perfil do Instagram válido, com até 30 caracteres.'],
        ];
    }

    public function test_invalid_cpf_returns_specific_message(): void
    {
        $notice = PrivacyNotice::factory()->published()->create();
        $payload = [...$this->validPayload($notice), 'applicant_type' => 'professional', 'document' => '529.982.247-24'];

        $response = $this->post(route('pre-registration.store'), $payload);

        $response->assertInvalid(['document' => 'Informe um CPF válido, incluindo os dígitos verificadores.']);
        $this->assertDatabaseCount('pre_registrations', 0);
    }

    public function test_invalid_cnpj_returns_specific_message(): void
    {
        $notice = PrivacyNotice::factory()->published()->create();
        $payload = [...$this->validPayload($notice), 'document' => '11.222.333/0001-80'];

        $response = $this->post(route('pre-registration.store'), $payload);

        $response->assertInvalid(['document' => 'Informe um CNPJ válido, incluindo os dígitos verificadores.']);
        $this->assertDatabaseCount('pre_registrations', 0);
    }

    public function test_valid_cpf_is_accepted_for_professional(): void
    {
        Queue::fake();
        $notice = PrivacyNotice::factory()->published()->create();
        $this->mock(TurnstileVerifier::class)->shouldReceive('verify')->once()->andReturnTrue();
        $payload = [...$this->validPayload($notice), 'applicant_type' => 'professional', 'document' => '529.982.247-25'];

        $response = $this->post(route('pre-registration.store'), $payload);

        $response->assertRedirect(route('pre-registration.success'));
        $this->assertDatabaseCount('pre_registrations', 1);
    }

    public function test_public_address_requires_its_visible_fields(): void
    {
        $notice = PrivacyNotice::factory()->published()->create();
        $payload = [...$this->validPayload($notice), 'postal_code' => '', 'street' => '', 'street_number' => '', 'neighborhood' => ''];

        $response = $this->post(route('pre-registration.store'), $payload);

        $response->assertInvalid([
            'postal_code' => 'Informe o CEP do endereço público.',
            'street' => 'Informe o logradouro do endereço público.',
            'street_number' => 'Informe o número do endereço público.',
            'neighborhood' => 'Informe o bairro do endereço público.',
        ]);
        $this->assertDatabaseCount('pre_registrations', 0);
    }

    public function test_service_area_does_not_require_disabled_public_address_fields(): void
    {
        Queue::fake();
        $notice = PrivacyNotice::factory()->published()->create();
        $this->mock(TurnstileVerifier::class)->shouldReceive('verify')->once()->andReturnTrue();
        $payload = [...$this->validPayload($notice),
            'address_mode' => 'service_area',
            'postal_code' => null,
            'street' => null,
            'street_number' => null,
            'neighborhood' => null,
            'service_area' => 'Vassouras, Miguel Pereira e região',
        ];

        $response = $this->post(route('pre-registration.store'), $payload);

        $response->assertRedirect(route('pre-registration.success'));
        $this->assertDatabaseCount('pre_registrations', 1);
    }

    public function test_service_area_requires_a_description(): void
    {
        $notice = PrivacyNotice::factory()->published()->create();
        $payload = [...$this->validPayload($notice), 'address_mode' => 'service_area', 'service_area' => ''];

        $response = $this->post(route('pre-registration.store'), $payload);

        $response->assertInvalid(['service_area' => 'Descreva a área de atendimento.']);
        $this->assertDatabaseCount('pre_registrations', 0);
    }

    public function test_media_requires_alt_text_and_rights_confirmation(): void
    {
        $notice = PrivacyNotice::factory()->published()->create();
        $payload = [...$this->validPayload($notice),
            'logo' => UploadedFile::fake()->image('logo.jpg'),
            'logo_alt' => '',
            'media_rights_confirmed' => null,
        ];

        $response = $this->post(route('pre-registration.store'), $payload);

        $response->assertInvalid([
            'logo_alt' => 'Informe a descrição alternativa da logo.',
            'media_rights_confirmed' => 'Confirme que possui autorização para usar as imagens enviadas.',
        ]);
        $this->assertDatabaseCount('pre_registrations', 0);
        $this->assertDatabaseCount('email_outbox_messages', 0);
    }

    public function test_gallery_limit_returns_specific_message(): void
    {
        $notice = PrivacyNotice::factory()->published()->create();
        $images = collect(range(1, 7))->map(fn (int $index) => UploadedFile::fake()->image("foto-{$index}.jpg"))->all();
        $payload = [...$this->validPayload($notice),
            'gallery' => $images,
            'gallery_alt' => collect(range(1, 7))->map(fn (int $index) => "Foto {$index}")->all(),
            'media_rights_confirmed' => '1',
        ];

        $response = $this->post(route('pre-registration.store'), $payload);

        $response->assertInvalid(['gallery' => 'Envie no máximo seis fotografias.']);
        $this->assertDatabaseCount('pre_registrations', 0);
    }

    public function test_media_type_returns_a_specific_portuguese_message(): void
    {
        $notice = PrivacyNotice::factory()->published()->create();
        $payload = [...$this->validPayload($notice),
            'logo' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'),
            'logo_alt' => 'Logo sintética',
            'media_rights_confirmed' => '1',
        ];

        $response = $this->post(route('pre-registration.store'), $payload);

        $response->assertInvalid(['logo' => 'A logo deve ser uma imagem JPEG, PNG ou WebP.']);
        $this->assertDatabaseCount('pre_registrations', 0);
        $this->assertDatabaseCount('email_outbox_messages', 0);
    }

    public function test_media_size_returns_a_specific_portuguese_message(): void
    {
        $notice = PrivacyNotice::factory()->published()->create();
        $payload = [...$this->validPayload($notice),
            'cover' => UploadedFile::fake()->image('capa.jpg')->size(5121),
            'cover_alt' => 'Capa sintética',
            'media_rights_confirmed' => '1',
        ];

        $response = $this->post(route('pre-registration.store'), $payload);

        $response->assertInvalid(['cover' => 'A foto de capa deve ter no máximo 5 MB.']);
        $this->assertDatabaseCount('pre_registrations', 0);
        $this->assertDatabaseCount('email_outbox_messages', 0);
    }

    #[DataProvider('missingConsentProvider')]
    public function test_privacy_and_turnstile_return_specific_messages(string $field, mixed $value, string $message): void
    {
        $notice = PrivacyNotice::factory()->published()->create();
        $payload = [...$this->validPayload($notice), $field => $value];

        $response = $this->post(route('pre-registration.store'), $payload);

        $response->assertInvalid([$field => $message]);
        $this->assertDatabaseCount('pre_registrations', 0);
        $this->assertDatabaseCount('email_outbox_messages', 0);
    }

    /** @return array<string, array{string, mixed, string}> */
    public static function missingConsentProvider(): array
    {
        return [
            'privacidade' => ['privacy_accepted', null, 'Você precisa aceitar o aviso de privacidade para enviar.'],
            'Turnstile' => ['cf-turnstile-response', '', 'Conclua a verificação de segurança.'],
        ];
    }

    public function test_server_error_markup_identifies_the_contact_step_and_field(): void
    {
        $notice = PrivacyNotice::factory()->published()->create();
        $payload = [...$this->validPayload($notice), 'email' => 'inválido'];

        $response = $this->followingRedirects()->post(route('pre-registration.store'), $payload);

        $response->assertOk()
            ->assertSee('data-step="2"', false)
            ->assertSee('data-server-error-field="email"', false)
            ->assertSee('data-server-error-for="email"', false)
            ->assertSee('Informe um endereço de e-mail válido.');
    }

    public function test_gallery_upload_error_targets_review_step_and_preserves_text_fields(): void
    {
        $notice = PrivacyNotice::factory()->published()->create();
        $payload = [...$this->validPayload($notice),
            'business_name' => 'Negócio preservado após erro',
            'short_description' => 'Descrição preservada após uma falha de upload da galeria.',
            'gallery' => [UploadedFile::fake()->create('arquivo.pdf', 10, 'application/pdf')],
            'gallery_alt' => ['Arquivo inválido para teste'],
            'media_rights_confirmed' => '1',
        ];

        $response = $this->followingRedirects()->post(route('pre-registration.store'), $payload);

        $response->assertOk()
            ->assertSee('data-server-error-field="gallery.0"', false)
            ->assertSee('data-server-error-for="gallery"', false)
            ->assertSee('Cada fotografia deve ser uma imagem JPEG, PNG ou WebP.')
            ->assertSee('value="Negócio preservado após erro"', false)
            ->assertSee('Descrição preservada após uma falha de upload da galeria.');
        $this->assertDatabaseCount('pre_registrations', 0);
        $this->assertDatabaseCount('email_outbox_messages', 0);
    }

    /** @return array<string, mixed> */
    private function validPayload(PrivacyNotice $notice): array
    {
        return [
            'applicant_type' => 'company',
            'document' => '11.222.333/0001-81',
            'business_name' => 'Café do Vale',
            'category' => 'Gastronomia',
            'short_description' => 'Café regional com produtos artesanais e atendimento local.',
            'responsible_name' => 'Maria de Teste',
            'email' => 'contato@example.test',
            'phone' => '(24) 99999-8888',
            'website' => 'https://example.test',
            'instagram' => '@cafedovale',
            'address_mode' => 'public_address',
            'postal_code' => '27700-000',
            'street' => 'Rua de Teste',
            'street_number' => '100',
            'neighborhood' => 'Centro',
            'city' => 'Vassouras',
            'state' => 'RJ',
            'marketing_opt_in' => false,
            'privacy_accepted' => '1',
            'privacy_notice_version' => $notice->version,
            'cf-turnstile-response' => 'test-token',
            'company_website' => '',
        ];
    }
}
