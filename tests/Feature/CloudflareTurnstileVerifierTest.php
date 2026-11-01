<?php

namespace Tests\Feature;

use App\Services\CloudflareTurnstileVerifier;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CloudflareTurnstileVerifierTest extends TestCase
{
    public function test_accepts_expected_hostname_action_and_fresh_challenge(): void
    {
        config(['prelaunch.turnstile.secret_key' => 'synthetic-secret']);
        Http::fake(['*' => Http::response(['success' => true, 'hostname' => 'rotta.serradev.com', 'action' => 'pre_registration', 'challenge_ts' => now()->toIso8601String()])]);

        $this->assertTrue(app(CloudflareTurnstileVerifier::class)->verify('synthetic-token', '127.0.0.1'));
    }

    public function test_rejects_wrong_hostname_action_expired_challenge_and_timeout(): void
    {
        config(['prelaunch.turnstile.secret_key' => 'synthetic-secret']);
        foreach ([
            ['success' => true, 'hostname' => 'evil.test', 'action' => 'pre_registration', 'challenge_ts' => now()->toIso8601String()],
            ['success' => true, 'hostname' => 'rotta.serradev.com', 'action' => 'wrong', 'challenge_ts' => now()->toIso8601String()],
            ['success' => true, 'hostname' => 'rotta.serradev.com', 'action' => 'pre_registration', 'challenge_ts' => now()->subMinutes(6)->toIso8601String()],
        ] as $payload) {
            Http::fake(['*' => Http::response($payload)]);
            $this->assertFalse(app(CloudflareTurnstileVerifier::class)->verify('synthetic-token', null));
        }
        Http::fake(fn () => throw new ConnectionException('synthetic timeout'));
        $this->assertFalse(app(CloudflareTurnstileVerifier::class)->verify('synthetic-token', null));
    }
}
