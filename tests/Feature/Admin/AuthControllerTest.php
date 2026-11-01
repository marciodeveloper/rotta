<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\AdminAuditEvent;
use App\Services\TotpService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_valid_password_requires_mfa_before_authentication(): void
    {
        $admin = Admin::factory()->create(['email' => 'admin@example.test', 'password' => Hash::make('Testing-only-password-2026!')]);

        $response = $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'Testing-only-password-2026!']);

        $response->assertRedirect(route('admin.mfa.create'))->assertSessionHas('admin_mfa_pending_id', $admin->id);
        $this->assertGuest('admin');
    }

    public function test_valid_mfa_authenticates_pending_active_admin_and_audits_login(): void
    {
        $this->travelTo('2026-11-01 15:00:00');
        $admin = Admin::factory()->create(['totp_secret' => 'JBSWY3DPEHPK3PXP']);
        $code = (new TotpService)->codeAt($admin->totp_secret, intdiv(now()->timestamp, 30));

        $response = $this->withSession(['admin_mfa_pending_id' => $admin->id])->post(route('admin.mfa.store'), ['code' => $code]);

        $response->assertRedirect(route('admin.dashboard'));
        $response->assertSessionHas('admin_mfa_verified_at', now()->timestamp);
        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertDatabaseHas(AdminAuditEvent::class, ['admin_id' => $admin->id, 'action' => 'admin.login']);
    }
}
