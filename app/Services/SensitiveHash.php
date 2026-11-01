<?php

namespace App\Services;

class SensitiveHash
{
    public function make(string $normalizedValue): string
    {
        return $this->makeLegacy($normalizedValue);
    }

    public function makeLegacy(string $normalizedValue): string
    {
        $pepper = config('prelaunch.data_pepper');

        if (! is_string($pepper) || $pepper === '') {
            throw new \RuntimeException('PRELAUNCH_DATA_PEPPER must be configured.');
        }

        return hash_hmac('sha256', $normalizedValue, $pepper);
    }

    public function makeFor(string $context, string $normalizedValue): string
    {
        if (! preg_match('/^[a-z][a-z0-9.-]{1,63}$/', $context)) {
            throw new \InvalidArgumentException('Invalid sensitive hash context.');
        }

        $pepper = config('prelaunch.data_pepper');
        if (! is_string($pepper) || $pepper === '') {
            throw new \RuntimeException('PRELAUNCH_DATA_PEPPER must be configured.');
        }

        return hash_hmac('sha256', "rotta-prelaunch\0{$context}\0{$normalizedValue}", $pepper);
    }
}
