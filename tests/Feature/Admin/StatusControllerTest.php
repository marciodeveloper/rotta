<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\AdminAuditEvent;
use App\Models\PreRegistration;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class StatusControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_active_admin_updates_status_with_history_and_audit_event(): void
    {
        $admin = Admin::factory()->create();
        $registration = PreRegistration::factory()->create();

        $response = $this->actingAs($admin, 'admin')->patch(route('admin.status.update', $registration), [
            'status' => PreRegistration::STATUS_APPROVED, 'reason' => 'Dados conferidos em teste.',
        ]);

        $response->assertRedirect();
        $this->assertSame(PreRegistration::STATUS_APPROVED, $registration->fresh()->status);
        $this->assertDatabaseHas('pre_registration_status_histories', ['pre_registration_id' => $registration->id, 'to_status' => PreRegistration::STATUS_APPROVED, 'admin_id' => $admin->id]);
        $this->assertDatabaseHas(AdminAuditEvent::class, ['target_id' => $registration->id, 'action' => 'pre_registration.status_updated']);
    }
}
