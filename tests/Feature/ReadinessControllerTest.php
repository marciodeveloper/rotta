<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReadinessControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_reports_ready_without_exposing_dependency_details(): void
    {
        Storage::fake('prelaunch_media');
        config(['prelaunch.media_disk' => 'prelaunch_media']);
        Storage::disk('prelaunch_media')->put('.readiness-fixture', 'synthetic');

        $this->getJson('/ready')->assertOk()->assertExactJson(['status' => 'ready']);
    }

    public function test_fails_closed_when_private_media_storage_is_unavailable(): void
    {
        config([
            'prelaunch.media_disk' => 'unavailable_media',
            'filesystems.disks.unavailable_media' => [
                'driver' => 'local',
                'root' => '/proc/rotta-prelaunch-unavailable',
            ],
        ]);

        $this->getJson('/ready')->assertStatus(503)->assertExactJson(['status' => 'unavailable']);
    }
}
