<?php

namespace App\Console\Commands;

use App\Services\EncryptedBackupArchive;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('prelaunch:backup:decrypt {archive : Absolute encrypted archive path} {--output= : New absolute restore directory} {--key-file= : Absolute 0600 recovery key file}')]
#[Description('Authenticate and decrypt a backup archive into an isolated restore directory')]
class DecryptBackupBatch extends Command
{
    public function handle(EncryptedBackupArchive $backupArchive): int
    {
        $archive = (string) $this->argument('archive');
        $output = (string) $this->option('output');
        $keyFile = (string) $this->option('key-file');
        if (! str_starts_with($archive, '/') || ! is_file($archive) || ! str_starts_with($output, '/') || file_exists($output) || $keyFile === '') {
            $this->error('Archive, output and key file must be explicit absolute paths; output must not exist.');

            return self::FAILURE;
        }
        try {
            $batch = $backupArchive->decrypt($archive, $output, $keyFile, (int) config('prelaunch.backup.timeout_seconds'));
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Backup decryption failed closed.');

            return self::FAILURE;
        }
        $this->info($batch);

        return self::SUCCESS;
    }
}
