<?php

namespace App\Services;

class DocumentValidator
{
    public function normalize(string $value): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', $value) ?? '');
    }

    public function isValidFor(string $type, string $value): bool
    {
        $normalized = $this->normalize($value);

        return match ($type) {
            'professional' => ctype_digit($normalized) && $this->isValidCpf($normalized),
            'company' => $this->isValidCnpj($normalized),
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
        $value = $this->normalize($value);
        if (! preg_match('/^[A-Z0-9]{12}[0-9]{2}$/', $value) || preg_match('/^(\d)\1{13}$/', $value)) {
            return false;
        }

        $weights = [[5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2], [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]];
        foreach ($weights as $position => $sequence) {
            $sum = 0;
            foreach ($sequence as $index => $weight) {
                $sum += (ord($value[$index]) - 48) * $weight;
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
