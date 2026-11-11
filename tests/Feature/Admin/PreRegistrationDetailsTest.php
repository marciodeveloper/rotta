<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\PreRegistration;
use App\Models\PreRegistrationMedia;
use App\Models\PreRegistrationStatusHistory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PreRegistrationDetailsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_details_require_active_admin_and_missing_registration_is_not_found(): void
    {
        $registration = PreRegistration::factory()->create();
        $url = route('admin.pre-registrations.show', $registration);
        $this->get($url)->assertRedirect(route('admin.login'));
        $this->actingAs(Admin::factory()->create(['is_active' => false]), 'admin')->get($url)->assertRedirect(route('admin.login'));
        $this->actingAs(Admin::factory()->create(), 'admin')->get(route('admin.pre-registrations.show', Str::uuid()))->assertNotFound();
    }

    public function test_details_show_decrypted_fields_history_and_preserve_list_context_without_secrets(): void
    {
        $admin = Admin::factory()->create();
        $registration = PreRegistration::factory()->create(['business_name' => '<script>alert(1)</script>', 'responsible_name' => 'Responsável sintético', 'street' => 'Rua sintética', 'address_mode' => 'public_address']);
        PreRegistrationStatusHistory::factory()->create(['pre_registration_id' => $registration->id, 'admin_id' => $admin->id, 'from_status' => 'under_review', 'to_status' => 'rejected', 'reason' => 'Justificativa sintética de análise.']);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.pre-registrations.show', ['preRegistration' => $registration, 'q' => 'Loja', 'sort' => 'business_name', 'direction' => 'asc', 'page' => 3, 'per_page' => 10]));
        $response->assertOk()->assertSee('Responsável sintético')->assertSee('Rua sintética')->assertSee('Justificativa sintética de análise.')
            ->assertSee('Não há data separada de aceite armazenada.')->assertSee('page=3')->assertSee('per_page=10')
            ->assertSee('<script>alert(1)</script>')->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee($registration->document_hash)->assertDontSee($registration->document)->assertDontSee('token_hash');
    }

    public function test_media_is_private_scoped_and_streamed_without_storage_paths(): void
    {
        Storage::fake('prelaunch_media');
        $registration = PreRegistration::factory()->create();
        $other = PreRegistration::factory()->create();
        $bytes = UploadedFile::fake()->image('synthetic.png', 16, 16)->get();
        Storage::disk('prelaunch_media')->put('synthetic/private.png', $bytes);
        $media = PreRegistrationMedia::query()->create([
            'pre_registration_id' => $registration->id, 'type' => 'logo', 'position' => 0, 'alt_text' => 'Logo sintética',
            'checksum' => hash('sha256', $bytes), 'mime_type' => 'image/png', 'extension' => 'png', 'size_bytes' => strlen($bytes),
            'width' => 16, 'height' => 16, 'status' => 'pending_review', 'storage_disk' => 'prelaunch_media',
            'storage_key' => 'synthetic/private.png', 'thumbnail_storage_key' => 'synthetic/thumb.png', 'rights_confirmed_at' => now(),
        ]);
        $url = route('admin.media.show', ['preRegistration' => $registration, 'media' => $media->id]);
        $this->get($url)->assertRedirect(route('admin.login'));
        $this->actingAs(Admin::factory()->create(['is_active' => false]), 'admin')->get($url)->assertRedirect(route('admin.login'));
        $this->actingAs(Admin::factory()->create(), 'admin')->get($url)->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        $response = $this->get($url);
        $this->assertSame($bytes, $response->streamedContent());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->get(route('admin.pre-registrations.show', $registration))->assertOk()->assertSee('Logo sintética')->assertDontSee('synthetic/private.png');
        $this->get(route('admin.media.show', ['preRegistration' => $other, 'media' => $media->id]))->assertNotFound();
        $this->get(route('admin.media.show', ['preRegistration' => $registration, 'media' => Str::uuid()]))->assertNotFound();
        Storage::disk('prelaunch_media')->delete('synthetic/private.png');
        $this->get($url)->assertNotFound();
    }

    public function test_status_update_from_detail_preserves_context_and_existing_state_rules(): void
    {
        $registration = PreRegistration::factory()->create(['status' => 'under_review', 'email_confirmed_at' => now(), 'status_version' => 1]);
        $detail = route('admin.pre-registrations.show', ['preRegistration' => $registration, 'state' => 'RJ', 'page' => 2]);
        $this->actingAs(Admin::factory()->create(), 'admin')->from($detail)
            ->patch(route('admin.status.update', $registration), ['status' => 'archived', 'status_version' => 1, 'reason' => 'Encerramento sintético autorizado.'])
            ->assertRedirect($detail);
        $this->assertDatabaseHas('pre_registrations', ['id' => $registration->id, 'status' => 'archived', 'status_version' => 2]);
    }
}
