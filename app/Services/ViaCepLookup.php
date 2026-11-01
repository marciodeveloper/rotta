<?php

namespace App\Services;

use App\Contracts\CepLookup;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use UnexpectedValueException;

class ViaCepLookup implements CepLookup
{
    public function find(string $postalCode): ?array
    {
        $postalCode = preg_replace('/\D+/', '', $postalCode) ?? '';

        if (strlen($postalCode) !== 8) {
            return null;
        }

        $response = Http::connectTimeout(2)
            ->timeout(4)
            ->get(rtrim((string) config('prelaunch.cep.base_url'), '/').'/'.$postalCode.'/json/');

        if (! $response->successful()) {
            throw new RuntimeException('CEP provider unavailable.');
        }

        if ($response->json('erro') === true) {
            return null;
        }

        if (! is_string($response->json('localidade')) || ! is_string($response->json('uf')) || ! preg_match('/^[A-Z]{2}$/', $response->json('uf'))) {
            throw new UnexpectedValueException('CEP provider returned an invalid address.');
        }

        return [
            'postal_code' => $postalCode,
            'street' => (string) $response->json('logradouro', ''),
            'neighborhood' => (string) $response->json('bairro', ''),
            'city' => (string) $response->json('localidade', ''),
            'state' => (string) $response->json('uf', ''),
        ];
    }
}
