<?php

namespace Tests\Feature;

use App\Contracts\CepLookup;
use App\Contracts\TurnstileVerifier;
use App\Jobs\DeliverConfirmationEmail;
use App\Models\EmailOutboxMessage;
use App\Models\PreRegistration;
use App\Models\PrivacyNotice;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PreRegistrationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_form_links_the_full_notice_and_displays_the_current_version(): void
    {
        PrivacyNotice::factory()->published()->create(['version' => '2026-11-01']);

        $response = $this->get(route('pre-registration.create'))
            ->assertOk()
            ->assertSee('Versão 2026-11-01')
            ->assertSee('/privacidade/', false)
            ->assertSee('Leia o aviso de privacidade completo');

        $this->assertMatchesRegularExpression(
            '/<button[^>]*type="button"[^>]*formnovalidate[^>]*data-cep-button[^>]*>Buscar CEP<\/button>/',
            $response->getContent(),
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<button[^>]*data-cep-button[^>]*(?:formaction|formmethod)=/',
            $response->getContent(),
        );
    }

    public function test_ajax_cep_lookup_does_not_contaminate_invalid_submission_redirect(): void
    {
        PrivacyNotice::factory()->published()->create(['version' => '2026-11-01']);
        $this->mock(CepLookup::class)->shouldReceive('find')->once()->with('12604250')->andReturn([
            'postal_code' => '12604250',
            'street' => 'Rua Galante Giuseppe',
            'neighborhood' => 'Vila Geny',
            'city' => 'Lorena',
            'state' => 'SP',
        ]);

        $this->get(route('pre-registration.create'))->assertOk();
        $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->getJson(route('cep.lookup', ['postal_code' => '12604250']))
            ->assertOk();

        $this->post(route('pre-registration.store'), [])
            ->assertRedirect(route('pre-registration.create'));
        $this->assertDatabaseCount('pre_registrations', 0);
        $this->assertDatabaseCount('email_outbox_messages', 0);
    }

    public function test_invalid_submission_redirects_to_form_when_session_previous_url_is_cep_endpoint(): void
    {
        $cepUrl = route('cep.lookup', ['postal_code' => '12604250']);

        $this->withSession(['_previous.url' => $cepUrl])
            ->post(route('pre-registration.store'), [])
            ->assertRedirect(route('pre-registration.create'));

        $this->assertDatabaseCount('pre_registrations', 0);
        $this->assertDatabaseCount('email_outbox_messages', 0);
    }

    public function test_valid_payload_creates_encrypted_record_and_transactional_outbox(): void
    {
        Queue::fake();
        $notice = PrivacyNotice::factory()->published()->create(['version' => '2026.01']);
        $this->mock(TurnstileVerifier::class)->shouldReceive('verify')->once()->with('test-token', '127.0.0.1')->andReturnTrue();

        $response = $this->post(route('pre-registration.store'), $this->validPayload($notice));

        $response->assertRedirect(route('pre-registration.success'));
        $this->assertDatabaseCount('pre_registrations', 1);
        $registration = PreRegistration::query()->firstOrFail();
        $this->assertSame('contato@example.test', $registration->email);
        $this->assertNotSame('contato@example.test', DB::table('pre_registrations')->value('email'));
        $this->assertNotNull($registration->document_hash_v2);
        $this->assertNotNull($registration->email_hash_v2);
        $this->assertDatabaseHas(EmailOutboxMessage::class, ['pre_registration_id' => $registration->id, 'status' => EmailOutboxMessage::STATUS_PENDING]);
        Queue::assertPushed(DeliverConfirmationEmail::class, 1);
    }

    public function test_alphanumeric_cnpj_and_private_reencoded_media_are_persisted(): void
    {
        Queue::fake();
        Storage::fake('prelaunch_media');
        config(['prelaunch.media_disk' => 'prelaunch_media']);
        $notice = PrivacyNotice::factory()->published()->create(['version' => '2026.01']);
        $this->mock(TurnstileVerifier::class)->shouldReceive('verify')->once()->andReturnTrue();
        $payload = [...$this->validPayload($notice),
            'document' => '12.ABC.345/01DE-35',
            'logo' => UploadedFile::fake()->image('logo.jpg', 800, 600),
            'logo_alt' => 'Marca sintética do negócio',
            'gallery' => [UploadedFile::fake()->image('fachada.png', 1200, 800)],
            'gallery_alt' => ['Fachada sintética do negócio'],
            'media_rights_confirmed' => '1',
        ];

        $this->post(route('pre-registration.store'), $payload)->assertRedirect(route('pre-registration.success'));

        $registration = PreRegistration::query()->firstOrFail();
        $this->assertSame('12ABC34501DE35', $registration->document);
        $this->assertCount(2, $registration->media);
        foreach ($registration->media as $media) {
            Storage::disk('prelaunch_media')->assertExists($media->getRawOriginal('storage_key'));
            Storage::disk('prelaunch_media')->assertExists($media->getRawOriginal('thumbnail_storage_key'));
            $this->assertSame('pending_review', $media->status);
        }
    }

    public function test_disguised_executable_is_rejected_as_media(): void
    {
        $notice = PrivacyNotice::factory()->published()->create(['version' => '2026.01']);
        $this->mock(TurnstileVerifier::class)->shouldReceive('verify')->once()->andReturnTrue();
        $payload = [...$this->validPayload($notice),
            'logo' => UploadedFile::fake()->createWithContent('logo.jpg', "MZ\x00synthetic executable"),
            'logo_alt' => 'Arquivo inválido', 'media_rights_confirmed' => '1',
        ];

        $this->post(route('pre-registration.store'), $payload)->assertInvalid('media');
        $this->assertDatabaseCount('pre_registrations', 0);
    }

    public function test_rejects_more_than_eight_images_and_files_above_five_megabytes(): void
    {
        $notice = PrivacyNotice::factory()->published()->create(['version' => '2026.01']);
        $payload = [...$this->validPayload($notice),
            'cover' => UploadedFile::fake()->create('cover.jpg', 5_121, 'image/jpeg'),
            'cover_alt' => 'Capa sintética',
            'gallery' => collect(range(1, 7))->map(fn (int $index) => UploadedFile::fake()->image("gallery-{$index}.jpg"))->all(),
            'gallery_alt' => collect(range(1, 7))->map(fn (int $index) => "Imagem sintética {$index}")->all(),
            'media_rights_confirmed' => '1',
        ];

        $response = $this->post(route('pre-registration.store'), $payload);

        $response->assertInvalid(['cover', 'gallery']);
        $this->assertDatabaseCount('pre_registrations', 0);
    }

    public function test_duplicate_document_returns_neutral_response_without_new_record_or_email(): void
    {
        Queue::fake();
        $notice = PrivacyNotice::factory()->published()->create(['version' => '2026.01']);
        PreRegistration::factory()->for($notice)->create([
            'document' => '11222333000181',
            'document_hash' => hash_hmac('sha256', '11222333000181', (string) config('prelaunch.data_pepper')),
        ]);
        $this->mock(TurnstileVerifier::class)->shouldReceive('verify')->once()->andReturnTrue();

        $response = $this->post(route('pre-registration.store'), $this->validPayload($notice));

        $response->assertRedirect(route('pre-registration.success'));
        $this->assertDatabaseCount('pre_registrations', 1);
        $this->assertDatabaseCount('email_outbox_messages', 0);
        Queue::assertNothingPushed();
    }

    public function test_honeypot_returns_neutral_response_without_persisting_or_verifying_captcha(): void
    {
        Queue::fake();
        $notice = PrivacyNotice::factory()->published()->create(['version' => '2026.01']);
        $this->mock(TurnstileVerifier::class)->shouldNotReceive('verify');
        $payload = [...$this->validPayload($notice), 'company_website' => 'https://bot.invalid'];

        $response = $this->post(route('pre-registration.store'), $payload);

        $response->assertRedirect(route('pre-registration.success'));
        $this->assertDatabaseCount('pre_registrations', 0);
        $this->assertDatabaseCount('email_outbox_messages', 0);
        Queue::assertNothingPushed();
    }

    public function test_rejects_invalid_cnpj_before_persisting(): void
    {
        $notice = PrivacyNotice::factory()->published()->create(['version' => '2026.01']);
        $payload = [...$this->validPayload($notice), 'document' => '11.222.333/0001-80'];

        $response = $this->post(route('pre-registration.store'), $payload);

        $response->assertInvalid(['document' => 'Informe um CPF ou CNPJ válido']);
        $this->assertDatabaseCount('pre_registrations', 0);
    }

    public function test_rejects_failed_turnstile_without_persisting(): void
    {
        $notice = PrivacyNotice::factory()->published()->create(['version' => '2026.01']);
        $this->mock(TurnstileVerifier::class)->shouldReceive('verify')->once()->andReturnFalse();

        $response = $this->post(route('pre-registration.store'), $this->validPayload($notice));

        $response->assertInvalid(['cf-turnstile-response' => 'Não foi possível validar']);
        $this->assertDatabaseCount('pre_registrations', 0);
    }

    public function test_draft_notice_fails_closed_before_turnstile_or_persistence(): void
    {
        Queue::fake();
        $notice = PrivacyNotice::factory()->create(['version' => 'draft-2026.01']);
        $this->mock(TurnstileVerifier::class)->shouldNotReceive('verify');

        $response = $this->post(route('pre-registration.store'), $this->validPayload($notice));

        $response->assertInvalid(['privacy_accepted' => 'O aviso de privacidade foi atualizado']);
        $this->assertDatabaseCount('pre_registrations', 0);
        $this->assertDatabaseCount('email_outbox_messages', 0);
        Queue::assertNothingPushed();
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
