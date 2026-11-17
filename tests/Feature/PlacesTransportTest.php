<?php

namespace Tests\Feature;

use App\Exceptions\PlacesUnavailable;
use App\Models\Admin;
use App\Models\AdminAuditEvent;
use App\Services\GooglePlacesProvider;
use App\Services\PlacesQuota;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\TransferStats;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PlacesTransportTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['business_explorer.google_api_key' => 'synthetic-secret-never-log', 'business_explorer.terms_url' => 'https://example.invalid/terms', 'business_explorer.privacy_url' => 'https://example.invalid/privacy', 'business_explorer.page_size' => 5, 'business_explorer.max_pages' => 2, 'business_explorer.per_admin_minute' => 3, 'business_explorer.per_admin_day' => 10, 'business_explorer.global_day' => 20, 'business_explorer.timeout_seconds' => 8, 'business_explorer.search_timeout_seconds' => 15]);
        $this->actingAs(Admin::factory()->create(), 'admin');
    }

    public static function transportCases(): array
    {
        return [
            'connection timeout' => [28, ['connect_time' => 0], 'connection_timeout', 504],
            'response timeout' => [28, ['connect_time' => 0.02, 'appconnect_time' => 0.04], 'response_timeout', 504],
            'body timeout after HTTP headers' => [28, ['connect_time' => 0.02, 'appconnect_time' => 0.04, 'starttransfer_time' => 0.1, 'http_code' => 200], 'response_timeout', 504],
            'TLS timeout' => [28, ['connect_time' => 0.02, 'appconnect_time' => 0], 'tls_timeout', 504],
            'unknown timeout phase' => [28, [], 'transport_timeout', 504],
            'DNS' => [6, [], 'dns_failure', 503],
            'TLS' => [60, [], 'tls_failure', 503],
            'connection refused' => [7, [], 'connection_failure', 503],
        ];
    }

    #[DataProvider('transportCases')]
    public function test_transport_failures_are_classified_without_retry_or_secret_leaks(int $errno, array $stats, string $category, int $status): void
    {
        $logs = [];
        foreach (['info', 'warning'] as $level) {
            Log::shouldReceive($level)->andReturnUsing(function ($event, $data) use (&$logs): void {
                $logs[] = [$event, $data];
            });
        }
        Http::fake(function ($request, array $options) use ($errno, $stats) {
            $psr = new Request('POST', 'https://places.googleapis.com/?key=synthetic-secret-never-log');
            $options['on_stats'](new TransferStats($psr, null, 8, $errno, $stats));
            throw new ConnectException('cURL error '.$errno.' synthetic-secret-never-log', $psr);
        });
        $response = $this->postJson(route('admin.explorer.search'), ['query' => 'query-not-allowed-in-logs'])->assertStatus($status)->assertJsonPath('error_code', $category);
        $this->assertMatchesRegularExpression('/^[a-f0-9-]{36}$/', $response->json('correlation_id'));
        Http::assertSentCount(1);
        $event = AdminAuditEvent::where('action', 'business_explorer.search')->sole();
        $this->assertSame($category, $event->metadata['category']);
        $this->assertSame(1, $event->metadata['attempts']);
        $this->assertSame('reserved', $event->metadata['quota']);
        $this->assertSame($stats['http_code'] ?? null, $event->metadata['upstream_status']);
        $serialized = json_encode([$logs, $event->metadata, $response->json()]);
        foreach (['synthetic-secret-never-log', 'query-not-allowed-in-logs', 'X-Goog-Api-Key', 'https://places.googleapis.com'] as $secret) {
            $this->assertStringNotContainsString($secret, $serialized);
        }
        $completed = array_values(array_filter($logs, fn ($row) => $row[0] === 'places.http.completed'));
        $this->assertCount(1, $completed);
        $this->assertSame($errno, $completed[0][1]['curl_errno']);
        $this->assertGreaterThanOrEqual(0, $completed[0][1]['duration_ms']);
    }

    public static function upstreamCases(): array
    {
        return [[503, 503, 'upstream_unavailable'], [429, 429, 'upstream_quota'], [401, 502, 'upstream_authentication'], [403, 502, 'upstream_authentication']];
    }

    #[DataProvider('upstreamCases')]
    public function test_upstream_http_status_is_recorded_separately(int $upstream, int $local, string $category): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'secret-provider-response']], $upstream)]);
        $this->postJson(route('admin.explorer.search'), ['query' => 'cafeterias sintéticas'])->assertStatus($local)->assertJsonPath('error_code', $category)->assertDontSee('secret-provider-response');
        $event = AdminAuditEvent::where('action', 'business_explorer.search')->sole();
        $this->assertSame($upstream, $event->metadata['upstream_status']);
        Http::assertSentCount(1);
    }

    public function test_malformed_response_and_explicit_recovery_use_one_request_per_operation(): void
    {
        Http::fake(['*' => Http::sequence()->push('invalid-json')->push(['places' => []])]);
        $this->postJson(route('admin.explorer.search'), ['query' => 'cafeterias sintéticas'])->assertStatus(502)->assertJsonPath('error_code', 'malformed_response');
        $this->postJson(route('admin.explorer.search'), ['query' => 'cafeterias sintéticas'])->assertOk()->assertJsonPath('places', []);
        Http::assertSentCount(2);
        $this->assertDatabaseCount('prospects', 0);
    }

    public function test_manual_retries_exhaust_quota_without_additional_provider_requests(): void
    {
        config(['business_explorer.per_admin_minute' => 1]);
        Http::fake(['*' => Http::response([], 503)]);
        $this->postJson(route('admin.explorer.search'), ['query' => 'cafeterias sintéticas'])->assertStatus(503);
        $this->postJson(route('admin.explorer.search'), ['query' => 'cafeterias sintéticas'])->assertStatus(429)->assertJsonPath('error_code', 'internal_quota');
        Http::assertSentCount(1);
        $failure = AdminAuditEvent::where('action', 'business_explorer.search')->orderByDesc('id')->first();
        $this->assertSame(0, $failure->metadata['attempts']);
        $this->assertSame('denied', $failure->metadata['quota']);
    }

    public function test_unexpected_failure_is_sanitized_and_timeout_values_remain_bounded(): void
    {
        $attempts = 0;
        $capturedOptions = [];
        Http::fake(function ($request, array $options) use (&$attempts, &$capturedOptions) {
            $attempts++;
            $capturedOptions = $options;
            throw new \RuntimeException('synthetic-secret-never-log');
        });
        $this->postJson(route('admin.explorer.search'), ['query' => 'cafeterias sintéticas'])->assertStatus(502)->assertJsonPath('error_code', 'unexpected_transport_failure')->assertDontSee('synthetic-secret-never-log');
        $this->assertSame(1, $attempts);
        $this->assertSame(3, $capturedOptions['connect_timeout']);
        $this->assertSame(15, $capturedOptions['timeout']);
        $this->assertFalse($capturedOptions['allow_redirects']);
    }

    public function test_volatile_quota_storage_is_refused_outside_testing(): void
    {
        $admin = Admin::factory()->create();
        Http::fake();
        config(['cache.default' => 'array']);
        app()->instance('env', 'local');
        try {
            app(PlacesQuota::class)->reserve($admin);
            $this->fail('Volatile quotas must not authorize real requests.');
        } catch (PlacesUnavailable $exception) {
            $this->assertSame(503, $exception->httpStatus);
            $this->assertStringContainsString('persistente', $exception->getMessage());
        } finally {
            app()->instance('env', 'testing');
        }
        Http::assertNothingSent();
    }

    public function test_details_keeps_eight_second_timeout_and_search_is_capped_at_fifteen(): void
    {
        Http::fake(function ($request, array $options) {
            $this->assertSame(8, $options['timeout']);

            return Http::response(['id' => 'ChIJ_TIMEOUT_ONLY']);
        });
        app(GooglePlacesProvider::class)->details('ChIJ_TIMEOUT_ONLY');
        config(['business_explorer.search_timeout_seconds' => 90]);
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(function ($request, array $options) {
            $this->assertSame(15, $options['timeout']);

            return Http::response(['places' => []]);
        });
        $this->postJson(route('admin.explorer.search'), ['query' => 'cafeterias sintéticas'])->assertOk();
        Http::assertSentCount(1);
    }
}
