<?php

namespace Tests\Feature;

use App\Models\PreRegistration;
use App\Models\PreRegistrationMedia;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class BackupCommandsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_creates_and_verifies_a_consistent_database_and_media_batch(): void
    {
        Storage::fake('prelaunch_media');
        config([
            'prelaunch.media_disk' => 'prelaunch_media',
            'prelaunch.media.minimum_free_bytes' => 0,
        ]);
        $root = storage_path('framework/testing/backup-command-'.Str::uuid());
        $registration = PreRegistration::factory()->create();
        $bytes = 'synthetic-original-media';
        $thumbnail = 'synthetic-thumbnail';
        Storage::disk('prelaunch_media')->put('synthetic/original.webp', $bytes);
        Storage::disk('prelaunch_media')->put('synthetic/thumbnail.webp', $thumbnail);
        PreRegistrationMedia::query()->create([
            'pre_registration_id' => $registration->id,
            'type' => 'logo',
            'position' => 0,
            'alt_text' => 'Marca sintética',
            'checksum' => hash('sha256', $bytes),
            'mime_type' => 'image/webp',
            'extension' => 'webp',
            'size_bytes' => strlen($bytes),
            'width' => 10,
            'height' => 10,
            'status' => 'pending_review',
            'storage_disk' => 'prelaunch_media',
            'storage_key' => 'synthetic/original.webp',
            'thumbnail_storage_key' => 'synthetic/thumbnail.webp',
            'rights_confirmed_at' => now(),
        ]);

        try {
            $this->assertSame(0, Artisan::call('prelaunch:backup:create', ['--output' => $root]));
            $batches = File::directories($root);
            $this->assertCount(1, $batches);
            $batch = $batches[0];
            $this->assertFileExists("{$batch}/database.dump");
            $this->assertFileExists("{$batch}/media.tar.gz");
            $this->assertFileExists("{$batch}/media-manifest.json");
            $this->assertFileExists("{$batch}/SHA256SUMS");
            $this->assertSame(0, Artisan::call('prelaunch:backup:verify', ['batch' => $batch]));
        } finally {
            File::deleteDirectory($root);
        }
    }

    public function test_verification_fails_after_an_artifact_is_tampered(): void
    {
        $root = storage_path('framework/testing/backup-verify-'.Str::uuid());
        File::ensureDirectoryExists($root);
        foreach (['database.dump', 'media.tar.gz'] as $name) {
            File::put("{$root}/{$name}", 'synthetic');
        }
        File::put("{$root}/media-manifest.json", json_encode(['format' => 'rotta-prelaunch-backup-v1', 'media_count' => 0, 'media' => []], JSON_THROW_ON_ERROR));
        $checksums = collect(['database.dump', 'media.tar.gz', 'media-manifest.json'])
            ->map(fn (string $name): string => hash_file('sha256', "{$root}/{$name}")."  {$name}")
            ->implode("\n");
        File::put("{$root}/SHA256SUMS", $checksums."\n");
        File::put("{$root}/database.dump", 'tampered');

        try {
            $this->assertSame(1, Artisan::call('prelaunch:backup:verify', ['batch' => $root]));
        } finally {
            File::deleteDirectory($root);
        }
    }
}
