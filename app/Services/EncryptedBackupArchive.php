<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

class EncryptedBackupArchive
{
    private const MAGIC = "ROTTA-PRELAUNCH-BACKUP\x01";

    private const CHUNK_BYTES = 1_048_576;

    public function encrypt(string $batch, string $destination, string $keyFile, int $timeout): void
    {
        $key = $this->readKey($keyFile);
        $temporaryTar = $destination.'.tar.tmp';
        $temporaryEncrypted = $destination.'.tmp';

        try {
            (new Process(['tar', '-cf', $temporaryTar, '-C', dirname($batch), basename($batch)]))->setTimeout($timeout)->mustRun();
            $input = fopen($temporaryTar, 'rb');
            $output = fopen($temporaryEncrypted, 'xb');
            if ($input === false || $output === false) {
                throw new RuntimeException('Unable to open protected backup streams.');
            }
            [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
            $this->writeAll($output, self::MAGIC.$header);
            while (! feof($input)) {
                $plain = fread($input, self::CHUNK_BYTES);
                if ($plain === false) {
                    throw new RuntimeException('Unable to read the backup archive.');
                }
                $tag = feof($input) ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;
                $cipher = sodium_crypto_secretstream_xchacha20poly1305_push($state, $plain, '', $tag);
                $this->writeAll($output, pack('N', strlen($cipher)).$cipher);
            }
            fclose($input);
            fclose($output);
            chmod($temporaryEncrypted, 0600);
            rename($temporaryEncrypted, $destination);
            file_put_contents($destination.'.sha256', hash_file('sha256', $destination).'  '.basename($destination)."\n");
            chmod($destination.'.sha256', 0600);
        } finally {
            sodium_memzero($key);
            @unlink($temporaryTar);
            @unlink($temporaryEncrypted);
        }
    }

    public function decrypt(string $archive, string $destination, string $keyFile, int $timeout): string
    {
        $key = $this->readKey($keyFile);
        $temporaryTar = dirname($destination).'/.'.basename($destination).'.tar.tmp';

        try {
            $input = fopen($archive, 'rb');
            $output = fopen($temporaryTar, 'xb');
            if ($input === false || $output === false) {
                throw new RuntimeException('Unable to open protected restore streams.');
            }
            if (! hash_equals(self::MAGIC, $this->readExact($input, strlen(self::MAGIC)))) {
                throw new RuntimeException('Unsupported encrypted backup format.');
            }
            $header = $this->readExact($input, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $key);
            $finalSeen = false;
            while (! feof($input)) {
                $lengthBytes = fread($input, 4);
                if ($lengthBytes === false || $lengthBytes === '') {
                    break;
                }
                if (strlen($lengthBytes) !== 4) {
                    throw new RuntimeException('Truncated encrypted backup frame.');
                }
                $length = unpack('Nlength', $lengthBytes)['length'];
                if ($length < SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES || $length > self::CHUNK_BYTES + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES) {
                    throw new RuntimeException('Invalid encrypted backup frame.');
                }
                $pulled = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $this->readExact($input, $length));
                if ($pulled === false) {
                    throw new RuntimeException('Encrypted backup authentication failed.');
                }
                [$plain, $tag] = $pulled;
                if ($finalSeen || ($tag !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE && $tag !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL)) {
                    throw new RuntimeException('Invalid encrypted backup stream state.');
                }
                $this->writeAll($output, $plain);
                $finalSeen = $tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;
            }
            fclose($input);
            fclose($output);
            if (! $finalSeen) {
                throw new RuntimeException('Encrypted backup has no authenticated final frame.');
            }
            $entries = (new Process(['tar', '-tf', $temporaryTar]))->setTimeout($timeout)->mustRun()->getOutput();
            $topLevel = $this->validateArchiveEntries($entries);
            mkdir($destination, 0700, true);
            (new Process(['tar', '-xf', $temporaryTar, '-C', $destination]))->setTimeout($timeout)->mustRun();

            return $destination.'/'.$topLevel;
        } finally {
            sodium_memzero($key);
            @unlink($temporaryTar);
        }
    }

    private function readKey(string $keyFile): string
    {
        if (! str_starts_with($keyFile, '/') || ! is_file($keyFile) || ((fileperms($keyFile) & 0077) !== 0)) {
            throw new RuntimeException('The recovery key must be an absolute 0600 file.');
        }
        $key = sodium_base642bin(trim((string) file_get_contents($keyFile)), SODIUM_BASE64_VARIANT_ORIGINAL);
        if (strlen($key) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) {
            throw new RuntimeException('The recovery key must contain exactly 32 base64-encoded bytes.');
        }

        return $key;
    }

    /** @param resource $stream */
    private function writeAll($stream, string $bytes): void
    {
        while ($bytes !== '') {
            $written = fwrite($stream, $bytes);
            if ($written === false || $written === 0) {
                throw new RuntimeException('Unable to write the protected backup stream.');
            }
            $bytes = substr($bytes, $written);
        }
    }

    /** @param resource $stream */
    private function readExact($stream, int $length): string
    {
        $bytes = '';
        while (strlen($bytes) < $length && ! feof($stream)) {
            $chunk = fread($stream, $length - strlen($bytes));
            if ($chunk === false) {
                throw new RuntimeException('Unable to read the protected backup stream.');
            }
            $bytes .= $chunk;
        }
        if (strlen($bytes) !== $length) {
            throw new RuntimeException('Truncated encrypted backup.');
        }

        return $bytes;
    }

    private function validateArchiveEntries(string $entries): string
    {
        $topLevels = [];
        foreach (array_filter(explode("\n", trim($entries))) as $entry) {
            if (str_starts_with($entry, '/') || preg_match('~(^|/)\.\.(/|$)~', $entry) === 1) {
                throw new RuntimeException('Unsafe path in encrypted backup archive.');
            }
            $topLevels[] = explode('/', $entry)[0];
        }
        $topLevels = array_values(array_unique($topLevels));
        if (count($topLevels) !== 1 || $topLevels[0] === '') {
            throw new RuntimeException('Encrypted backup must contain exactly one batch directory.');
        }

        return $topLevels[0];
    }
}
