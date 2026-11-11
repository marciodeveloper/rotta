<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\AdminAuditEvent;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_valid_password_authenticates_existing_admin_and_audits_login(): void
    {
        $admin = Admin::factory()->create(['email' => 'admin@example.test', 'password' => Hash::make('Testing-only-password-2026!')]);
        $this->withSession(['before_login' => 'synthetic']);
        $previousSession = session()->getId();

        $response = $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'Testing-only-password-2026!']);

        $response->assertRedirect(route('admin.dashboard'))->assertSessionHas('admin_password_confirmed_at', now()->timestamp);
        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertNotSame($previousSession, session()->getId());
        $this->assertDatabaseHas(AdminAuditEvent::class, ['admin_id' => $admin->id, 'action' => 'admin.login']);
    }

    public function test_invalid_password_and_inactive_admin_cannot_login(): void
    {
        $admin = Admin::factory()->create();
        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'invalid'])->assertSessionHasErrors('email');
        $admin->update(['is_active' => false]);
        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'Testing-only-password-2026!'])->assertSessionHasErrors('email');
        $this->assertGuest('admin');
        $this->assertDatabaseCount('admin_audit_events', 0);
    }

    public function test_mfa_routes_are_unavailable_and_logout_invalidates_authentication(): void
    {
        $this->get('/admin/mfa')->assertNotFound();
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin')->post(route('admin.logout'))->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
    }

    public function test_provisioning_does_not_require_or_generate_second_factor(): void
    {
        $this->artisan('prelaunch:admin:create', ['email' => 'synthetic-admin@example.invalid', '--name' => 'Operador sintético'])
            ->expectsQuestion('Password (minimum 14 characters)', 'Testing-only-password-2026!')->assertSuccessful();
        $admin = Admin::query()->where('email', 'synthetic-admin@example.invalid')->firstOrFail();
        $this->assertSame('', $admin->totp_secret);
        $this->assertNull($admin->totp_confirmed_at);
        $this->assertFalse($admin->can_export);
        $this->assertDatabaseCount('admin_recovery_codes', 0);
    }

    public function test_password_login_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('admin.login.store'), ['email' => 'rate-limit@example.invalid', 'password' => 'invalid'])->assertSessionHasErrors('email');
        }
        $this->post(route('admin.login.store'), ['email' => 'rate-limit@example.invalid', 'password' => 'invalid'])->assertTooManyRequests();
    }
}
