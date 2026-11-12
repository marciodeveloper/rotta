<?php

namespace App\Services;

use App\Models\Prospect;
use Illuminate\Support\Str;

class NormalizeProspect
{
    public function handle(array $input): array
    {
        $data = [];
        foreach (array_keys(Prospect::FIELDS) as $field) {
            $value = $input[$field] ?? ($field === 'priority' ? 'medium' : null);
            $data[$field] = is_string($value) ? (trim($value) ?: null) : $value;
        }
        foreach (['business_name', 'category', 'city'] as $field) {
            if (is_string($data[$field])) {
                $data[$field] = preg_replace('/\s+/u', ' ', $data[$field]);
            }
        }
        if (is_string($data['state'])) {
            $data['state'] = Str::upper($data['state']);
        }
        if (is_string($data['email'])) {
            $data['email'] = Str::lower($data['email']);
        }
        foreach (['phone', 'postal_code'] as $field) {
            if (is_string($data[$field]) && preg_match('/^[\d\s()+.\-]+$/D', $data[$field])) {
                $data[$field] = preg_replace('/\D/', '', $data[$field]);
            }
        }

        return $data;
    }

    public function key(string $value): string
    {
        return Str::lower(Str::ascii(preg_replace('/\s+/u', ' ', trim($value))));
    }

    public function phoneKey(string $phone): string
    {
        return strlen($phone) > 11 && str_starts_with($phone, '55') ? substr($phone, 2) : $phone;
    }

    public function decisions(array $input): array
    {
        foreach (['change_reason', 'duplicate_reason', 'blocked_match_reason', 'reason'] as $field) {
            if (is_string($input[$field] ?? null)) {
                $value = trim(preg_replace('/[\p{C}\p{Z}\s]+/u', ' ', $input[$field]));
                $input[$field] = $value === '' ? null : $value;
            }
        }

        return $input;
    }

    public function source(array $input): array
    {
        $data = [];
        foreach (array_keys(ProspectRules::source()) as $field) {
            $value = $input[$field] ?? null;
            $data[$field] = is_string($value) ? (trim($value) ?: null) : $value;
        }

        return $data;
    }
}
