<?php

namespace Tests\Feature;

use App\Models\PrivacyNotice;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_public_page_sends_browser_security_headers(): void
    {
        PrivacyNotice::factory()->create(['is_current' => true]);

        $response = $this->get(route('pre-registration.create'));

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString("frame-ancestors 'none'", (string) $response->headers->get('Content-Security-Policy'));
    }
}
