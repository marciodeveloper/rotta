<?php

namespace App\Services;

use App\Models\EmailConfirmationToken;
use App\Models\PreRegistration;
use App\Models\PreRegistrationStatusHistory;
use App\Models\PrivacyNotice;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreatePreRegistration
{
    public function __construct(
        private DocumentValidator $documentValidator,
        private SensitiveHash $sensitiveHash,
    ) {}

    /** @param array<string, mixed> $data
     * @return array{registration: PreRegistration, token: string}|null
     */
    public function handle(array $data, PrivacyNotice $privacyNotice): ?array
    {
        $document = $this->documentValidator->normalize((string) $data['document']);
        $email = Str::lower(trim((string) $data['email']));
        $phone = preg_replace('/\D+/', '', (string) $data['phone']) ?? '';
        $documentHash = $this->sensitiveHash->make($document);
        $emailHash = $this->sensitiveHash->make($email);

        if (PreRegistration::query()->where('document_hash', $documentHash)->orWhere('email_hash', $emailHash)->exists()) {
            return null;
        }

        try {
            return DB::transaction(function () use ($data, $privacyNotice, $document, $email, $phone, $documentHash, $emailHash): array {
                $registration = PreRegistration::query()->create([
                    ...Arr::only($data, [
                        'applicant_type', 'business_name', 'category', 'short_description', 'responsible_name',
                        'website', 'instagram', 'address_mode', 'postal_code', 'street', 'street_number',
                        'complement', 'neighborhood', 'city', 'state', 'service_area', 'google_place_id',
                        'latitude', 'longitude', 'marketing_opt_in',
                    ]),
                    'document' => $document,
                    'document_hash' => $documentHash,
                    'document_last_four' => substr($document, -4),
                    'email' => $email,
                    'email_hash' => $emailHash,
                    'phone' => $phone,
                    'phone_hash' => $this->sensitiveHash->make($phone),
                    'state' => Str::upper((string) $data['state']),
                    'privacy_notice_id' => $privacyNotice->id,
                    'privacy_notice_version' => $privacyNotice->version,
                    'status' => PreRegistration::STATUS_PENDING_EMAIL,
                    'source' => 'prelaunch_web',
                ]);

                $plainToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
                EmailConfirmationToken::query()->create([
                    'pre_registration_id' => $registration->id,
                    'token_hash' => hash('sha256', $plainToken),
                    'expires_at' => now()->addMinutes((int) config('prelaunch.confirmation_ttl_minutes')),
                ]);
                PreRegistrationStatusHistory::query()->create([
                    'pre_registration_id' => $registration->id,
                    'from_status' => null,
                    'to_status' => PreRegistration::STATUS_PENDING_EMAIL,
                ]);

                return ['registration' => $registration, 'token' => $plainToken];
            }, 3);
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }
}
