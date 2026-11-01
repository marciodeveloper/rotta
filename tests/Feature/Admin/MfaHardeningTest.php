<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Services\AdminRecoveryCodeService;
use App\Services\AdminSecondFactorAuthenticator;
use App\Services\TotpService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class MfaHardeningTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_totp_time_step_cannot_be_replayed(): void
    {
        $totp = app(TotpService::class);
        $admin = Admin::factory()->create(['totp_secret' => $totp->generateSecret()]);
        $code = $totp->codeAt($admin->totp_secret, intdiv(now()->timestamp, 30));

        $this->assertTrue(app(AdminSecondFactorAuthenticator::class)->verify($admin, $code));
        $this->assertFalse(app(AdminSecondFactorAuthenticator::class)->verify($admin, $code));
    }

    public function test_recovery_code_is_single_use(): void
    {
        $admin = Admin::factory()->create();
        $code = app(AdminRecoveryCodeService::class)->generate($admin, 1)[0];

        $this->assertTrue(app(AdminSecondFactorAuthenticator::class)->verify($admin, $code));
        $this->assertFalse(app(AdminSecondFactorAuthenticator::class)->verify($admin, $code));
    }

    public function test_admin_reauthentication_requires_password_and_fresh_second_factor(): void
    {
        $totp = app(TotpService::class);
        $admin = Admin::factory()->create(['totp_secret' => $totp->generateSecret()]);
        $code = $totp->codeAt($admin->totp_secret, intdiv(now()->timestamp, 30));

        $response = $this->actingAs($admin, 'admin')->post(route('admin.reauthenticate.store'), [
            'password' => 'Testing-only-password-2026!', 'code' => $code,
        ]);

        $response->assertRedirect(route('admin.dashboard'))->assertSessionHas('admin_mfa_verified_at');
        $this->assertDatabaseHas('admin_audit_events', ['admin_id' => $admin->id, 'action' => 'admin.reauthenticated']);
    }
}
