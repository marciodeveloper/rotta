<?php

namespace App\Services;

class SensitiveHash
{
    public function make(string $normalizedValue): string
    {
        $pepper = config('prelaunch.data_pepper');

        if (! is_string($pepper) || $pepper === '') {
            throw new \RuntimeException('PRELAUNCH_DATA_PEPPER must be configured.');
        }

        return hash_hmac('sha256', $normalizedValue, $pepper);
    }
}
