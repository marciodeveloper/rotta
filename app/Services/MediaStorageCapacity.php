<?php

namespace App\Services;

use App\Models\MediaStorageUsage;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class MediaStorageCapacity
{
    /**
     * @template T
     *
     * @param  Closure(): T  $store
     * @return T
     */
    public function store(int $additionalBytes, Closure $store): mixed
    {
        return DB::transaction(function () use ($additionalBytes, $store): mixed {
            $usage = $this->lockUsage();
            $before = $this->snapshot($additionalBytes);
            $this->record($usage, $before);

            if ($before['level'] === 'blocked') {
                Log::warning('prelaunch.media.upload_blocked', $before);
                throw ValidationException::withMessages([
                    'media' => 'O envio de imagens está temporariamente indisponível por limite de armazenamento.',
                ]);
            }

            $result = $store();
            $this->record($usage, $this->snapshot());

            return $result;
        }, 3);
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $mutation
     * @return T
     */
    public function mutate(Closure $mutation): mixed
    {
        return DB::transaction(function () use ($mutation): mixed {
            $usage = $this->lockUsage();
            $result = $mutation();
            $this->record($usage, $this->snapshot());

            return $result;
        }, 3);
    }

    /** @return array{used_bytes:int,projected_bytes:int,free_bytes:int|null,quota_bytes:int,alert_bytes:int,block_bytes:int,minimum_free_bytes:int,level:string} */
    public function inspect(): array
    {
        return DB::transaction(function (): array {
            $usage = $this->lockUsage();
            $snapshot = $this->snapshot();
            $this->record($usage, $snapshot);

            return $snapshot;
        }, 3);
    }

    private function lockUsage(): MediaStorageUsage
    {
        return MediaStorageUsage::query()->lockForUpdate()->findOrFail(MediaStorageUsage::SINGLETON_ID);
    }

    /** @return array{used_bytes:int,projected_bytes:int,free_bytes:int|null,quota_bytes:int,alert_bytes:int,block_bytes:int,minimum_free_bytes:int,level:string} */
    private function snapshot(int $additionalBytes = 0): array
    {
        $diskName = (string) config('prelaunch.media_disk');
        $disk = Storage::disk($diskName);
        $usedBytes = array_sum(array_map(fn (string $path): int => $disk->size($path), $disk->allFiles()));
        $quotaBytes = (int) config('prelaunch.media.global_quota_bytes');
        $alertBytes = intdiv($quotaBytes * (int) config('prelaunch.media.alert_percent'), 100);
        $blockBytes = intdiv($quotaBytes * (int) config('prelaunch.media.block_percent'), 100);
        $minimumFreeBytes = (int) config('prelaunch.media.minimum_free_bytes');
        $projectedBytes = $usedBytes + $additionalBytes;
        $freeBytes = null;

        if (config("filesystems.disks.{$diskName}.driver") === 'local') {
            $root = $disk->path('');
            $available = @disk_free_space($root);
            if ($available === false) {
                throw new RuntimeException('Unable to determine free space for private media storage.');
            }
            $freeBytes = (int) $available;
        }

        $blockedByQuota = $projectedBytes >= $blockBytes;
        $blockedByHost = $freeBytes !== null && $freeBytes - $additionalBytes < $minimumFreeBytes;
        $level = $blockedByQuota || $blockedByHost ? 'blocked' : ($projectedBytes >= $alertBytes ? 'alert' : 'normal');

        return [
            'used_bytes' => $usedBytes,
            'projected_bytes' => $projectedBytes,
            'free_bytes' => $freeBytes,
            'quota_bytes' => $quotaBytes,
            'alert_bytes' => $alertBytes,
            'block_bytes' => $blockBytes,
            'minimum_free_bytes' => $minimumFreeBytes,
            'level' => $level,
        ];
    }

    /** @param array{used_bytes:int,projected_bytes:int,free_bytes:int|null,quota_bytes:int,alert_bytes:int,block_bytes:int,minimum_free_bytes:int,level:string} $snapshot */
    private function record(MediaStorageUsage $usage, array $snapshot): void
    {
        $usage->update([
            'used_bytes' => $snapshot['used_bytes'],
            'free_bytes' => $snapshot['free_bytes'],
            'level' => $snapshot['level'],
            'checked_at' => now(),
        ]);

        if ($snapshot['level'] !== 'normal') {
            Log::warning('prelaunch.media.capacity_'.$snapshot['level'], $snapshot);
        }
    }
}
