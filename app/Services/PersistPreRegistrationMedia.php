<?php

namespace App\Services;

use App\Models\PreRegistration;
use App\Models\PreRegistrationMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PersistPreRegistrationMedia
{
    public function __construct(private SafeImageProcessor $processor) {}

    /** @param array<string, mixed> $data @param list<string> $storedKeys */
    public function handle(PreRegistration $registration, array $data, array &$storedKeys): void
    {
        $items = [];
        if (($data['logo'] ?? null) instanceof UploadedFile) {
            $items[] = ['logo', 0, $data['logo'], $data['logo_alt']];
        }
        if (($data['cover'] ?? null) instanceof UploadedFile) {
            $items[] = ['cover', 0, $data['cover'], $data['cover_alt']];
        }
        foreach (($data['gallery'] ?? []) as $position => $file) {
            if ($file instanceof UploadedFile) {
                $items[] = ['gallery', $position, $file, $data['gallery_alt'][$position] ?? ''];
            }
        }

        $totalBytes = array_sum(array_map(fn (array $item): int => $item[2]->getSize() ?: 0, $items));
        if ($totalBytes > 50 * 1024 * 1024) {
            throw ValidationException::withMessages(['media' => 'O total de imagens não pode exceder 50 MB.']);
        }

        $diskName = (string) config('prelaunch.media_disk');
        $disk = Storage::disk($diskName);
        foreach ($items as [$type, $position, $file, $alt]) {
            $processed = $this->processor->process($file);
            $mediaId = (string) Str::uuid();
            $base = "{$registration->id}/{$mediaId}";
            $originalKey = "{$base}/original.{$processed['extension']}";
            $thumbnailKey = "{$base}/thumbnail.{$processed['extension']}";
            $disk->put($originalKey, $processed['bytes'], ['visibility' => 'private']);
            $storedKeys[] = $originalKey;
            $disk->put($thumbnailKey, $processed['thumbnail'], ['visibility' => 'private']);
            $storedKeys[] = $thumbnailKey;

            $media = new PreRegistrationMedia([
                'pre_registration_id' => $registration->id, 'type' => $type, 'position' => $position,
                'alt_text' => trim((string) $alt), 'checksum' => $processed['checksum'], 'mime_type' => $processed['mime'],
                'extension' => $processed['extension'], 'size_bytes' => strlen($processed['bytes']), 'width' => $processed['width'],
                'height' => $processed['height'], 'status' => 'pending_review', 'storage_disk' => $diskName,
                'storage_key' => $originalKey, 'thumbnail_storage_key' => $thumbnailKey, 'rights_confirmed_at' => now(),
            ]);
            $media->id = $mediaId;
            $media->save();
        }
    }
}
