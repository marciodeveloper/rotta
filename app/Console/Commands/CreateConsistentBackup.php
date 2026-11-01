<?php

namespace App\Console\Commands;

use App\Models\PreRegistrationMedia;
use App\Services\MediaStorageCapacity;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

#[Signature('prelaunch:backup:create {--output= : Protected local staging directory}')]
#[Description('Create a consistent PostgreSQL and private media backup batch')]
class CreateConsistentBackup extends Command
{
    public function handle(MediaStorageCapacity $capacity, Filesystem $files): int
    {
        $root = (string) ($this->option('output') ?: config('prelaunch.backup.root'));
        if (! str_starts_with($root, '/')) {
            $this->error('Backup output must be an absolute path.');

            return self::FAILURE;
        }

        $batch = rtrim($root, '/').'/'.now()->format('Ymd_His').'_'.Str::lower(Str::random(8));
        $files->ensureDirectoryExists($batch, 0700, true);
        chmod($batch, 0700);

        try {
            $capacity->mutate(fn () => $this->createBatch($batch));
        } catch (Throwable $exception) {
            $files->deleteDirectory($batch);
            report($exception);
            $this->error('Backup failed closed; the incomplete batch was removed.');

            return self::FAILURE;
        }

        $this->info($batch);

        return self::SUCCESS;
    }

    private function createBatch(string $batch): void
    {
        $diskName = (string) config('prelaunch.media_disk');
        if (config("filesystems.disks.{$diskName}.driver") !== 'local') {
            throw new RuntimeException('This backup command currently requires the approved local media driver.');
        }

        $disk = Storage::disk($diskName);
        $mediaRoot = $disk->path('');
        $databaseDump = "{$batch}/database.dump";
        $mediaArchive = "{$batch}/media.tar.gz";
        $manifestPath = "{$batch}/media-manifest.json";
        $timeout = (int) config('prelaunch.backup.timeout_seconds');

        $manifest = [];
        PreRegistrationMedia::query()->orderBy('id')->each(function (PreRegistrationMedia $media) use ($disk, &$manifest): void {
            $storageKey = $media->getRawOriginal('storage_key');
            $thumbnailKey = $media->getRawOriginal('thumbnail_storage_key');
            if (! $disk->exists($storageKey) || ! $disk->exists($thumbnailKey)) {
                throw new RuntimeException("Media object missing for {$media->id}.");
            }
            $bytes = $disk->get($storageKey);
            if (! hash_equals($media->checksum, hash('sha256', $bytes)) || strlen($bytes) !== $media->size_bytes) {
                throw new RuntimeException("Media integrity check failed for {$media->id}.");
            }
            $thumbnail = $disk->get($thumbnailKey);
            $manifest[] = [
                'media_id' => $media->id,
                'pre_registration_id' => $media->pre_registration_id,
                'storage_key' => $storageKey,
                'checksum' => $media->checksum,
                'size_bytes' => $media->size_bytes,
                'thumbnail_storage_key' => $thumbnailKey,
                'thumbnail_checksum' => hash('sha256', $thumbnail),
                'thumbnail_size_bytes' => strlen($thumbnail),
            ];
        });

        file_put_contents($manifestPath, json_encode([
            'format' => 'rotta-prelaunch-backup-v1',
            'created_at' => now()->toIso8601String(),
            'media_count' => count($manifest),
            'media' => $manifest,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        $database = config('database.connections.pgsql');
        $dump = new Process([
            'pg_dump', '--format=custom', '--no-owner', '--no-privileges',
            '--host='.(string) $database['host'], '--port='.(string) $database['port'],
            '--username='.(string) $database['username'], '--file='.$databaseDump,
            (string) $database['database'],
        ], null, ['PGPASSWORD' => (string) $database['password']]);
        $dump->setTimeout($timeout)->mustRun();

        (new Process(['tar', '-czf', $mediaArchive, '-C', $mediaRoot, '.']))->setTimeout($timeout)->mustRun();

        $checksums = [];
        foreach (['database.dump', 'media.tar.gz', 'media-manifest.json'] as $name) {
            $checksums[] = hash_file('sha256', "{$batch}/{$name}")."  {$name}";
            chmod("{$batch}/{$name}", 0600);
        }
        file_put_contents("{$batch}/SHA256SUMS", implode("\n", $checksums)."\n");
        chmod("{$batch}/SHA256SUMS", 0600);
    }
}
