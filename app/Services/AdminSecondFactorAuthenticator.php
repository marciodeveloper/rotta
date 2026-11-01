<?php

namespace App\Services;

use App\Models\Admin;
use Illuminate\Support\Facades\DB;

class AdminSecondFactorAuthenticator
{
    public function __construct(private TotpService $totp, private AdminRecoveryCodeService $recoveryCodes) {}

    public function verify(Admin $admin, string $code): bool
    {
        if (! preg_match('/^\d{6}$/', $code)) {
            return $this->recoveryCodes->consume($admin, $code);
        }

        return DB::transaction(function () use ($admin, $code): bool {
            $locked = Admin::query()->lockForUpdate()->find($admin->id);
            if ($locked === null || ! $locked->is_active) {
                return false;
            }
            $step = $this->totp->matchingTimeStep($locked->totp_secret, $code);
            if ($step === null || ($locked->last_totp_time_step !== null && $step <= $locked->last_totp_time_step)) {
                return false;
            }
            $locked->update(['last_totp_time_step' => $step]);

            return true;
        }, 3);
    }
}
