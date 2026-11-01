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
    public function __construct(private SafeImageProcessor $processor, private MediaStorageCapacity $capacity) {}

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
        $maximumRequestBytes = (int) config('prelaunch.media.max_image_bytes') * (int) config('prelaunch.media.max_files_per_registration');
        if ($totalBytes > $maximumRequestBytes) {
            throw ValidationException::withMessages(['media' => 'O total de imagens não pode exceder 40 MB.']);
        }

        if ($items === []) {
            return;
        }

        $diskName = (string) config('prelaunch.media_disk');
        $disk = Storage::disk($diskName);
        $prepared = [];
        foreach ($items as [$type, $position, $file, $alt]) {
            $processed = $this->processor->process($file);
            $mediaId = (string) Str::uuid();
            $base = "{$registration->id}/{$mediaId}";
            $prepared[] = [
                'id' => $mediaId,
                'type' => $type,
                'position' => $position,
                'alt' => $alt,
                'processed' => $processed,
                'original_key' => "{$base}/original.{$processed['extension']}",
                'thumbnail_key' => "{$base}/thumbnail.{$processed['extension']}",
            ];
        }

        $additionalBytes = array_sum(array_map(
            fn (array $item): int => strlen($item['processed']['bytes']) + strlen($item['processed']['thumbnail']),
            $prepared,
        ));

        $this->capacity->store($additionalBytes, function () use ($prepared, $registration, $disk, $diskName, &$storedKeys): void {
            foreach ($prepared as $item) {
                $processed = $item['processed'];
                $disk->put($item['original_key'], $processed['bytes'], ['visibility' => 'private']);
                $storedKeys[] = $item['original_key'];
                $disk->put($item['thumbnail_key'], $processed['thumbnail'], ['visibility' => 'private']);
                $storedKeys[] = $item['thumbnail_key'];

                $media = new PreRegistrationMedia([
                    'pre_registration_id' => $registration->id, 'type' => $item['type'], 'position' => $item['position'],
                    'alt_text' => trim((string) $item['alt']), 'checksum' => $processed['checksum'], 'mime_type' => $processed['mime'],
                    'extension' => $processed['extension'], 'size_bytes' => strlen($processed['bytes']), 'width' => $processed['width'],
                    'height' => $processed['height'], 'status' => 'pending_review', 'storage_disk' => $diskName,
                    'storage_key' => $item['original_key'], 'thumbnail_storage_key' => $item['thumbnail_key'], 'rights_confirmed_at' => now(),
                ]);
                $media->id = $item['id'];
                $media->save();
            }
        });
    }
}
