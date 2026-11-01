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

    public function test_confirmed_registration_can_move_from_review_to_approved_with_history_and_audit(): void
    {
        $admin = Admin::factory()->create();
        $registration = PreRegistration::factory()->create([
            'status' => PreRegistration::STATUS_UNDER_REVIEW,
            'email_confirmed_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'admin')->patch(route('admin.status.update', $registration), [
            'status' => PreRegistration::STATUS_APPROVED,
            'status_version' => 1,
        ]);

        $response->assertRedirect();
        $this->assertSame(PreRegistration::STATUS_APPROVED, $registration->fresh()->status);
        $this->assertSame(2, $registration->fresh()->status_version);
        $this->assertDatabaseHas('pre_registration_status_histories', [
            'pre_registration_id' => $registration->id,
            'to_status' => PreRegistration::STATUS_APPROVED,
            'admin_id' => $admin->id,
        ]);
        $this->assertDatabaseHas(AdminAuditEvent::class, ['target_id' => $registration->id, 'action' => 'pre_registration.status_updated']);
    }

    public function test_pending_email_registration_cannot_be_approved(): void
    {
        $admin = Admin::factory()->create();
        $registration = PreRegistration::factory()->create();

        $response = $this->actingAs($admin, 'admin')->patch(route('admin.status.update', $registration), [
            'status' => PreRegistration::STATUS_APPROVED,
            'status_version' => 1,
        ]);

        $response->assertInvalid(['status' => 'Esta transição de status não é permitida']);
        $this->assertSame(PreRegistration::STATUS_PENDING_EMAIL, $registration->fresh()->status);
    }

    public function test_rejection_requires_a_justification(): void
    {
        $admin = Admin::factory()->create();
        $registration = PreRegistration::factory()->create([
            'status' => PreRegistration::STATUS_UNDER_REVIEW,
            'email_confirmed_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'admin')->patch(route('admin.status.update', $registration), [
            'status' => PreRegistration::STATUS_REJECTED,
            'status_version' => 1,
        ]);

        $response->assertInvalid(['reason']);
        $this->assertSame(PreRegistration::STATUS_UNDER_REVIEW, $registration->fresh()->status);
    }

    public function test_stale_status_version_rejects_a_concurrent_update(): void
    {
        $admin = Admin::factory()->create();
        $registration = PreRegistration::factory()->create([
            'status' => PreRegistration::STATUS_UNDER_REVIEW,
            'status_version' => 2,
            'email_confirmed_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'admin')->patch(route('admin.status.update', $registration), [
            'status' => PreRegistration::STATUS_APPROVED,
            'status_version' => 1,
        ]);

        $response->assertInvalid(['status' => 'O cadastro foi atualizado por outra operação']);
        $this->assertSame(PreRegistration::STATUS_UNDER_REVIEW, $registration->fresh()->status);
        $this->assertDatabaseCount('pre_registration_status_histories', 0);
    }
}
