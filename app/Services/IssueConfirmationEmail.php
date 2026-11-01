<?php

namespace App\Services;

use App\Jobs\DeliverConfirmationEmail;
use App\Models\EmailConfirmationToken;
use App\Models\EmailOutboxMessage;
use App\Models\PreRegistration;
use Illuminate\Support\Facades\DB;

class IssueConfirmationEmail
{
    public function handle(PreRegistration $registration): EmailOutboxMessage
    {
        EmailConfirmationToken::query()
            ->whereBelongsTo($registration)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $plainToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $confirmationToken = EmailConfirmationToken::query()->create([
            'pre_registration_id' => $registration->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addMinutes((int) config('prelaunch.confirmation_ttl_minutes')),
            'is_active' => true,
        ]);
        $outbox = EmailOutboxMessage::query()->create([
            'pre_registration_id' => $registration->id,
            'email_confirmation_token_id' => $confirmationToken->id,
            'confirmation_token' => $plainToken,
            'status' => EmailOutboxMessage::STATUS_PENDING,
            'available_at' => now(),
        ]);

        DB::afterCommit(fn () => DeliverConfirmationEmail::dispatch($outbox->id));

        return $outbox;
    }
}
