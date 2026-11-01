<?php

namespace App\Services;

use App\Contracts\TurnstileVerifier;
use Illuminate\Support\Facades\Http;

class CloudflareTurnstileVerifier implements TurnstileVerifier
{
    public function verify(string $token, ?string $ipAddress): bool
    {
        $secret = config('prelaunch.turnstile.secret_key');

        if (! is_string($secret) || $secret === '' || $token === '') {
            return false;
        }

        $response = Http::asForm()
            ->connectTimeout(3)
            ->timeout(5)
            ->post((string) config('prelaunch.turnstile.verify_url'), [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $ipAddress,
            ]);

        return $response->successful() && $response->json('success') === true;
    }
}
