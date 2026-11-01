<?php

namespace Tests\Feature;

use App\Models\PreRegistration;
use App\Services\ImportPreRegistrationMediaManifest;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class MediaImportContractTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_reimport_of_same_immutable_media_uuid_is_idempotent(): void
    {
        Storage::fake('prelaunch_media');
        $bytes = 'synthetic-media-bytes';
        Storage::disk('prelaunch_media')->put('synthetic/original.webp', $bytes);
        $registration = PreRegistration::factory()->create();
        $payload = [
            'contract_version' => '1.0', 'pre_registration_id' => $registration->id, 'media_id' => (string) Str::uuid(),
            'type' => 'logo', 'position' => 0, 'alt_text' => 'Marca sintética', 'checksum' => hash('sha256', $bytes),
            'mime_type' => 'image/webp', 'extension' => 'webp', 'size_bytes' => strlen($bytes), 'width' => 10, 'height' => 10,
            'status' => 'pending_review', 'storage_disk' => 'prelaunch_media', 'storage_key' => 'synthetic/original.webp',
            'thumbnail_storage_key' => 'synthetic/thumbnail.webp', 'rights_confirmed_at' => now()->toIso8601String(),
        ];

        $first = app(ImportPreRegistrationMediaManifest::class)->import($payload);
        $second = app(ImportPreRegistrationMediaManifest::class)->import($payload);

        $this->assertTrue($first->is($second));
        $this->assertDatabaseCount('pre_registration_media', 1);
    }

    public function test_import_rejects_media_with_invalid_checksum(): void
    {
        Storage::fake('prelaunch_media');
        Storage::disk('prelaunch_media')->put('synthetic/original.webp', 'tampered');
        $registration = PreRegistration::factory()->create();

        $this->expectException(RuntimeException::class);
        app(ImportPreRegistrationMediaManifest::class)->import([
            'contract_version' => '1.0', 'pre_registration_id' => $registration->id, 'media_id' => (string) Str::uuid(),
            'type' => 'logo', 'position' => 0, 'alt_text' => 'Marca sintética', 'checksum' => str_repeat('a', 64),
            'mime_type' => 'image/webp', 'extension' => 'webp', 'size_bytes' => 8, 'width' => 10, 'height' => 10,
            'status' => 'pending_review', 'storage_disk' => 'prelaunch_media', 'storage_key' => 'synthetic/original.webp',
            'thumbnail_storage_key' => 'synthetic/thumbnail.webp', 'rights_confirmed_at' => now()->toIso8601String(),
        ]);
    }
}
