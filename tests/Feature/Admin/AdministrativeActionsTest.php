<?php

namespace Tests\Feature\Admin;

use App\Jobs\DeliverConfirmationEmail;
use App\Models\Admin;
use App\Models\AdminAuditEvent;
use App\Models\EmailConfirmationToken;
use App\Models\EmailOutboxMessage;
use App\Models\PreRegistration;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdministrativeActionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public static function actionStates(): array
    {
        return [
            'pending' => ['pending_email_confirmation', [], true],
            'review' => ['under_review', ['approved', 'rejected', 'archived'], false],
            'approved' => ['approved', ['archived'], false],
            'rejected' => ['rejected', ['under_review', 'archived'], false],
            'archived' => ['archived', ['under_review'], false],
        ];
    }

    #[DataProvider('actionStates')]
    public function test_table_and_detail_offer_only_contextual_actions(string $status, array $targets, bool $resend): void
    {
        $registration = PreRegistration::factory()->create(['status' => $status, 'email_confirmed_at' => $resend ? null : now()]);
        $this->actingAs(Admin::factory()->create(), 'admin');
        foreach ([route('admin.dashboard'), route('admin.pre-registrations.show', $registration)] as $url) {
            $response = $this->get($url)->assertOk();
            foreach (['approved', 'rejected', 'archived', 'under_review'] as $target) {
                if (in_array($target, $targets, true)) {
                    $response->assertSee('data-target-status="'.$target.'"', false);
                } else {
                    $response->assertDontSee('data-target-status="'.$target.'"', false);
                }
            }
            if ($resend) {
                $response->assertSee('data-crm-action="resend"', false);
            } else {
                $response->assertDontSee('data-crm-action="resend"', false);
            }
        }
    }

    public static function allowedTransitions(): array
    {
        return [
            ['under_review', 'approved'], ['under_review', 'rejected'], ['under_review', 'archived'],
            ['approved', 'archived'], ['rejected', 'under_review'], ['rejected', 'archived'], ['archived', 'under_review'],
        ];
    }

    #[DataProvider('allowedTransitions')]
    public function test_json_actions_preserve_machine_history_and_audit(string $from, string $to): void
    {
        $registration = PreRegistration::factory()->create(['status' => $from, 'email_confirmed_at' => now()]);
        $admin = Admin::factory()->create();
        $url = route('admin.status.update', $registration);
        $payload = ['status' => $to, 'status_version' => 1, 'reason' => 'Justificativa sintética autorizada.'];
        $this->actingAs($admin, 'admin')->patchJson($url, $payload)->assertOk()->assertJsonPath('message', 'Status atualizado com sucesso.');
        $this->assertDatabaseHas('pre_registrations', ['id' => $registration->id, 'status' => $to, 'status_version' => 2]);
        $this->assertDatabaseHas('pre_registration_status_histories', ['pre_registration_id' => $registration->id, 'from_status' => $from, 'to_status' => $to, 'admin_id' => $admin->id]);
        $this->assertDatabaseHas('admin_audit_events', ['target_id' => $registration->id, 'action' => 'pre_registration.status_updated']);
        $this->patchJson($url, $payload)->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertDatabaseCount('pre_registration_status_histories', 1);
    }

    public function test_justifications_and_email_confirmation_remain_mandatory(): void
    {
        $registration = PreRegistration::factory()->create(['status' => 'under_review', 'email_confirmed_at' => null]);
        $this->actingAs(Admin::factory()->create(), 'admin');
        foreach (['rejected', 'archived'] as $target) {
            $this->patchJson(route('admin.status.update', $registration), ['status' => $target, 'status_version' => 1])
                ->assertUnprocessable()->assertJsonValidationErrors('reason');
        }
        $this->patchJson(route('admin.status.update', $registration), ['status' => 'approved', 'status_version' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertDatabaseCount('pre_registration_status_histories', 0);
    }

    public function test_actions_enforce_authentication_active_admin_and_gate_on_backend(): void
    {
        Queue::fake([DeliverConfirmationEmail::class]);
        $registration = PreRegistration::factory()->create();
        $statusUrl = route('admin.status.update', $registration);
        $resendUrl = route('admin.confirmation.resend', $registration);
        $this->postJson($resendUrl, ['status_version' => 1])->assertUnauthorized();
        $this->patchJson($statusUrl, ['status' => 'approved', 'status_version' => 1])->assertUnauthorized();
        $this->actingAs(Admin::factory()->create(['is_active' => false]), 'admin')->post($resendUrl, ['status_version' => 1])->assertRedirect(route('admin.login'));
        Gate::define('manage-pre-registration', fn (): bool => false);
        $this->actingAs(Admin::factory()->create(), 'admin')->postJson($resendUrl, ['status_version' => 1])->assertForbidden();
        $this->patchJson($statusUrl, ['status' => 'approved', 'status_version' => 1])->assertForbidden();
        $this->assertDatabaseCount('email_outbox_messages', 0);
        Queue::assertNothingPushed();
    }

    public function test_admin_resend_rotates_token_throttles_and_audits_without_leaking_token(): void
    {
        $this->freezeTime();
        Queue::fake([DeliverConfirmationEmail::class]);
        $registration = PreRegistration::factory()->create();
        $admin = Admin::factory()->create();
        $url = route('admin.confirmation.resend', $registration);
        $response = $this->actingAs($admin, 'admin')->postJson($url, ['status_version' => 1])->assertOk();
        $response->assertDontSee('confirmation_token')->assertDontSee('token_hash');
        $this->assertDatabaseCount('email_outbox_messages', 1);
        $this->assertDatabaseHas('admin_audit_events', ['admin_id' => $admin->id, 'target_id' => $registration->id, 'action' => 'pre_registration.confirmation_resent']);
        $this->postJson($url, ['status_version' => 1])->assertTooManyRequests();
        $this->actingAs(Admin::factory()->create(), 'admin')->postJson($url, ['status_version' => 1])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->travel(61)->seconds();
        $this->postJson($url, ['status_version' => 1])->assertOk();
        $this->assertSame(1, $registration->confirmationTokens()->where('is_active', true)->count());
        $this->assertDatabaseCount('email_outbox_messages', 2);
        $this->assertSame('pending_email_confirmation', $registration->fresh()->status);
        $this->artisan('prelaunch:email-outbox:dispatch')->assertSuccessful();
        Queue::assertPushed(DeliverConfirmationEmail::class);
    }

    public function test_resend_rejects_stale_version_confirmed_email_and_nonpending_status(): void
    {
        Queue::fake([DeliverConfirmationEmail::class]);
        $this->actingAs(Admin::factory()->create(), 'admin');
        foreach ([['status_version' => 2], ['email_confirmed_at' => now()], ['status' => 'under_review']] as $attributes) {
            $registration = PreRegistration::factory()->create($attributes);
            $this->postJson(route('admin.confirmation.resend', $registration), ['status_version' => 1])
                ->assertUnprocessable()->assertJsonValidationErrors('status');
        }
        $this->assertDatabaseCount('email_outbox_messages', 0);
        Queue::assertNothingPushed();
    }

    public function test_hourly_resend_limit_is_persisted_independently_of_cache(): void
    {
        Queue::fake([DeliverConfirmationEmail::class]);
        $registration = PreRegistration::factory()->create();
        $admin = Admin::factory()->create();
        for ($index = 0; $index < 3; $index++) {
            AdminAuditEvent::query()->create(['admin_id' => $admin->id, 'action' => 'pre_registration.confirmation_resent', 'target_type' => PreRegistration::class, 'target_id' => $registration->id, 'created_at' => now()->subMinutes(2 + $index)]);
        }
        $this->actingAs($admin, 'admin')->postJson(route('admin.confirmation.resend', $registration), ['status_version' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertDatabaseCount('email_outbox_messages', 0);
        Queue::assertNothingPushed();
    }

    public function test_resend_does_not_invalidate_a_confirmation_being_delivered(): void
    {
        Queue::fake([DeliverConfirmationEmail::class]);
        $registration = PreRegistration::factory()->create();
        $token = EmailConfirmationToken::factory()->for($registration)->create();
        EmailOutboxMessage::query()->create([
            'pre_registration_id' => $registration->id, 'email_confirmation_token_id' => $token->id,
            'confirmation_token' => str_repeat('x', 43), 'status' => 'processing', 'available_at' => now(), 'claimed_at' => now(),
        ]);
        $this->actingAs(Admin::factory()->create(), 'admin')->postJson(route('admin.confirmation.resend', $registration), ['status_version' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertTrue($token->fresh()->is_active);
        $this->assertDatabaseCount('email_outbox_messages', 1);
        Queue::assertNothingPushed();
    }
}
