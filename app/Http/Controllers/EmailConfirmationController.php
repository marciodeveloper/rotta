<?php

namespace App\Http\Controllers;

use App\Models\EmailConfirmationToken;
use App\Models\PreRegistration;
use App\Models\PreRegistrationStatusHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EmailConfirmationController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(string $token): View
    {
        $confirmed = DB::transaction(function () use ($token): bool {
            $confirmationToken = EmailConfirmationToken::query()
                ->where('token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->first();

            if ($confirmationToken === null || ! $confirmationToken->is_active || $confirmationToken->used_at !== null || $confirmationToken->expires_at->isPast()) {
                return false;
            }

            $registration = PreRegistration::query()->lockForUpdate()->findOrFail($confirmationToken->pre_registration_id);
            if ($registration->status !== PreRegistration::STATUS_PENDING_EMAIL) {
                return false;
            }

            $confirmationToken->update(['used_at' => now(), 'is_active' => false]);

            if ($registration->email_confirmed_at === null) {
                $fromStatus = $registration->status;
                $registration->update([
                    'email_confirmed_at' => now(),
                    'status' => PreRegistration::STATUS_UNDER_REVIEW,
                    'status_version' => $registration->status_version + 1,
                ]);
                PreRegistrationStatusHistory::query()->create([
                    'pre_registration_id' => $registration->id,
                    'from_status' => $fromStatus,
                    'to_status' => PreRegistration::STATUS_UNDER_REVIEW,
                ]);
            }

            return true;
        }, 3);

        return view('pre-registration.confirmed', ['confirmed' => $confirmed]);
    }
}
