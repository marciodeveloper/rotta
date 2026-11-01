<?php

namespace App\Services;

use App\Contracts\TurnstileVerifier;
use Illuminate\Support\Facades\Http;
use Throwable;

class CloudflareTurnstileVerifier implements TurnstileVerifier
{
    public function verify(string $token, ?string $ipAddress): bool
    {
        $secret = config('prelaunch.turnstile.secret_key');

        if (! is_string($secret) || $secret === '' || $token === '') {
            return false;
        }

        try {
            $response = Http::asForm()
                ->connectTimeout(3)
                ->timeout(5)
                ->post((string) config('prelaunch.turnstile.verify_url'), [
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $ipAddress,
                ]);
        } catch (Throwable) {
            return false;
        }

        $challengeAt = $response->json('challenge_ts');
        $challengeTimestamp = is_string($challengeAt) ? strtotime($challengeAt) : false;
        $expectedHostname = (string) config('prelaunch.turnstile.expected_hostname');
        $expectedAction = (string) config('prelaunch.turnstile.expected_action');
        $fresh = $challengeTimestamp !== false
            && $challengeTimestamp <= now()->timestamp + 30
            && $challengeTimestamp >= now()->timestamp - (int) config('prelaunch.turnstile.max_challenge_age_seconds');

        return $response->successful()
            && $response->json('success') === true
            && is_string($response->json('hostname'))
            && hash_equals($expectedHostname, $response->json('hostname'))
            && is_string($response->json('action'))
            && hash_equals($expectedAction, $response->json('action'))
            && $fresh;
    }
}
