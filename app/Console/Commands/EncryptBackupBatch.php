<?php

namespace App\Console\Commands;

use App\Services\EncryptedBackupArchive;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('prelaunch:backup:encrypt {batch : Absolute backup batch directory} {--output= : Absolute encrypted archive path} {--key-file= : Absolute 0600 recovery key file}')]
#[Description('Encrypt a backup batch with authenticated streaming encryption')]
class EncryptBackupBatch extends Command
{
    public function handle(EncryptedBackupArchive $archive): int
    {
        $batch = rtrim((string) $this->argument('batch'), '/');
        $output = (string) ($this->option('output') ?: $batch.'.rottabackup');
        $keyFile = (string) $this->option('key-file');
        if (! str_starts_with($batch, '/') || ! is_dir($batch) || ! str_starts_with($output, '/') || file_exists($output) || $keyFile === '') {
            $this->error('Batch, output and key file must be explicit absolute paths; output must not exist.');

            return self::FAILURE;
        }
        try {
            $archive->encrypt($batch, $output, $keyFile, (int) config('prelaunch.backup.timeout_seconds'));
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Backup encryption failed closed.');

            return self::FAILURE;
        }
        $this->info($output);

        return self::SUCCESS;
    }
}
