<?php

namespace App\Console\Commands;

use App\Models\PreRegistrationMedia;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('prelaunch:media:cleanup-orphans {--force : Delete instead of reporting} {--older-than=24 : Minimum age in hours}')]
#[Description('Report or remove private media objects not referenced by the database')]
class CleanupOrphanedMedia extends Command
{
    public function handle(): int
    {
        $disk = Storage::disk((string) config('prelaunch.media_disk'));
        $referenced = PreRegistrationMedia::query()->pluck('thumbnail_storage_key')->merge(PreRegistrationMedia::query()->pluck('storage_key'))->flip();
        $cutoff = now()->subHours(max(1, (int) $this->option('older-than')))->timestamp;
        $orphans = collect($disk->allFiles())->filter(fn (string $key): bool => ! $referenced->has($key) && $disk->lastModified($key) < $cutoff)->values();
        if ($this->option('force') && $orphans->isNotEmpty()) {
            $disk->delete($orphans->all());
        }
        $this->info(($this->option('force') ? 'Removed' : 'Found')." {$orphans->count()} orphaned media objects.");

        return self::SUCCESS;
    }
}
