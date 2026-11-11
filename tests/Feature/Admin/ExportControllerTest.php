<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\PreRegistration;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ExportControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_without_export_permission_is_forbidden(): void
    {
        $admin = Admin::factory()->create(['can_export' => false]);

        $response = $this->actingAs($admin, 'admin')
            ->withSession(['admin_password_confirmed_at' => now()->timestamp])
            ->post(route('admin.export'), ['reason' => 'Revisão operacional autorizada do lote.']);

        $response->assertForbidden();
        $this->assertDatabaseCount('admin_audit_events', 0);
    }

    public function test_export_permission_without_recent_password_requires_confirmation(): void
    {
        $admin = Admin::factory()->create(['can_export' => true]);

        $response = $this->actingAs($admin, 'admin')
            ->withSession(['admin_password_confirmed_at' => now()->subMinutes(16)->timestamp])
            ->post(route('admin.export'), ['reason' => 'Revisão operacional autorizada do lote.']);

        $response->assertRedirect(route('admin.reauthenticate'));
        $this->assertDatabaseCount('admin_audit_events', 0);
    }

    public function test_get_export_route_is_not_allowed(): void
    {
        $admin = Admin::factory()->create(['can_export' => true]);

        $this->actingAs($admin, 'admin')->get('/admin/exportar')->assertMethodNotAllowed();
    }

    public function test_export_respects_text_search_and_other_filters_across_pages(): void
    {
        $admin = Admin::factory()->create(['can_export' => true]);
        $match = PreRegistration::factory()->create(['business_name' => 'Oficina Alvo', 'state' => 'RJ']);
        $other = PreRegistration::factory()->create(['business_name' => 'Outro negócio', 'state' => 'RJ']);
        $outside = PreRegistration::factory()->create(['business_name' => 'Oficina Alvo', 'state' => 'SP']);
        $response = $this->actingAs($admin, 'admin')->withSession(['admin_password_confirmed_at' => now()->timestamp])
            ->post(route('admin.export'), ['q' => 'Oficina', 'state' => 'RJ', 'reason' => 'Conferência sintética dos resultados filtrados.']);
        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString($match->id, $content);
        $this->assertStringNotContainsString($other->id, $content);
        $this->assertStringNotContainsString($outside->id, $content);
    }

    public function test_authorized_export_minimizes_fields_sanitizes_control_prefixed_formula_and_audits_result(): void
    {
        $admin = Admin::factory()->create(['can_export' => true]);
        $registration = PreRegistration::factory()->create([
            'business_name' => "\t =HYPERLINK(\"https://invalid.test\")",
            'document' => '11222333000181',
            'document_last_four' => '0181',
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->withSession(['admin_password_confirmed_at' => now()->timestamp])
            ->post(route('admin.export'), ['reason' => 'Revisão operacional autorizada do lote.']);

        $response->assertOk()->assertHeader('X-ROTTA-Contract-Version', '1.1');
        $content = $response->streamedContent();
        $this->assertStringContainsString('contract_version,source_id,applicant_type,document_last_four', $content);
        $this->assertStringContainsString("'\t =HYPERLINK", $content);
        $this->assertStringNotContainsString('11222333000181', $content);
        $this->assertStringNotContainsString('short_description', $content);
        $this->assertStringContainsString($registration->id, $content);
        $this->assertDatabaseHas('admin_audit_events', ['admin_id' => $admin->id, 'action' => 'pre_registration.export_requested']);
        $this->assertDatabaseHas('admin_audit_events', ['admin_id' => $admin->id, 'action' => 'pre_registration.export_completed']);
    }
}
