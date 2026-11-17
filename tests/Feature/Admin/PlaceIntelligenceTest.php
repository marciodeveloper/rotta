<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\GooglePlaceReference;
use App\Services\CreateProspect;
use App\Services\GoogleMapsLinks;
use App\Services\PlacesContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PlaceIntelligenceTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['business_explorer.google_api_key' => 'synthetic-not-real', 'business_explorer.terms_url' => 'https://example.invalid/terms', 'business_explorer.privacy_url' => 'https://example.invalid/privacy', 'business_explorer.enterprise_enabled' => true, 'business_explorer.reputation_enabled' => true, 'business_explorer.review_summary_enabled' => true]);
    }

    private function reference(Admin $admin): GooglePlaceReference
    {
        return GooglePlaceReference::create(['place_id' => 'ChIJ_SYNTHETIC_INTELLIGENCE', 'saved_by' => $admin->id, 'queried_at' => now()]);
    }

    private function input(GooglePlaceReference $reference, string $mode = 'reputation'): array
    {
        return ['reference_id' => $reference->id, 'mode' => $mode, 'cost_acknowledged' => true];
    }

    private function response(): array
    {
        return ['id' => 'ChIJ_SYNTHETIC_INTELLIGENCE', 'rating' => 4.9, 'userRatingCount' => 65,
            'googleMapsLinks' => ['reviewsUri' => 'https://www.google.com/maps/reviews?synthetic=1', 'photosUri' => 'https://www.google.com/maps/photos?synthetic=1'],
            'reviewSummary' => ['text' => ['text' => 'Resumo sintético completo. Não truncar nem reescrever.'], 'disclosureText' => ['text' => 'Resumido com o Gemini'], 'reviewsUri' => 'https://www.google.com/maps/reviews?synthetic=1', 'flagContentUri' => 'https://www.google.com/local/review/rap/report?synthetic=1']];
    }

    public function test_reputation_uses_minimal_enterprise_mask_and_does_not_persist_google_content(): void
    {
        $admin = Admin::factory()->create();
        $ref = $this->reference($admin);
        $before = $ref->fresh()->getRawOriginal();
        Http::fake(['places.googleapis.com/*' => Http::response($this->response())]);
        $response = $this->actingAs($admin, 'admin')->postJson(route('admin.explorer.intelligence'), $this->input($ref))->assertOk()
            ->assertJsonPath('intelligence.rating', 4.9)->assertJsonPath('intelligence.rating_count', 65)->assertJsonPath('intelligence.review_summary', null)
            ->assertJsonPath('intelligence.maps_links.photos', 'https://www.google.com/maps/photos?synthetic=1');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        Http::assertSent(fn (Request $r) => $r->hasHeader('X-Goog-FieldMask', 'id,attributions,rating,userRatingCount,googleMapsLinks') && $r['languageCode'] === 'pt-BR');
        Http::assertSentCount(1);
        $this->assertSame($before, $ref->fresh()->getRawOriginal());
        $this->assertStringNotContainsString('Resumo sintético', DB::table('admin_audit_events')->get()->toJson());
        $this->assertDatabaseCount('prospects', 0);
        $response->assertSessionMissing('_old_input');
    }

    public function test_summary_returns_full_text_disclosure_and_required_links_without_reviews_or_context_storage(): void
    {
        $admin = Admin::factory()->create();
        $ref = $this->reference($admin);
        Http::fake(['places.googleapis.com/*' => Http::response($this->response())]);
        $response = $this->actingAs($admin, 'admin')->postJson(route('admin.explorer.intelligence'), $this->input($ref, 'summary'))->assertOk()
            ->assertJsonPath('intelligence.review_summary.text', $this->response()['reviewSummary']['text']['text'])
            ->assertJsonPath('intelligence.review_summary.disclosure', 'Resumido com o Gemini')->assertJsonPath('intelligence.summary_status', 'available')
            ->assertJsonPath('intelligence.sku', 'Enterprise + Atmosphere');
        Http::assertSent(fn (Request $r) => $r->hasHeader('X-Goog-FieldMask', 'id,attributions,rating,userRatingCount,googleMapsLinks,reviewSummary'));
        $response->assertJsonMissingPath('intelligence.import_token');
        $this->assertStringNotContainsString('Resumo sintético', DB::table('cache')->get()->toJson());
        $this->assertStringNotContainsString('Resumido com o Gemini', DB::table('admin_audit_events')->get()->toJson());
    }

    public static function disabledFeatures(): array
    {
        return [['enterprise_enabled', 'reputation'], ['reputation_enabled', 'reputation'], ['review_summary_enabled', 'summary']];
    }

    #[DataProvider('disabledFeatures')]
    public function test_disabled_features_do_not_call_google_or_consume_quota(string $key, string $mode): void
    {
        $admin = Admin::factory()->create();
        $ref = $this->reference($admin);
        config(['business_explorer.'.$key => false]);
        $this->actingAs($admin, 'admin')->postJson(route('admin.explorer.intelligence'), $this->input($ref, $mode))->assertStatus(422);
        Http::assertNothingSent();
        $this->assertDatabaseCount('admin_audit_events', 0);
    }

    public function test_missing_summary_and_optional_values_have_neutral_states(): void
    {
        $admin = Admin::factory()->create();
        $ref = $this->reference($admin);
        Http::fake(['places.googleapis.com/*' => Http::response(['id' => $ref->place_id])]);
        $this->actingAs($admin, 'admin')->postJson(route('admin.explorer.intelligence'), $this->input($ref, 'summary'))->assertOk()
            ->assertJsonPath('intelligence.summary_status', 'unavailable')->assertJsonPath('intelligence.review_summary', null)->assertJsonPath('intelligence.rating', null)->assertJsonPath('intelligence.rating_count', null);
    }

    public function test_incomplete_or_unsafe_attribution_never_exposes_summary(): void
    {
        $admin = Admin::factory()->create();
        $ref = $this->reference($admin);
        $data = $this->response();
        $data['reviewSummary']['flagContentUri'] = 'https://google.com.evil.invalid/report';
        Http::fake(['places.googleapis.com/*' => Http::response($data)]);
        $this->actingAs($admin, 'admin')->postJson(route('admin.explorer.intelligence'), $this->input($ref, 'summary'))->assertOk()->assertJsonPath('intelligence.summary_status', 'incomplete_attribution')->assertJsonPath('intelligence.review_summary', null);
    }

    public function test_authorization_consent_invalid_modes_and_foreign_context_are_enforced(): void
    {
        $admin = Admin::factory()->create();
        $ref = $this->reference($admin);
        $this->postJson(route('admin.explorer.intelligence'), $this->input($ref))->assertUnauthorized();
        $this->actingAs($admin, 'admin')->postJson(route('admin.explorer.intelligence'), [...$this->input($ref), 'cost_acknowledged' => false])->assertUnprocessable();
        $this->postJson(route('admin.explorer.intelligence'), $this->input($ref, 'reviews'))->assertUnprocessable();
        $token = app(PlacesContext::class)->issue(Admin::factory()->create(), 'place', ['place_id' => $ref->place_id]);
        $this->postJson(route('admin.explorer.intelligence'), ['token' => $token, 'mode' => 'reputation', 'cost_acknowledged' => true])->assertUnprocessable();
        $admin->update(['is_active' => false]);
        $this->postJson(route('admin.explorer.intelligence'), $this->input($ref))->assertRedirect(route('admin.login'));
        Http::assertNothingSent();
    }

    public function test_daily_quota_is_shared_with_search_and_summary_and_errors_do_not_retry(): void
    {
        config(['business_explorer.per_admin_day' => 1]);
        $admin = Admin::factory()->create();
        $ref = $this->reference($admin);
        Http::fake(['places.googleapis.com/*' => Http::response([], 503)]);
        $this->actingAs($admin, 'admin')->postJson(route('admin.explorer.intelligence'), $this->input($ref))->assertStatus(503);
        $this->postJson(route('admin.explorer.intelligence'), $this->input($ref, 'summary'))->assertStatus(429);
        Http::assertSentCount(1);
        $this->assertDatabaseHas('admin_audit_events', ['action' => 'business_explorer.review_summary', 'metadata->quota' => 'denied', 'metadata->attempts' => 0]);
    }

    public function test_timeout_is_handled_without_retry_or_personal_content_in_audit(): void
    {
        $admin = Admin::factory()->create();
        $ref = $this->reference($admin);
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;
            throw new ConnectionException('cURL error 28: synthetic timeout');
        });
        $this->actingAs($admin, 'admin')->postJson(route('admin.explorer.intelligence'), $this->input($ref))->assertStatus(504);
        $this->assertSame(1, $attempts);
        $this->assertDatabaseHas('admin_audit_events', ['action' => 'business_explorer.reputation', 'metadata->attempts' => 1]);
    }

    public function test_csrf_is_required_outside_the_framework_test_bypass(): void
    {
        $admin = Admin::factory()->create();
        $ref = $this->reference($admin);
        $this->actingAs($admin, 'admin');
        $this->app->instance('env', 'local');
        try {
            $this->postJson(route('admin.explorer.intelligence'), $this->input($ref))->assertStatus(419);
            Http::assertNothingSent();
        } finally {
            $this->app->instance('env', 'testing');
        }
    }

    public function test_basic_details_reuse_pro_links_without_requesting_enterprise_or_summary(): void
    {
        $admin = Admin::factory()->create();
        $ref = $this->reference($admin);
        $data = $this->response();
        $data['googleMapsUri'] = ['invalid' => true];
        Http::fake(['places.googleapis.com/*' => Http::response($data)]);
        $this->actingAs($admin, 'admin')->postJson(route('admin.explorer.details'), ['reference_id' => $ref->id])->assertOk()
            ->assertJsonPath('place.maps_links.reviews', 'https://www.google.com/maps/reviews?synthetic=1')->assertJsonMissingPath('place.rating')->assertJsonMissingPath('place.review_summary');
        Http::assertSent(function (Request $request) {
            $mask = $request->header('X-Goog-FieldMask')[0];

            return str_contains($mask, 'googleMapsLinks') && ! str_contains($mask, 'reviewSummary') && ! str_contains($mask, 'rating') && ! str_contains($mask, 'reviews,');
        });
        Http::assertSentCount(1);
    }

    public function test_free_links_and_existing_crm_information_render_without_provider_calls(): void
    {
        $admin = Admin::factory()->create();
        $ref = $this->reference($admin);
        $prospect = app(CreateProspect::class)->handle(['business_name' => 'Negócio sintético próprio', 'city' => 'Vassouras', 'state' => 'RJ', 'notes' => 'Observação própria', 'submission_token' => (string) Str::uuid()], $admin);
        $ref->update(['prospect_id' => $prospect->id, 'linked_by' => $admin->id, 'linked_at' => now()]);
        $this->actingAs($admin, 'admin')->get(route('admin.prospects.show', $prospect))->assertOk()->assertSee('Inteligência Comercial')->assertSee('Obter rota para visita')->assertSee('Observação própria')->assertSee('data-intelligence-query="summary"', false);
        Http::assertNothingSent();
        $links = GoogleMapsLinks::forPlace($ref->place_id);
        $this->assertStringContainsString('destination_place_id='.$ref->place_id, $links['directions']);
        $this->assertStringContainsString('query_place_id='.$ref->place_id, $links['place']);
        $this->assertNull($links['photos']);
        $this->assertNull(GoogleMapsLinks::safe('https://google.com.evil.invalid/'));
        $this->assertNull(GoogleMapsLinks::safe('javascript:alert(1)'));
    }
}
