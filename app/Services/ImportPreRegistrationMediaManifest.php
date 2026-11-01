<?php

namespace App\Services;

use App\Models\PreRegistration;
use App\Models\PreRegistrationMedia;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ImportPreRegistrationMediaManifest
{
    /** @param array<string, mixed> $payload */
    public function import(array $payload): PreRegistrationMedia
    {
        $disk = Storage::disk((string) $payload['storage_disk']);
        $storageKey = (string) $payload['storage_key'];
        if (! $disk->exists($storageKey)) {
            throw new RuntimeException('Media object is missing from durable storage.');
        }
        $bytes = $disk->get($storageKey);
        if (! hash_equals((string) $payload['checksum'], hash('sha256', $bytes)) || strlen($bytes) !== (int) $payload['size_bytes']) {
            throw new RuntimeException('Media object failed checksum or size verification.');
        }

        return DB::transaction(function () use ($payload): PreRegistrationMedia {
            $registration = PreRegistration::query()->findOrFail((string) $payload['pre_registration_id']);
            $immutable = Arr::only($payload, ['pre_registration_id', 'type', 'position', 'alt_text', 'checksum', 'mime_type', 'extension', 'size_bytes', 'width', 'height', 'status', 'storage_disk', 'storage_key', 'thumbnail_storage_key', 'rights_confirmed_at']);
            $existing = PreRegistrationMedia::query()->lockForUpdate()->find((string) $payload['media_id']);
            if ($existing !== null) {
                foreach (['pre_registration_id', 'checksum', 'storage_disk', 'storage_key', 'thumbnail_storage_key'] as $field) {
                    if ((string) $existing->{$field} !== (string) $immutable[$field]) {
                        throw new RuntimeException("Conflicting immutable media field: {$field}");
                    }
                }

                return $existing;
            }
            $media = new PreRegistrationMedia($immutable);
            $media->id = (string) $payload['media_id'];
            $media->preRegistration()->associate($registration);
            $media->save();

            return $media;
        }, 3);
    }
}
