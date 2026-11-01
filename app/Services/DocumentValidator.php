<?php

namespace App\Services;

class DocumentValidator
{
    public function normalize(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    public function isValidFor(string $type, string $value): bool
    {
        $digits = $this->normalize($value);

        return match ($type) {
            'professional' => $this->isValidCpf($digits),
            'company' => $this->isValidCnpj($digits),
            default => false,
        };
    }

    public function isValidCpf(string $value): bool
    {
        if (strlen($value) !== 11 || preg_match('/^(\d)\1{10}$/', $value)) {
            return false;
        }

        for ($position = 9; $position < 11; $position++) {
            $sum = 0;
            for ($index = 0; $index < $position; $index++) {
                $sum += (int) $value[$index] * (($position + 1) - $index);
            }
            $digit = ((10 * $sum) % 11) % 10;
            if ((int) $value[$position] !== $digit) {
                return false;
            }
        }

        return true;
    }

    public function isValidCnpj(string $value): bool
    {
        if (strlen($value) !== 14 || preg_match('/^(\d)\1{13}$/', $value)) {
            return false;
        }

        $weights = [[5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2], [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]];
        foreach ($weights as $position => $sequence) {
            $sum = 0;
            foreach ($sequence as $index => $weight) {
                $sum += (int) $value[$index] * $weight;
            }
            $remainder = $sum % 11;
            $digit = $remainder < 2 ? 0 : 11 - $remainder;
            if ((int) $value[12 + $position] !== $digit) {
                return false;
            }
        }

        return true;
    }
}
