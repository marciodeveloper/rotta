<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\PreRegistration;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ExportControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_controlled_export_uses_v1_contract_sanitizes_csv_and_records_audit(): void
    {
        $admin = Admin::factory()->create();
        $registration = PreRegistration::factory()->create(['business_name' => '=HYPERLINK("https://invalid.test")']);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.export'));

        $response->assertOk()->assertHeader('X-ROTTA-Contract-Version', '1.0');
        $content = $response->streamedContent();
        $this->assertStringContainsString('contract_version,source_id', $content);
        $this->assertStringContainsString("'=HYPERLINK", $content);
        $this->assertDatabaseHas('admin_audit_events', ['admin_id' => $admin->id, 'action' => 'pre_registration.exported']);
        $this->assertStringContainsString($registration->id, $content);
    }
}
