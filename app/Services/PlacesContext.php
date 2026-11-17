<?php

namespace App\Services;

use App\Models\Admin;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use JsonException;

class PlacesContext
{
    public function issue(Admin $admin, string $kind, array $data): string
    {
        // Browser-only transit: no response bodies or cursors in sessions/cache.
        return Crypt::encryptString(json_encode([...$data, 'kind' => $kind, 'admin' => $admin->id, 'expires' => now()->addMinutes(15)->timestamp], JSON_THROW_ON_ERROR));
    }

    public function read(Admin $admin, string $kind, string $token): array
    {
        try {
            $data = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            throw ValidationException::withMessages(['token' => 'A referência expirou ou é inválida. Refaça a pesquisa.']);
        }
        if (! is_array($data) || ($data['kind'] ?? null) !== $kind || ($data['admin'] ?? null) !== $admin->id || ! is_int($data['expires'] ?? null) || $data['expires'] <= now()->timestamp) {
            throw ValidationException::withMessages(['token' => 'A referência expirou ou é inválida. Refaça a pesquisa.']);
        }

        return $data;
    }
}
