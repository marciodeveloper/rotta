<?php

namespace Tests\Feature;

use App\Models\MediaStorageUsage;
use App\Services\MediaStorageCapacity;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MediaStorageCapacityTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_records_physical_usage_after_a_private_write(): void
    {
        Storage::fake('prelaunch_media');
        $this->configureCapacity();

        app(MediaStorageCapacity::class)->store(20, function (): void {
            Storage::disk('prelaunch_media')->put('registration/media/original.webp', str_repeat('a', 20));
        });

        $usage = MediaStorageUsage::query()->findOrFail(MediaStorageUsage::SINGLETON_ID);
        $this->assertSame(20, $usage->used_bytes);
        $this->assertSame('normal', $usage->level);
    }

    public function test_reports_alert_at_seventy_percent_without_blocking(): void
    {
        Storage::fake('prelaunch_media');
        $this->configureCapacity();
        Storage::disk('prelaunch_media')->put('existing.webp', str_repeat('a', 70));

        $snapshot = app(MediaStorageCapacity::class)->inspect();

        $this->assertSame(70, $snapshot['used_bytes']);
        $this->assertSame('alert', $snapshot['level']);
        $this->assertDatabaseHas('media_storage_usages', ['id' => 1, 'used_bytes' => 70, 'level' => 'alert']);
    }

    public function test_blocks_a_write_that_reaches_eighty_percent(): void
    {
        Storage::fake('prelaunch_media');
        $this->configureCapacity();
        $called = false;

        try {
            app(MediaStorageCapacity::class)->store(80, function () use (&$called): void {
                $called = true;
            });
            $this->fail('Expected capacity validation to fail.');
        } catch (ValidationException $exception) {
            $this->assertSame('O envio de imagens está temporariamente indisponível por limite de armazenamento.', $exception->errors()['media'][0]);
        }

        $this->assertFalse($called);
        Storage::disk('prelaunch_media')->assertDirectoryEmpty('/');
    }

    public function test_blocks_a_write_below_the_host_free_space_floor(): void
    {
        Storage::fake('prelaunch_media');
        $this->configureCapacity(minimumFreeBytes: PHP_INT_MAX);

        $this->expectException(ValidationException::class);
        app(MediaStorageCapacity::class)->store(1, fn (): null => null);
    }

    private function configureCapacity(int $minimumFreeBytes = 0): void
    {
        config([
            'prelaunch.media_disk' => 'prelaunch_media',
            'prelaunch.media.global_quota_bytes' => 100,
            'prelaunch.media.alert_percent' => 70,
            'prelaunch.media.block_percent' => 80,
            'prelaunch.media.minimum_free_bytes' => $minimumFreeBytes,
        ]);
    }
}
