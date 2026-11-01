<?php

namespace App\Services;

use App\Models\PreRegistration;
use App\Models\PreRegistrationStatusHistory;
use App\Models\PrivacyNotice;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class CreatePreRegistration
{
    public function __construct(
        private DocumentValidator $documentValidator,
        private SensitiveHash $sensitiveHash,
        private IssueConfirmationEmail $issueConfirmationEmail,
        private PersistPreRegistrationMedia $persistMedia,
    ) {}

    /** @param array<string, mixed> $data
     */
    public function handle(array $data, PrivacyNotice $privacyNotice): ?PreRegistration
    {
        $document = $this->documentValidator->normalize((string) $data['document']);
        $email = Str::lower(trim((string) $data['email']));
        $phone = preg_replace('/\D+/', '', (string) $data['phone']) ?? '';
        $documentHash = $this->sensitiveHash->make($document);
        $emailHash = $this->sensitiveHash->make($email);
        $documentHashV2 = $this->sensitiveHash->makeFor('document', $document);
        $emailHashV2 = $this->sensitiveHash->makeFor('email', $email);

        if (PreRegistration::query()->where(fn ($query) => $query->where('document_hash', $documentHash)->orWhere('document_hash_v2', $documentHashV2))->orWhere(fn ($query) => $query->where('email_hash', $emailHash)->orWhere('email_hash_v2', $emailHashV2))->exists()) {
            return null;
        }

        $storedKeys = [];
        try {
            return DB::transaction(function () use ($data, $privacyNotice, $document, $email, $phone, $documentHash, $emailHash, $documentHashV2, $emailHashV2, &$storedKeys): PreRegistration {
                $registration = PreRegistration::query()->create([
                    ...Arr::only($data, [
                        'applicant_type', 'business_name', 'category', 'short_description', 'responsible_name',
                        'website', 'instagram', 'address_mode', 'postal_code', 'street', 'street_number',
                        'complement', 'neighborhood', 'city', 'state', 'service_area', 'google_place_id',
                        'latitude', 'longitude', 'marketing_opt_in',
                    ]),
                    'document' => $document,
                    'document_hash' => $documentHash,
                    'document_hash_v2' => $documentHashV2,
                    'document_last_four' => substr($document, -4),
                    'email' => $email,
                    'email_hash' => $emailHash,
                    'email_hash_v2' => $emailHashV2,
                    'phone' => $phone,
                    'phone_hash' => $this->sensitiveHash->make($phone),
                    'phone_hash_v2' => $this->sensitiveHash->makeFor('phone', $phone),
                    'state' => Str::upper((string) $data['state']),
                    'privacy_notice_id' => $privacyNotice->id,
                    'privacy_notice_version' => $privacyNotice->version,
                    'status' => PreRegistration::STATUS_PENDING_EMAIL,
                    'source' => 'prelaunch_web',
                ]);

                PreRegistrationStatusHistory::query()->create([
                    'pre_registration_id' => $registration->id,
                    'from_status' => null,
                    'to_status' => PreRegistration::STATUS_PENDING_EMAIL,
                ]);
                $this->issueConfirmationEmail->handle($registration);
                $this->persistMedia->handle($registration, $data, $storedKeys);

                return $registration;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            Storage::disk((string) config('prelaunch.media_disk'))->delete($storedKeys);

            return null;
        } catch (Throwable $exception) {
            Storage::disk((string) config('prelaunch.media_disk'))->delete($storedKeys);
            throw $exception;
        }
    }
}
