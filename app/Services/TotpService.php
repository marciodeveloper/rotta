<?php

namespace App\Services;

class TotpService
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(int $bytes = 20): string
    {
        $binary = random_bytes($bytes);
        $bits = '';
        foreach (str_split($binary) as $character) {
            $bits .= str_pad(decbin(ord($character)), 8, '0', STR_PAD_LEFT);
        }

        $secret = '';
        foreach (str_split($bits, 5) as $chunk) {
            $secret .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $secret;
    }

    public function verify(string $secret, string $code, ?int $timestamp = null): bool
    {
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $counter = intdiv($timestamp ?? now()->timestamp, 30);
        for ($offset = -1; $offset <= 1; $offset++) {
            if (hash_equals($this->codeAt($secret, $counter + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    public function codeAt(string $secret, int $counter): string
    {
        $binarySecret = $this->decodeBase32($secret);
        $counterBytes = pack('N2', intdiv($counter, 0x100000000), $counter & 0xFFFFFFFF);
        $hash = hash_hmac('sha1', $counterBytes, $binarySecret, true);
        $offset = ord($hash[19]) & 0x0F;
        $value = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private function decodeBase32(string $value): string
    {
        $value = strtoupper(rtrim($value, '='));
        $bits = '';
        foreach (str_split($value) as $character) {
            $position = strpos(self::ALPHABET, $character);
            if ($position === false) {
                throw new \InvalidArgumentException('Invalid Base32 secret.');
            }
            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $binary = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $binary .= chr(bindec($chunk));
            }
        }

        return $binary;
    }
}
