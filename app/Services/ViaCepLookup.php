<?php

namespace App\Services;

use App\Contracts\CepLookup;
use Illuminate\Support\Facades\Http;

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

        if (! $response->successful() || $response->json('erro') === true) {
            return null;
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
