<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use JsonException;

#[Signature('prelaunch:backup:verify {batch : Absolute path to an extracted backup batch}')]
#[Description('Verify backup package checksums and media manifest structure')]
class VerifyBackupBatch extends Command
{
    public function handle(): int
    {
        $batch = rtrim((string) $this->argument('batch'), '/');
        if (! str_starts_with($batch, '/') || ! is_dir($batch)) {
            $this->error('Backup batch must be an existing absolute directory.');

            return self::FAILURE;
        }

        foreach (['database.dump', 'media.tar.gz', 'media-manifest.json', 'SHA256SUMS'] as $name) {
            if (! is_file("{$batch}/{$name}")) {
                $this->error("Missing backup artifact: {$name}");

                return self::FAILURE;
            }
        }

        $expected = [];
        foreach (file("{$batch}/SHA256SUMS", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (! preg_match('/^([a-f0-9]{64})  ([A-Za-z0-9.-]+)$/', $line, $matches)) {
                $this->error('Invalid checksum manifest.');

                return self::FAILURE;
            }
            $expected[$matches[2]] = $matches[1];
        }

        foreach (['database.dump', 'media.tar.gz', 'media-manifest.json'] as $name) {
            if (! isset($expected[$name]) || ! hash_equals($expected[$name], (string) hash_file('sha256', "{$batch}/{$name}"))) {
                $this->error("Checksum mismatch: {$name}");

                return self::FAILURE;
            }
        }

        try {
            $manifest = json_decode((string) file_get_contents("{$batch}/media-manifest.json"), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->error('Invalid media manifest JSON.');

            return self::FAILURE;
        }
        if (($manifest['format'] ?? null) !== 'rotta-prelaunch-backup-v1' || ($manifest['media_count'] ?? null) !== count($manifest['media'] ?? [])) {
            $this->error('Invalid media manifest structure.');

            return self::FAILURE;
        }

        $this->info('Backup batch verified.');

        return self::SUCCESS;
    }
}
