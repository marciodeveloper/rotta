<?php

namespace Tests\Feature\Admin;

use App\Exceptions\PlacesUnavailable;
use App\Models\Admin;
use App\Models\GooglePlaceReference;
use App\Models\Prospect;
use App\Services\AuthorizedBusinessWebsite;
use App\Services\CreateProspect;
use App\Services\CreateProspectFromPlaceReference;
use App\Services\GooglePlacesProvider;
use App\Services\ManageProspectContact;
use App\Services\PlacesContext;
use App\Services\ReferenceFieldProvenance;
use App\Services\SaveGooglePlaceReference;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BusinessExplorerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['business_explorer.page_size' => 10, 'business_explorer.max_pages' => 3, 'business_explorer.per_admin_minute' => 6, 'business_explorer.per_admin_day' => 50, 'business_explorer.global_day' => 200, 'business_explorer.google_api_key' => 'synthetic-server-key-never-real', 'business_explorer.terms_url' => 'https://example.invalid/terms', 'business_explorer.privacy_url' => 'https://example.invalid/privacy']);
    }

    private function place(string $id = 'ChIJ_SYNTHETIC'): array
    {
        return ['id' => $id, 'displayName' => ['text' => 'Cafeteria Google sintética'], 'formattedAddress' => 'Rua da Simulação, Vassouras, RJ', 'primaryTypeDisplayName' => ['text' => 'Cafeteria'], 'googleMapsUri' => 'https://maps.google.com/?cid=123', 'attributions' => [['provider' => 'Fornecedor sintético', 'providerUri' => 'https://example.invalid/source']], 'privateDebug' => 'never-store-raw-provider-response'];
    }

    private function token(Admin $admin, string $id = 'ChIJ_SYNTHETIC'): string
    {
        return app(PlacesContext::class)->issue($admin, 'place', ['place_id' => $id, 'queried_at' => now()->toIso8601String()]);
    }

    private function reference(Admin $admin, string $id = 'ChIJ_SYNTHETIC'): GooglePlaceReference
    {
        return app(SaveGooglePlaceReference::class)->handle($admin, $this->token($admin, $id));
    }

    private function input(array $extra = []): array
    {
        $input = [...['business_name' => 'Empresa com dados independentes', 'city' => 'Vassouras', 'state' => 'RJ', 'priority' => 'medium', 'origin_reference' => 'Dados fornecidos diretamente pelo estabelecimento em visita sintética', 'independent_data_confirmed' => '1', 'submission_token' => (string) Str::uuid()], ...$extra];
        $input['field_provenance'] ??= array_fill_keys(array_keys(ReferenceFieldProvenance::fields()), ['kind' => 'direct_contact', 'source' => $input['origin_reference'], 'confirmed' => '1']);

        return $input;
    }

    public function test_missing_key_and_missing_legal_configuration_are_clear_and_do_not_call_google(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');
        config(['business_explorer.google_api_key' => null]);
        $this->get(route('admin.explorer.index'))->assertOk()->assertSee('GOOGLE_PLACES_SERVER_API_KEY')->assertSee('google-maps-attribution.png');
        $this->postJson(route('admin.explorer.search'), ['query' => 'Cafeterias em Vassouras, RJ'])->assertStatus(503)->assertJsonPath('message', 'Explorador indisponível: configure GOOGLE_PLACES_SERVER_API_KEY no servidor.');
        config(['business_explorer.google_api_key' => 'synthetic-server-key-never-real', 'business_explorer.terms_url' => null]);
        $this->postJson(route('admin.explorer.search'), ['query' => 'Cafeterias em Vassouras, RJ'])->assertStatus(503)->assertJsonFragment(['message' => 'Configure as URLs públicas dos termos de uso e da política de privacidade da aplicação, incluindo o uso do Google Maps, antes de pesquisar.']);
        Http::assertNothingSent();
        $this->assertDatabaseCount('google_place_references', 0);
    }

    public function test_search_uses_server_key_minimal_pro_mask_and_never_persists_provider_content(): void
    {
        Mail::fake();
        Http::fake(['https://places.googleapis.com/v1/places:searchText' => Http::response(['places' => [$this->place()]])]);
        $this->actingAs(Admin::factory()->create(), 'admin')->get(route('admin.explorer.index'))->assertOk()->assertDontSee('synthetic-server-key-never-real');
        $response = $this->postJson(route('admin.explorer.search'), ['query' => '  Cafeterias em Vassouras, RJ  '])->assertOk()
            ->assertJsonPath('places.0.name', 'Cafeteria Google sintética')->assertJsonPath('places.0.address', 'Rua da Simulação, Vassouras, RJ')
            ->assertJsonPath('places.0.category', 'Cafeteria')->assertJsonPath('places.0.attributions.0.name', 'Fornecedor sintético')->assertJsonPath('places.0.reference', null);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('never-store-raw-provider-response', $response->getContent());
        $this->assertStringNotContainsString('synthetic-server-key-never-real', $response->getContent());
        Http::assertSent(fn (Request $request) => $request->method() === 'POST' && ! str_contains($request->url(), 'synthetic-server')
            && $request->hasHeader('X-Goog-Api-Key', 'synthetic-server-key-never-real')
            && $request->hasHeader('X-Goog-FieldMask', 'places.id,places.displayName,places.formattedAddress,places.primaryTypeDisplayName,places.googleMapsUri,places.attributions,nextPageToken')
            && $request['textQuery'] === 'Cafeterias em Vassouras, RJ' && $request['pageSize'] === 10 && $request['languageCode'] === 'pt-BR');
        Http::assertSentCount(1);
        foreach (['prospects', 'google_place_references', 'pre_registrations', 'email_outbox_messages'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertStringNotContainsString('Cafeteria Google sintética', DB::table('admin_audit_events')->get()->toJson());
        $response->assertSessionMissing('_old_input');
        Mail::assertNothingSent();
    }

    public function test_pagination_is_bound_to_the_original_query_admin_and_configured_result_limit(): void
    {
        config(['business_explorer.page_size' => 1, 'business_explorer.max_pages' => 2]);
        Http::fake(['https://places.googleapis.com/v1/places:searchText' => Http::sequence()
            ->push(['places' => [$this->place()], 'nextPageToken' => 'provider-page-token-private'])
            ->push(['places' => [$this->place('ChIJ_SECOND')], 'nextPageToken' => 'must-not-be-followed'])]);
        $admin = Admin::factory()->create();
        $first = $this->actingAs($admin, 'admin')->postJson(route('admin.explorer.search'), ['query' => 'Cafeterias em Vassouras, RJ'])->assertOk();
        $cursor = $first->json('next_cursor');
        $this->assertIsString($cursor);
        $this->assertStringNotContainsString('provider-page-token-private', $first->getContent());
        $this->actingAs(Admin::factory()->create(), 'admin')->postJson(route('admin.explorer.search'), ['cursor' => $cursor])->assertUnprocessable();
        $this->actingAs($admin, 'admin')->postJson(route('admin.explorer.search'), ['cursor' => $cursor])->assertOk()->assertJsonPath('page', 2)->assertJsonPath('next_cursor', null)->assertJsonPath('max_results', 2);
        Http::assertSent(fn (Request $request) => ($request->data()['pageToken'] ?? null) === 'provider-page-token-private' && $request['textQuery'] === 'Cafeterias em Vassouras, RJ' && $request['pageSize'] === 1);
        Http::assertSentCount(2);
    }

    public static function invalidQueries(): array
    {
        return [[''], ['  '], ['ab'], [['array']], [str_repeat('a', 201)]];
    }

    #[DataProvider('invalidQueries')]
    public function test_invalid_search_never_flashes_tokens_or_provider_input_even_without_ajax(mixed $query): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin')->post(route('admin.explorer.search'), ['query' => $query, 'provider_payload' => 'must-not-flash'])
            ->assertUnprocessable()->assertJsonValidationErrors('query')->assertSessionMissing('_old_input');
        Http::assertNothingSent();
    }

    public function test_expired_or_modified_context_cannot_fetch_details_or_save_a_reference(): void
    {
        $this->freezeTime();
        $admin = Admin::factory()->create();
        $token = $this->token($admin);
        $this->travel(16)->minutes();
        $this->actingAs($admin, 'admin')->postJson(route('admin.explorer.details'), ['token' => $token])->assertUnprocessable()->assertJsonValidationErrors('token');
        $this->post(route('admin.explorer.references.store'), ['token' => $token])->assertUnprocessable()->assertSessionMissing('_old_input');
        $this->postJson(route('admin.explorer.references.store'), ['token' => 'tampered'])->assertUnprocessable();
        $this->assertDatabaseCount('google_place_references', 0);
        Http::assertNothingSent();
    }

    public function test_other_admin_cannot_save_an_unconfirmed_reference_from_someone_elses_search(): void
    {
        $owner = Admin::factory()->create();
        $this->actingAs(Admin::factory()->create(), 'admin')->postJson(route('admin.explorer.references.store'), ['token' => $this->token($owner)])->assertUnprocessable()->assertJsonValidationErrors('token');
        $this->assertDatabaseCount('google_place_references', 0);
        Http::assertNothingSent();
    }

    public function test_detail_payload_cannot_mix_an_arbitrary_reference_and_a_search_token(): void
    {
        $admin = Admin::factory()->create();
        $reference = $this->reference($admin);
        $this->actingAs($admin, 'admin')->postJson(route('admin.explorer.details'), ['reference_id' => $reference->id, 'token' => $this->token($admin)])->assertUnprocessable()->assertJsonValidationErrors('token');
        Http::assertNothingSent();
    }

    public function test_saving_a_reference_is_idempotent_and_stores_only_exempt_identifier_and_metadata(): void
    {
        $admin = Admin::factory()->create();
        $input = ['token' => $this->token($admin), 'name' => 'forbidden-provider-name', 'address' => 'forbidden-provider-address', 'raw_response' => ['secret' => 'must-not-store']];
        $first = $this->actingAs($admin, 'admin')->postJson(route('admin.explorer.references.store'), $input)->assertOk();
        $this->postJson(route('admin.explorer.references.store'), $input)->assertOk()->assertJsonPath('reference.id', $first->json('reference.id'));
        $this->assertDatabaseCount('google_place_references', 1);
        $this->assertSame('ChIJ_SYNTHETIC', GooglePlaceReference::sole()->place_id);
        $this->assertSame($admin->id, GooglePlaceReference::sole()->saved_by);
        $this->assertStringNotContainsString('forbidden-provider', DB::table('google_place_references')->get()->toJson());
        $this->assertDatabaseCount('prospects', 0);
        $this->assertDatabaseCount('pre_registrations', 0);
        $this->assertSame(1, DB::table('admin_audit_events')->where('action', 'business_explorer.reference_saved')->count());
        Http::assertNothingSent();
    }

    public function test_place_ids_are_not_assumed_to_have_a_short_fixed_length(): void
    {
        $admin = Admin::factory()->create();
        $reference = $this->reference($admin, str_repeat('a', 400));
        $this->assertSame(str_repeat('a', 400), $reference->place_id);
    }

    public function test_details_are_fresh_and_normalization_removes_unsafe_links_and_unrequested_fields(): void
    {
        $admin = Admin::factory()->create();
        $reference = $this->reference($admin);
        $place = $this->place();
        $place['googleMapsUri'] = 'javascript:alert(1)';
        $place['attributions'][] = ['provider' => '<script>alert(1)</script>', 'providerUri' => 'javascript:alert(1)'];
        $place['attributions'][] = ['provider' => 'Legacy attribution provider', 'providerUri' => 'http://example.invalid/attribution'];
        Http::fake(['https://places.googleapis.com/v1/places/ChIJ_SYNTHETIC*' => Http::response($place)]);
        $response = $this->actingAs($admin, 'admin')->postJson(route('admin.explorer.details'), ['reference_id' => $reference->id])->assertOk()->assertJsonPath('place.maps_url', null)->assertJsonPath('place.attributions.1.url', null);
        $this->assertStringNotContainsString('privateDebug', $response->getContent());
        $response->assertJsonPath('place.attributions.2.url', 'http://example.invalid/attribution');
        Http::assertSent(fn (Request $request) => $request->method() === 'GET' && $request->hasHeader('X-Goog-FieldMask', implode(',', GooglePlacesProvider::DETAIL_FIELDS)));
        Http::assertSentCount(1);
    }

    public static function providerFailures(): array
    {
        return [[400, 422], [401, 502], [403, 502], [429, 429], [500, 503]];
    }

    #[DataProvider('providerFailures')]
    public function test_provider_errors_are_sanitized_and_do_not_retry(int $googleStatus, int $status): void
    {
        Http::fake(['https://places.googleapis.com/v1/places:searchText' => Http::response(['error' => ['message' => 'never-expose-provider-diagnostic synthetic-server-key-never-real']], $googleStatus)]);
        $response = $this->actingAs(Admin::factory()->create(), 'admin')->postJson(route('admin.explorer.search'), ['query' => 'Cafeterias em Vassouras, RJ'])->assertStatus($status);
        $this->assertStringNotContainsString('never-expose', $response->getContent());
        $this->assertStringNotContainsString('synthetic-server-key-never-real', $response->getContent());
        $this->assertStringNotContainsString('never-expose', DB::table('admin_audit_events')->get()->toJson());
        Http::assertSentCount(1);
    }

    public function test_timeouts_are_reported_without_persisting_provider_bodies(): void
    {
        Http::fake(['https://places.googleapis.com/v1/places:searchText' => Http::failedConnection()]);
        $this->actingAs(Admin::factory()->create(), 'admin')->postJson(route('admin.explorer.search'), ['query' => 'Cafeterias em Vassouras'])->assertStatus(503);
        $this->assertDatabaseCount('google_place_references', 0);
    }

    public function test_malformed_json_is_rejected_without_creating_records(): void
    {
        Http::fake(['https://places.googleapis.com/v1/places:searchText' => Http::response('not-json', 200)]);
        $this->actingAs(Admin::factory()->create(), 'admin')->postJson(route('admin.explorer.search'), ['query' => 'Cafeterias em Vassouras'])->assertStatus(502);
        $this->assertDatabaseCount('google_place_references', 0);
        Http::assertSentCount(1);
    }

    public function test_minute_day_and_global_quotas_charge_details_and_pages_and_fail_closed(): void
    {
        config(['business_explorer.per_admin_minute' => 1]);
        Http::fake(['https://places.googleapis.com/v1/places:searchText' => Http::response(['places' => [$this->place()]])]);
        $this->actingAs(Admin::factory()->create(), 'admin');
        $first = $this->postJson(route('admin.explorer.search'), ['query' => 'Cafeterias em Vassouras'])->assertOk();
        $this->postJson(route('admin.explorer.details'), ['token' => $first->json('places.0.token')])->assertStatus(429);
        Http::assertSentCount(1);
        Cache::flush();
        config(['business_explorer.per_admin_minute' => 10, 'business_explorer.per_admin_day' => 1]);
        $this->postJson(route('admin.explorer.search'), ['query' => 'Cafeterias em Vassouras'])->assertOk();
        $this->postJson(route('admin.explorer.search'), ['query' => 'Cafeterias em Vassouras'])->assertStatus(429);
        Cache::flush();
        config(['business_explorer.per_admin_day' => 10, 'business_explorer.global_day' => 1]);
        $this->postJson(route('admin.explorer.search'), ['query' => 'Cafeterias em Vassouras'])->assertOk();
        $this->actingAs(Admin::factory()->create(), 'admin')->postJson(route('admin.explorer.search'), ['query' => 'Cafeterias em Vassouras'])->assertStatus(429);
        Http::assertSentCount(3);
    }

    public function test_unavailable_quota_store_prevents_any_billable_request(): void
    {
        Cache::shouldReceive('lock')->once()->andThrow(new \RuntimeException('synthetic cache unavailable'));
        $this->actingAs(Admin::factory()->create(), 'admin')->postJson(route('admin.explorer.search'), ['query' => 'Cafeterias em Vassouras'])->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_blank_manual_form_preserves_the_independent_source_contract_and_creation_idempotency(): void
    {
        Mail::fake();
        $admin = Admin::factory()->create();
        $reference = $this->reference($admin);
        $this->actingAs($admin, 'admin')->get(route('admin.explorer.references.create', $reference))->assertOk()
            ->assertViewHas('prospect', fn (Prospect $prospect) => ! $prospect->exists && $prospect->business_name === null)
            ->assertSee('ChIJ_SYNTHETIC')->assertSee('preenchimento automático é opcional')->assertDontSee('Cafeteria Google sintética');
        $input = $this->input(['email' => 'contato@example.invalid']);
        $this->post(route('admin.explorer.references.prospects.store', $reference), $input)->assertRedirect();
        $prospect = Prospect::sole();
        $this->post(route('admin.explorer.references.prospects.store', $reference), $input)->assertRedirect(route('admin.prospects.show', $prospect));
        $this->assertSame($prospect->id, $reference->fresh()->prospect_id);
        $this->assertSame('Empresa com dados independentes', $prospect->business_name);
        $this->assertSame($input['origin_reference'], $prospect->sources()->where('submission_token', $input['submission_token'])->sole()->origin_reference);
        $this->assertSame('manual', $prospect->sources()->where('submission_token', $input['submission_token'])->sole()->entry_type);
        $this->assertNull($prospect->sources()->where('submission_token', $input['submission_token'])->sole()->provider);
        $this->assertNotSame($input['email'], DB::table('prospects')->value('email'));
        $this->assertDatabaseCount('prospects', 1);
        $this->assertDatabaseCount('prospect_activities', 2);
        $this->assertDatabaseCount('pre_registrations', 0);
        $this->assertDatabaseCount('email_outbox_messages', 0);
        Mail::assertNothingSent();
    }

    public function test_independence_attestation_and_meaningful_source_are_enforced_by_the_shared_action(): void
    {
        $admin = Admin::factory()->create();
        $reference = $this->reference($admin);
        foreach ([['independent_data_confirmed' => '0'], ['origin_reference' => '   '], ['origin_reference' => 'https://maps.google.com/?cid=123'], ['submission_token' => 'not-a-uuid']] as $bad) {
            try {
                app(CreateProspectFromPlaceReference::class)->handle($reference, $admin, $this->input($bad));
                $this->fail('Expected rejection of invalid independent provenance.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
        $this->assertDatabaseCount('prospects', 0);
        $this->assertNull($reference->fresh()->prospect_id);
    }

    public function test_invalid_manual_data_returns_to_reference_form_and_preserves_valid_fields(): void
    {
        $admin = Admin::factory()->create();
        $reference = $this->reference($admin);
        $response = $this->actingAs($admin, 'admin')->post(route('admin.explorer.references.prospects.store', $reference), $this->input(['business_name' => ['malformed']]));
        $response->assertRedirect(route('admin.explorer.references.create', $reference))->assertSessionHasErrors('business_name')->assertSessionHasInput('city', 'Vassouras');
        $this->withCookie(config('session.cookie'), session()->getId())->get(route('admin.explorer.references.create', $reference))->assertOk()->assertSee('Nome do estabelecimento deve ser um texto.');
        $this->assertDatabaseCount('prospects', 0);
    }

    public function test_reference_creation_cannot_bypass_blocked_matches_or_reuse_a_creation_token_for_another_reference(): void
    {
        $admin = Admin::factory()->create();
        $existing = app(CreateProspect::class)->handle($this->input(), $admin);
        app(ManageProspectContact::class)->handle($existing, $admin, ['blocked' => true, 'lock_version' => 1, 'reason' => 'Bloqueio sintético para regressão do Explorador.']);
        $reference = $this->reference($admin);
        $this->actingAs($admin, 'admin')->post(route('admin.explorer.references.prospects.store', $reference), $this->input())->assertSessionHasErrors('blocked_match_acknowledged');
        $this->assertNull($reference->fresh()->prospect_id);
        $other = $this->reference($admin, 'ChIJ_OTHER');
        $this->post(route('admin.explorer.references.prospects.store', $other), $this->input(['submission_token' => $existing->creation_token]))->assertSessionHasErrors('submission_token');
        $this->assertDatabaseCount('prospects', 1);
        $this->assertNotNull($existing->fresh()->contact_blocked_at);
    }

    public function test_search_distinguishes_linked_place_ids_from_name_only_possible_duplicates(): void
    {
        $admin = Admin::factory()->create();
        $reference = $this->reference($admin);
        $prospect = app(CreateProspectFromPlaceReference::class)->handle($reference, $admin, $this->input(['business_name' => 'Cafeteria Google sintética']));
        Http::fake(['https://places.googleapis.com/v1/places:searchText' => Http::response(['places' => [$this->place(), $this->place('ChIJ_DISTINCT_BRANCH')]])]);
        $this->actingAs($admin, 'admin')->postJson(route('admin.explorer.search'), ['query' => 'Cafeterias em Vassouras'])->assertOk()
            ->assertJsonPath('places.0.linked_prospect.url', route('admin.prospects.show', $prospect))
            ->assertJsonPath('places.1.linked_prospect', null)->assertJsonPath('places.1.possible_duplicates.0.name', 'Cafeteria Google sintética');
        $this->assertDatabaseCount('prospects', 1);
        $this->assertDatabaseCount('google_place_references', 1);
    }

    public function test_all_explorer_routes_and_policies_reject_guests_and_inactive_admins(): void
    {
        $reference = $this->reference(Admin::factory()->create());
        $this->get(route('admin.explorer.references.create', $reference))->assertRedirect(route('admin.login'));
        $this->post(route('admin.explorer.references.prospects.store', $reference), [])->assertRedirect(route('admin.login'));
        foreach (['search' => ['query' => 'Cafeterias'], 'details' => ['token' => 'invalid'], 'references.store' => ['token' => 'invalid']] as $action => $input) {
            $this->post(route('admin.explorer.'.$action), $input)->assertRedirect(route('admin.login'));
        }
        $this->get(route('admin.explorer.index'))->assertRedirect(route('admin.login'));
        $inactive = Admin::factory()->create(['is_active' => false]);
        $this->actingAs($inactive, 'admin')->get(route('admin.explorer.index'))->assertRedirect(route('admin.login'));
        foreach (['search' => ['query' => 'Cafeterias'], 'details' => ['reference_id' => $reference->id], 'references.store' => ['token' => $this->token($inactive)]] as $action => $input) {
            $this->actingAs($inactive, 'admin')->post(route('admin.explorer.'.$action), $input)->assertRedirect(route('admin.login'));
        }
        $this->assertFalse(Gate::forUser($inactive)->allows('useForProspect', $reference));
        foreach (['viewAny', 'create'] as $ability) {
            $this->assertFalse(Gate::forUser($inactive)->allows($ability, GooglePlaceReference::class));
        }
        try {
            app(SaveGooglePlaceReference::class)->handle($inactive, $this->token($inactive));
            $this->fail('Expected service authorization.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('google_place_references', 1);
        }
        Http::assertNothingSent();
    }

    public function test_new_migration_can_be_reversed_and_reapplied_without_changing_legacy_prospects(): void
    {
        $admin = Admin::factory()->create();
        $prospect = app(CreateProspect::class)->handle($this->input(), $admin);
        $before = DB::table('prospects')->where('id', $prospect->id)->first();
        $migration = require database_path('migrations/2026_11_17_042854_create_google_place_references_table.php');
        $migration->down();
        $migration->up();
        $this->assertEquals($before, DB::table('prospects')->where('id', $prospect->id)->first());
        $this->assertDatabaseCount('prospect_sources', 1);
        $this->assertDatabaseCount('prospect_activities', 1);
        $this->assertDatabaseCount('google_place_references', 0);
    }

    public function test_assisted_form_opens_without_billable_get_and_does_not_prefill_google_content(): void
    {
        $admin = Admin::factory()->create();
        $reference = $this->reference($admin);
        $this->actingAs($admin, 'admin')->get(route('admin.explorer.references.create', $reference))
            ->assertOk()->assertSee('Consultar resumo Google')->assertSee('Buscar dados e preencher cadastro')
            ->assertDontSee('name="field_provenance[', false)->assertDontSee('data-enrichment-enterprise', false)
            ->assertSee('Origem dos dados')->assertSee('name="assisted_mode" value="simple"', false);
        Http::assertNothingSent();
    }

    public function test_expanded_details_remain_pro_and_are_temporary_even_with_full_address(): void
    {
        $admin = Admin::factory()->create();
        $reference = $this->reference($admin);
        $place = [...$this->place(), 'businessStatus' => 'OPERATIONAL', 'primaryType' => 'cafe', 'types' => ['cafe', 'store'],
            'addressComponents' => [
                ['longText' => 'Rua Sintética', 'types' => ['route']], ['longText' => '42', 'types' => ['street_number']],
                ['longText' => 'Vassouras', 'types' => ['administrative_area_level_2']],
                ['longText' => 'Rio de Janeiro', 'shortText' => 'RJ', 'types' => ['administrative_area_level_1']],
                ['longText' => '27700-000', 'types' => ['postal_code']], ['longText' => 'Brasil', 'types' => ['country']],
            ], 'nationalPhoneNumber' => 'forbidden-enterprise', 'websiteUri' => 'https://forbidden.invalid', 'location' => ['latitude' => 1]];
        Http::fake(['https://places.googleapis.com/v1/places/*' => Http::response($place)]);
        $response = $this->actingAs($admin, 'admin')->postJson(route('admin.explorer.details'), ['reference_id' => $reference->id])
            ->assertOk()->assertJsonPath('place.address_fields.city', 'Vassouras')->assertJsonPath('place.address_fields.state', 'RJ')
            ->assertJsonPath('place.address_fields.street_number', '42')->assertJsonPath('place.business_status', 'Em operação');
        $this->assertStringNotContainsString('forbidden-enterprise', $response->getContent());
        Http::assertSent(function (Request $request): bool {
            $mask = $request->header('X-Goog-FieldMask')[0];

            return str_contains($mask, 'addressComponents') && ! preg_match('/nationalPhoneNumber|internationalPhoneNumber|websiteUri|OpeningHours|location|\*/', $mask);
        });
        $this->assertDatabaseCount('prospects', 0);
        $this->assertStringNotContainsString('Rua Sintética', DB::table('google_place_references')->get()->toJson());
        $this->assertStringNotContainsString('Rua Sintética', DB::table('admin_audit_events')->get()->toJson());
    }

    public function test_details_with_no_optional_fields_are_supported_and_count_towards_existing_quota(): void
    {
        $admin = Admin::factory()->create();
        $reference = $this->reference($admin);
        config(['business_explorer.per_admin_minute' => 1]);
        Http::fake(['https://places.googleapis.com/v1/places/*' => Http::response(['id' => $reference->place_id])]);
        $this->actingAs($admin, 'admin')->postJson(route('admin.explorer.details'), ['reference_id' => $reference->id])
            ->assertOk()->assertJsonPath('place.address_fields.city', '')->assertJsonPath('place.business_status', 'Não informado');
        $this->postJson(route('admin.explorer.details'), ['reference_id' => $reference->id])->assertStatus(429);
        Http::assertSentCount(1);
    }

    public static function invalidFieldSources(): array
    {
        return [['google', 'Resultado Google Maps conferido', '1'], ['direct_contact', 'https://maps.google.com/maps/123', '1'],
            ['direct_contact', '          ', '1'], ['direct_contact', ['malformed'], '1'],
            ['direct_contact', 'Contato direto do estabelecimento', '0']];
    }

    #[DataProvider('invalidFieldSources')]
    public function test_restricted_or_unconfirmed_field_provenance_is_rejected_without_writes(string $kind, mixed $source, string $confirmed): void
    {
        $admin = Admin::factory()->create();
        $reference = $this->reference($admin);
        $input = $this->input();
        $input['field_provenance']['business_name'] = compact('kind', 'source', 'confirmed');
        $this->actingAs($admin, 'admin')->post(route('admin.explorer.references.prospects.store', $reference), $input)
            ->assertRedirect(route('admin.explorer.references.create', $reference))->assertSessionHasErrors();
        $this->get(route('admin.explorer.references.create', $reference))->assertOk()->assertSee('Vassouras');
        $this->assertDatabaseCount('prospects', 0);
        $this->assertDatabaseCount('email_outbox_messages', 0);
        $this->assertNull($reference->fresh()->prospect_id);
    }

    public function test_per_field_sources_are_encrypted_audited_and_idempotent_and_cannot_be_replaced_on_retry(): void
    {
        $admin = Admin::factory()->create();
        $reference = $this->reference($admin);
        $input = $this->input(['phone' => '24999990000']);
        $input['field_provenance']['phone'] = ['kind' => 'licensed_source', 'source' => 'Arquivo comercial próprio; licença sintética de conservação 2026', 'confirmed' => '1'];
        $action = app(CreateProspectFromPlaceReference::class);
        $prospect = $action->handle($reference, $admin, $input);
        $this->assertSame($prospect->id, $action->handle($reference, $admin, $input)->id);
        $this->assertDatabaseCount('prospects', 1);
        $this->assertSame(3, $prospect->sources()->count());
        $this->assertCount(4, $prospect->confirmed_fields);
        $sourceId = $prospect->confirmed_fields['phone']['source_id'];
        $this->assertSame($input['field_provenance']['phone']['source'], $prospect->sources()->findOrFail($sourceId)->origin_reference);
        $this->assertStringNotContainsString('Arquivo comercial próprio', DB::table('prospect_sources')->get()->toJson());
        $this->assertStringNotContainsString('Arquivo comercial próprio', DB::table('admin_audit_events')->get()->toJson());
        $activity = $prospect->activities()->where('event', 'google_reference_linked')->sole();
        $this->assertSame($sourceId, $activity->metadata['field_sources']['phone']['source_id']);
        $input['field_provenance']['phone']['source'] = 'Outra fonte independente mas alteração em reenvio';
        try {
            $action->handle($reference, $admin, $input);
            $this->fail('Expected provenance replay rejection.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('submission_token', $exception->errors());
        }
        $this->assertSame(3, $prospect->sources()->count());
        $this->assertDatabaseCount('prospect_activities', 2);
    }

    public function test_direct_service_rejects_absent_provenance_and_existing_links_cannot_create_new_prospects(): void
    {
        $admin = Admin::factory()->create();
        $reference = $this->reference($admin);
        $input = $this->input();
        unset($input['field_provenance']);
        try {
            app(CreateProspectFromPlaceReference::class)->handle($reference, $admin, $input);
            $this->fail('Expected missing field provenance rejection.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('field_provenance', $exception->errors());
        }
        $prospect = app(CreateProspectFromPlaceReference::class)->handle($reference, $admin, $this->input());
        $this->actingAs($admin, 'admin')->get(route('admin.explorer.references.create', $reference))->assertRedirect(route('admin.prospects.show', $prospect));
        $this->post(route('admin.explorer.references.prospects.store', $reference), $this->input())->assertSessionHasErrors('submission_token');
        $this->assertDatabaseCount('prospects', 1);
        Http::assertNothingSent();
    }

    #[DataProvider('providerFailures')]
    public function test_assisted_details_provider_failure_preserves_reference_and_never_creates_prospects(int $googleStatus, int $status): void
    {
        $admin = Admin::factory()->create();
        $reference = $this->reference($admin);
        Http::fake(['https://places.googleapis.com/v1/places/*' => Http::response(['error' => ['message' => 'do-not-expose-synthetic-provider-details']], $googleStatus)]);
        $response = $this->actingAs($admin, 'admin')->postJson(route('admin.explorer.details'), ['reference_id' => $reference->id])->assertStatus($status);
        $this->assertStringNotContainsString('do-not-expose', $response->getContent());
        $this->assertDatabaseCount('prospects', 0);
        $this->assertNull($reference->fresh()->prospect_id);
        Http::assertSentCount(1);
    }

    public function test_assisted_details_timeout_does_not_retry_or_clear_reference(): void
    {
        $admin = Admin::factory()->create();
        $reference = $this->reference($admin);
        Http::fake(['https://places.googleapis.com/v1/places/*' => Http::failedConnection()]);
        $this->actingAs($admin, 'admin')->postJson(route('admin.explorer.details'), ['reference_id' => $reference->id])->assertStatus(503);
        $this->assertNull($reference->fresh()->prospect_id);
        $this->assertDatabaseCount('prospects', 0);
        Http::assertSentCount(1);
    }

    private function configureIndependentWebsite(array $changes = []): void
    {
        config(['business_explorer.independent_sources.ChIJ_SYNTHETIC' => [...[
            'url' => 'https://business.example.invalid/contact', 'storage_permitted' => true, 'identity_verified' => true,
            'expected_name' => 'Estabelecimento independente sintético', 'permission_reference' => 'Autorização sintética de conservação comercial 2026',
        ], ...$changes]]);
        $this->partialMock(AuthorizedBusinessWebsite::class, function ($mock): void {
            $mock->shouldReceive('resolveAddresses')->andReturn(['93.184.216.34']);
        });
    }

    private function independentHtml(array $changes = [], string $links = ''): string
    {
        $data = [...['@context' => 'https://schema.org', '@type' => 'CafeOrCoffeeShop', 'name' => 'Estabelecimento independente sintético',
            'telephone' => '(24) 99999-0000', 'email' => 'contato@example.invalid',
            'address' => ['streetAddress' => 'Rua Independente, 123', 'addressLocality' => 'Vassouras', 'addressRegion' => 'rj', 'postalCode' => '27700-000'],
            'sameAs' => ['https://www.instagram.com/cafe_independente/?utm_source=site'],
            'additionalProperty' => [['name' => 'Segmento', 'value' => 'Cafeteria'], ['name' => 'Bairro', 'value' => 'Centro'], ['name' => 'Complemento', 'value' => 'Loja 2']]], ...$changes];

        return '<html><script type="application/ld+json">'.json_encode($data, JSON_THROW_ON_ERROR).'</script>'.$links.'</html>';
    }

    private function fakeEnrichment(string $html, array $google = []): void
    {
        Http::fake([
            'https://places.googleapis.com/v1/places/*' => Http::response([...$this->place(), ...$google]),
            'https://business.example.invalid/robots.txt' => Http::response("User-agent: *\nAllow: /", 200, ['Content-Type' => 'text/plain']),
            'https://business.example.invalid/contact' => Http::response($html, 200, ['Content-Type' => 'text/html']),
        ]);
    }

    private function enrichmentUrl(GooglePlaceReference $reference): string
    {
        return route('admin.explorer.references.enrich', $reference);
    }

    public function test_enrichment_fills_all_commercial_fields_from_independent_website_and_signs_provenance(): void
    {
        $this->configureIndependentWebsite();
        $html = $this->independentHtml();
        $this->fakeEnrichment($html);
        $admin = Admin::factory()->create();
        $reference = $this->reference($admin);
        $response = $this->actingAs($admin, 'admin')->postJson($this->enrichmentUrl($reference), ['enterprise' => false, 'cost_acknowledged' => true])->assertOk()
            ->assertJsonPath('fields.business_name', 'Estabelecimento independente sintético')->assertJsonPath('fields.phone', '24999990000')
            ->assertJsonPath('fields.street', 'Rua Independente')->assertJsonPath('fields.street_number', '123')->assertJsonPath('fields.postal_code', '27700000')
            ->assertJsonPath('fields.state', 'RJ')->assertJsonPath('fields.instagram', 'https://instagram.com/cafe_independente')
            ->assertJsonPath('google.name', 'Cafeteria Google sintética');
        $fields = $response->json('fields');
        $this->assertCount(13, $fields);
        $this->assertSame(array_keys(ReferenceFieldProvenance::fields()), array_keys(array_intersect_key(ReferenceFieldProvenance::fields(), $fields)));
        $input = [...$fields, 'priority' => 'medium', 'submission_token' => (string) Str::uuid(), 'independent_data_confirmed' => '1', 'assisted_mode' => 'simple', 'enrichment_token' => $response->json('enrichment_token')];
        $this->post(route('admin.explorer.references.prospects.store', $reference), $input)->assertRedirect();
        $prospect = Prospect::sole();
        $this->assertSame($fields['business_name'], $prospect->business_name);
        $this->assertNotSame($response->json('google.name'), $prospect->business_name);
        $this->assertCount(13, $prospect->confirmed_fields);
        $metadata = $prospect->activities()->where('event', 'google_reference_linked')->sole()->metadata;
        $this->assertSame(hash('sha256', $html), $metadata['field_sources']['instagram']['evidence']);
        $this->assertSame('licensed_source', $metadata['field_sources']['business_name']['kind']);
        $this->assertStringNotContainsString('Rua Independente', DB::table('admin_audit_events')->get()->toJson());
        $this->assertStringNotContainsString('schema.org', DB::table('prospect_sources')->get()->toJson());
        $this->assertDatabaseCount('pre_registrations', 0);
        $this->assertDatabaseCount('email_outbox_messages', 0);
        $this->post(route('admin.explorer.references.prospects.store', $reference), $input)->assertRedirect();
        $this->assertDatabaseCount('prospects', 1);
        Http::assertSentCount(3);
    }

    public function test_enterprise_is_explicit_disabled_by_default_and_never_used_by_regular_details(): void
    {
        $admin = Admin::factory()->create();
        $reference = $this->reference($admin);
        config(['business_explorer.enterprise_enabled' => false]);
        $this->actingAs($admin, 'admin')->postJson($this->enrichmentUrl($reference), ['enterprise' => true, 'cost_acknowledged' => true])->assertUnprocessable();
        Http::assertNothingSent();
        config(['business_explorer.enterprise_enabled' => true]);
        $this->fakeEnrichment('', ['rating' => 4.9, 'userRatingCount' => 65, 'nationalPhoneNumber' => '(24) 99999-0000', 'websiteUri' => 'https://business.example.invalid', 'regularOpeningHours' => ['weekdayDescriptions' => ['Segunda: 08:00–18:00']]]);
        $response = $this->postJson($this->enrichmentUrl($reference), ['enterprise' => true, 'cost_acknowledged' => true])->assertOk()
            ->assertJsonPath('google.rating', 4.9)->assertJsonPath('google.rating_count', 65)->assertJsonPath('google.sku', 'Enterprise')
            ->assertJsonPath('fields', []);
        $this->assertStringContainsString('Instagram não identificado', $response->json('message'));
        Http::assertSent(function (Request $request): bool {
            $mask = $request->header('X-Goog-FieldMask')[0];

            return str_contains($mask, 'rating,userRatingCount') && str_contains($mask, 'nationalPhoneNumber') && ! str_contains($mask, 'reviews') && ! str_contains($mask, '*');
        });
        $this->postJson(route('admin.explorer.details'), ['reference_id' => $reference->id])->assertOk()->assertJsonMissingPath('place.rating');
        Http::assertSentCount(2);
        $this->assertDatabaseCount('prospects', 0);
        $this->assertStringNotContainsString('4.9', DB::table('google_place_references')->get()->toJson());
    }

    public function test_simple_manual_entry_does_not_require_external_attestation_or_trust_forged_field_provenance(): void
    {
        $admin = Admin::factory()->create();
        $reference = $this->reference($admin);
        $input = $this->input(['assisted_mode' => 'simple', 'field_provenance' => ['business_name' => ['kind' => 'google', 'confirmed' => true, 'source' => 'Google copied']]]);
        unset($input['origin_reference']);
        $input['independent_data_confirmed'] = '0';
        $this->actingAs($admin, 'admin')->post(route('admin.explorer.references.prospects.store', $reference), $input)->assertRedirect();
        $this->assertDatabaseCount('prospects', 1);
        $this->post(route('admin.explorer.references.prospects.store', $reference), $input)->assertRedirect();
        $prospect = Prospect::sole();
        $this->assertCount(0, $prospect->confirmed_fields);
        $metadata = $prospect->activities()->where('event', 'google_reference_linked')->sole()->metadata;
        $this->assertArrayNotHasKey('business_name', $metadata['field_sources']);
        $this->assertContains('business_name', $metadata['declared_fields']);
        Http::assertNothingSent();
    }

    public function test_signed_independent_proof_is_bound_to_admin_reference_values_and_expiry(): void
    {
        $this->configureIndependentWebsite();
        $this->fakeEnrichment($this->independentHtml());
        $admin = Admin::factory()->create();
        $reference = $this->reference($admin);
        $this->actingAs($admin, 'admin');
        $data = $this->postJson($this->enrichmentUrl($reference), ['enterprise' => false, 'cost_acknowledged' => true])->assertOk()->json();
        $input = $this->input(['assisted_mode' => 'simple', 'enrichment_token' => $data['enrichment_token']]);
        $other = $this->reference($admin, 'ChIJ_OTHER');
        $this->post(route('admin.explorer.references.prospects.store', $other), $input)->assertSessionHasErrors('enrichment_token');
        $this->actingAs(Admin::factory()->create(), 'admin')->post(route('admin.explorer.references.prospects.store', $reference), $input)->assertSessionHasErrors('token');
        $this->actingAs($admin, 'admin');
        $this->post(route('admin.explorer.references.prospects.store', $reference), [...$input, 'enrichment_token' => 'forged'])->assertSessionHasErrors('token');
        $this->travel(16)->minutes();
        $this->post(route('admin.explorer.references.prospects.store', $reference), $input)->assertSessionHasErrors('token');
        $this->assertDatabaseCount('prospects', 0);
    }

    public function test_edited_independent_field_is_recorded_as_manual_and_valid_data_survives_error(): void
    {
        $this->configureIndependentWebsite();
        $this->fakeEnrichment($this->independentHtml());
        $admin = Admin::factory()->create();
        $reference = $this->reference($admin);
        $this->actingAs($admin, 'admin');
        $data = $this->postJson($this->enrichmentUrl($reference), ['enterprise' => false, 'cost_acknowledged' => true])->assertOk()->json();
        $input = [...$this->input(), ...$data['fields'], 'assisted_mode' => 'simple', 'enrichment_token' => $data['enrichment_token'], 'business_name' => 'Nome corrigido independente'];
        $this->post(route('admin.explorer.references.prospects.store', $reference), [...$input, 'city' => ['malformed']])->assertSessionHasErrors('city')->assertSessionHasInput('business_name', 'Nome corrigido independente');
        $this->get(route('admin.explorer.references.create', $reference))->assertOk()->assertSee('Nome corrigido independente')->assertDontSee('name="field_provenance[', false);
        $this->post(route('admin.explorer.references.prospects.store', $reference), $input)->assertRedirect();
        $metadata = Prospect::sole()->activities()->where('event', 'google_reference_linked')->sole()->metadata;
        $this->assertArrayNotHasKey('business_name', $metadata['field_sources']);
        $this->assertContains('business_name', $metadata['declared_fields']);
        $this->assertSame('licensed_source', $metadata['field_sources']['phone']['kind']);
    }

    public static function unsafeWebsiteAddresses(): array
    {
        return [[['127.0.0.1']], [['10.0.0.8']], [['169.254.169.254']], [['172.17.0.1']], [['192.168.1.2']], [['0.0.0.0']], [['100.64.0.1']], [['198.18.0.1']], [['224.0.0.1']], [['192.0.2.1']], [['::1']], [['fc00::1']], [['fe80::1']], [['ff02::1']], [['2001:db8::1']], [['::ffff:127.0.0.1']], [['93.184.216.34', '127.0.0.1']], [[]]];
    }

    #[DataProvider('unsafeWebsiteAddresses')]
    public function test_independent_website_blocks_private_reserved_mixed_and_unresolved_dns(array $addresses): void
    {
        $this->configureIndependentWebsite();
        $website = \Mockery::mock(AuthorizedBusinessWebsite::class)->makePartial();
        $website->shouldReceive('resolveAddresses')->once()->andReturn($addresses);
        try {
            $website->lookup('ChIJ_SYNTHETIC');
            $this->fail('Expected unsafe destination rejection.');
        } catch (PlacesUnavailable $exception) {
            $this->assertSame(422, $exception->httpStatus);
        }
        Http::assertNothingSent();
    }

    public static function websiteRefusals(): array
    {
        return [['redirect'], ['oversized'], ['executable'], ['robots'], ['timeout'], ['identity'], ['ambiguous_instagram'], ['post_link']];
    }

    #[DataProvider('websiteRefusals')]
    public function test_website_safety_and_identity_failures_never_produce_unverified_instagram(string $case): void
    {
        $this->configureIndependentWebsite();
        $html = $this->independentHtml($case === 'identity' ? ['name' => 'Outra empresa'] : ($case === 'post_link' ? ['sameAs' => ['https://instagram.com/p/not-a-profile']] : []), $case === 'ambiguous_instagram' ? '<a href="https://instagram.com/another_company">Outro</a>' : '');
        $response = match ($case) {
            'redirect' => Http::response('', 302, ['Location' => 'http://169.254.169.254/']),
            'oversized' => Http::response(str_repeat('x', 262145), 200, ['Content-Type' => 'text/html']),
            'executable' => Http::response('binary', 200, ['Content-Type' => 'application/octet-stream']),
            'timeout' => Http::failedConnection(),
            default => Http::response($html, 200, ['Content-Type' => 'text/html']),
        };
        Http::fake(['https://business.example.invalid/robots.txt' => Http::response($case === 'robots' ? "User-agent: *\nDisallow: /" : '', 200, ['Content-Type' => 'text/plain']), 'https://business.example.invalid/contact' => $response]);
        try {
            $result = app(AuthorizedBusinessWebsite::class)->lookup('ChIJ_SYNTHETIC');
            $this->assertArrayNotHasKey('instagram', $result['fields']);
            if ($case === 'identity') {
                $this->assertSame([], $result['fields']);
            }
            $this->assertContains($case, ['identity', 'ambiguous_instagram', 'post_link']);
        } catch (PlacesUnavailable) {
            $this->assertNotContains($case, ['identity', 'ambiguous_instagram', 'post_link']);
        }
        Http::assertSentCount($case === 'robots' ? 1 : 2);
    }

    public function test_unlicensed_source_is_not_fetched_and_google_quota_failure_does_not_block_licensed_data(): void
    {
        $this->configureIndependentWebsite(['storage_permitted' => false]);
        $this->assertSame([], app(AuthorizedBusinessWebsite::class)->lookup('ChIJ_SYNTHETIC')['fields']);
        Http::assertNothingSent();
        $this->configureIndependentWebsite();
        $this->fakeEnrichment($this->independentHtml());
        $admin = Admin::factory()->create();
        $reference = $this->reference($admin);
        config(['business_explorer.global_day' => 0]);
        $this->actingAs($admin, 'admin')->postJson($this->enrichmentUrl($reference), ['enterprise' => false, 'cost_acknowledged' => true])->assertOk()
            ->assertJsonPath('google', null)->assertJsonPath('fields.city', 'Vassouras')->assertJsonCount(1, 'warnings');
        Http::assertSentCount(2);
    }

    public function test_enrichment_requires_authentication_acknowledgement_and_reuses_already_linked_crm_record(): void
    {
        $admin = Admin::factory()->create();
        $reference = $this->reference($admin);
        $this->post($this->enrichmentUrl($reference), [])->assertRedirect(route('admin.login'));
        $this->actingAs($admin, 'admin')->postJson($this->enrichmentUrl($reference), ['enterprise' => false])->assertJsonValidationErrors('cost_acknowledged');
        app(CreateProspectFromPlaceReference::class)->handle($reference, $admin, $this->input());
        $this->postJson($this->enrichmentUrl($reference), ['enterprise' => false, 'cost_acknowledged' => true])->assertOk()->assertJsonPath('outcome', 'existing')->assertJsonPath('existing_prospect_url', route('admin.prospects.show', $reference->fresh()->prospect_id));
        Http::assertNothingSent();
    }

    public function test_simple_entry_cannot_bypass_contact_suppression(): void
    {
        $admin = Admin::factory()->create();
        $prospect = app(CreateProspect::class)->handle($this->input(), $admin);
        app(ManageProspectContact::class)->handle($prospect, $admin, ['blocked' => true, 'lock_version' => 1, 'reason' => 'Bloqueio sintético para novo fluxo simples']);
        $reference = $this->reference($admin);
        $this->actingAs($admin, 'admin')->post(route('admin.explorer.references.prospects.store', $reference), $this->input(['assisted_mode' => 'simple']))->assertSessionHasErrors('blocked_match_acknowledged');
        $this->get(route('admin.explorer.references.create', $reference))->assertOk()->assertSee('Decisões de revisão')->assertSee('Contato bloqueado');
        $this->assertDatabaseCount('prospects', 1);
        $this->assertNull($reference->fresh()->prospect_id);
        $this->assertNotNull($prospect->fresh()->contact_blocked_at);
    }

    public function test_website_pins_dns_disables_proxy_and_limits_response_transport(): void
    {
        $this->configureIndependentWebsite();
        $html = $this->independentHtml();
        Http::fake(function (Request $request, array $options) use ($html) {
            $this->assertSame(['business.example.invalid:443:93.184.216.34'], $options['curl'][CURLOPT_RESOLVE]);
            $this->assertSame('', $options['proxy']);
            $this->assertFalse($options['stream'] ?? false);
            $this->assertFalse($options['decode_content']);
            $this->assertFalse($options['allow_redirects']);
            $this->assertLessThanOrEqual(5, $options['timeout']);
            $this->assertIsCallable($options['progress']);

            return str_ends_with(parse_url($request->url(), PHP_URL_PATH), 'robots.txt')
                ? Http::response('', 200, ['Content-Type' => 'text/plain']) : Http::response($html, 200, ['Content-Type' => 'text/html']);
        });
        $result = app(AuthorizedBusinessWebsite::class)->lookup('ChIJ_SYNTHETIC');
        $this->assertSame('https://instagram.com/cafe_independente', $result['fields']['instagram']);
        Http::assertSentCount(2);
    }

    public function test_missing_source_returns_specific_reason_and_performs_no_google_or_website_calls(): void
    {
        config(['business_explorer.independent_sources' => [], 'business_explorer.enterprise_enabled' => false]);
        $admin = Admin::factory()->create();
        $reference = $this->reference($admin);
        $this->actingAs($admin, 'admin');
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $response = $this->postJson($this->enrichmentUrl($reference), ['enterprise' => false, 'cost_acknowledged' => true])->assertOk()
                ->assertJsonPath('fields', [])->assertJsonPath('google', null)->assertJsonPath('outcome', 'unavailable')
                ->assertJsonPath('reason_code', 'source_not_configured')->assertJsonPath('enrichment_token', '');
            $this->assertStringContainsString('Dados Google estão disponíveis somente para consulta', $response->json('message'));
        }
        Http::assertNothingSent();
        $this->assertDatabaseCount('prospects', 0);
        $this->assertDatabaseCount('email_outbox_messages', 0);
    }

    public function test_unverified_permission_is_explicit_and_does_not_make_external_calls(): void
    {
        $this->configureIndependentWebsite(['identity_verified' => false]);
        $admin = Admin::factory()->create();
        $reference = $this->reference($admin);
        $this->actingAs($admin, 'admin')->postJson($this->enrichmentUrl($reference), ['enterprise' => false, 'cost_acknowledged' => true])->assertOk()
            ->assertJsonPath('reason_code', 'source_permission_unverified')->assertJsonPath('fields', []);
        Http::assertNothingSent();
    }

    public static function permittedDnsAnswers(): array
    {
        return [[['93.184.216.34', '2606:4700:4700::1111'], 'business.example.invalid:443:93.184.216.34'],
            [['2606:4700:4700::1111'], 'business.example.invalid:443:[2606:4700:4700::1111]']];
    }

    #[DataProvider('permittedDnsAnswers')]
    public function test_public_ipv6_and_dual_stack_do_not_block_authorized_websites(array $addresses, string $pin): void
    {
        $this->configureIndependentWebsite();
        $website = \Mockery::mock(AuthorizedBusinessWebsite::class)->makePartial();
        $website->shouldReceive('resolveAddresses')->once()->andReturn($addresses);
        $html = $this->independentHtml();
        Http::fake(function (Request $request, array $options) use ($pin, $html) {
            $this->assertSame([$pin], $options['curl'][CURLOPT_RESOLVE]);

            return str_ends_with(parse_url($request->url(), PHP_URL_PATH), 'robots.txt')
                ? Http::response('', 200, ['Content-Type' => 'text/plain']) : Http::response($html, 200, ['Content-Type' => 'text/html']);
        });
        $this->assertSame('Vassouras', $website->lookup('ChIJ_SYNTHETIC')['fields']['city']);
        Http::assertSentCount(2);
    }

    public function test_authorized_organization_without_postal_address_can_supply_name_and_unique_contact_links(): void
    {
        $this->configureIndependentWebsite();
        $html = '<html><script type="application/ld+json">'.json_encode(['@type' => 'https://schema.org/Organization', 'name' => 'Estabelecimento independente sintético']).'</script><a href="tel:+5524999990000">Ligar</a><a href="mailto:contato@example.invalid?subject=Contato">Contato</a></html>';
        $this->fakeEnrichment($html);
        $result = app(AuthorizedBusinessWebsite::class)->lookup('ChIJ_SYNTHETIC');
        $this->assertSame('Estabelecimento independente sintético', $result['fields']['business_name']);
        $this->assertSame('5524999990000', $result['fields']['phone']);
        $this->assertSame('contato@example.invalid', $result['fields']['email']);
        $this->assertArrayNotHasKey('city', $result['fields']);
        Http::assertSentCount(2);
    }

    public function test_no_source_does_not_issue_expiring_proof_or_block_later_manual_creation(): void
    {
        config(['business_explorer.independent_sources' => []]);
        $admin = Admin::factory()->create();
        $reference = $this->reference($admin);
        $this->actingAs($admin, 'admin')->postJson($this->enrichmentUrl($reference), ['enterprise' => false, 'cost_acknowledged' => true])->assertOk()->assertJsonPath('enrichment_token', '');
        $this->travel(16)->minutes();
        $this->post(route('admin.explorer.references.prospects.store', $reference), $this->input(['assisted_mode' => 'simple', 'enrichment_token' => '']))->assertRedirect();
        $this->assertDatabaseCount('prospects', 1);
        $this->assertDatabaseCount('email_outbox_messages', 0);
        Http::assertNothingSent();
    }

    public function test_dns_resolution_error_is_sanitized_and_does_not_crash_real_source_lookup(): void
    {
        $this->configureIndependentWebsite();
        $website = \Mockery::mock(AuthorizedBusinessWebsite::class)->makePartial();
        $website->shouldReceive('resolveAddresses')->once()->andThrow(new \ErrorException('synthetic resolver diagnostics must not leak'));
        $this->app->instance(AuthorizedBusinessWebsite::class, $website);
        $admin = Admin::factory()->create();
        $reference = $this->reference($admin);
        $response = $this->actingAs($admin, 'admin')->postJson($this->enrichmentUrl($reference), ['enterprise' => false, 'cost_acknowledged' => true])
            ->assertOk()->assertJsonPath('outcome', 'unavailable')->assertJsonPath('reason_code', 'source_lookup_failed')->assertJsonPath('fields', []);
        $this->assertStringContainsString('resolver o endereço', $response->json('message'));
        $this->assertStringNotContainsString('synthetic resolver diagnostics', $response->getContent());
        $this->assertDatabaseCount('prospects', 0);
        Http::assertNothingSent();
    }

    public function test_optional_brazilian_country_code_keeps_verified_phone_provenance(): void
    {
        $this->configureIndependentWebsite();
        $this->fakeEnrichment($this->independentHtml(['telephone' => '+55 (24) 99999-0000']));
        $admin = Admin::factory()->create();
        $reference = $this->reference($admin);
        $this->actingAs($admin, 'admin');
        $data = $this->postJson($this->enrichmentUrl($reference), ['enterprise' => false, 'cost_acknowledged' => true])->assertOk()->json();
        $input = [...$this->input(), ...$data['fields'], 'phone' => '(24) 99999-0000', 'assisted_mode' => 'simple', 'enrichment_token' => $data['enrichment_token']];
        $this->post(route('admin.explorer.references.prospects.store', $reference), $input)->assertRedirect();
        $metadata = Prospect::sole()->activities()->where('event', 'google_reference_linked')->sole()->metadata;
        $this->assertSame('licensed_source', $metadata['field_sources']['phone']['kind']);
        Http::assertSentCount(3);
    }

    public function test_reference_list_has_real_pagination_sizes_filters_and_no_provider_calls(): void
    {
        config(['business_explorer.independent_sources' => []]);
        $admin = Admin::factory()->create();
        for ($i = 0; $i < 52; $i++) {
            $this->reference($admin, 'ChIJ_LIST_'.str_pad((string) $i, 3, '0', STR_PAD_LEFT));
        }
        $this->actingAs($admin, 'admin');
        $response = $this->get(route('admin.explorer.index'))->assertOk()->assertViewHas('references', fn ($refs) => $refs->total() === 52 && $refs->count() === 10 && $refs->currentPage() === 1)->assertSee('Estabelecimento não consultado')->assertSee('Copiar ID')->assertSee('Iniciar cadastro')->assertSee('Consultar no Google');
        $first = $response->viewData('references')->pluck('id')->all();
        $page = $this->get(route('admin.explorer.index', ['page' => 2]))->assertOk()->viewData('references');
        $this->assertEmpty(array_intersect($first, $page->pluck('id')->all()));
        foreach ([25, 50] as $size) {
            $this->get(route('admin.explorer.index', ['per_page' => $size]))->assertOk()->assertViewHas('references', fn ($refs) => $refs->count() === $size && $refs->total() === 52);
        }
        $this->get(route('admin.explorer.index', ['q' => 'ChIJ_LIST_051']))->assertOk()->assertViewHas('references', fn ($refs) => $refs->total() === 1);
        $this->get(route('admin.explorer.index', ['page' => 999]))->assertOk()->assertViewHas('references', fn ($refs) => $refs->currentPage() === 6);
        Http::assertNothingSent();
    }

    public function test_saved_names_only_use_linked_crm_or_approved_sources_and_support_name_search(): void
    {
        config(['business_explorer.independent_sources' => []]);
        $admin = Admin::factory()->create();
        $linked = $this->reference($admin, 'ChIJ_OWN_LINKED');
        $prospect = app(CreateProspectFromPlaceReference::class)->handle($linked, $admin, $this->input(['business_name' => 'Nome próprio legítimo']));
        $source = $this->reference($admin, 'ChIJ_SYNTHETIC');
        $this->configureIndependentWebsite();
        $this->reference($admin, str_repeat('a', 400));
        $this->actingAs($admin, 'admin')->get(route('admin.explorer.index'))->assertOk()->assertSee('Nome próprio legítimo')->assertSee('Estabelecimento independente sintético')->assertSee('Estabelecimento não consultado')->assertSee(str_repeat('a', 400))->assertDontSee('Cafeteria Google sintética');
        $this->get(route('admin.explorer.index', ['q' => 'legítimo', 'status' => 'linked']))->assertOk()->assertViewHas('references', fn ($refs) => $refs->total() === 1 && $refs->first()->id === $linked->id);
        $this->get(route('admin.explorer.index', ['q' => 'independente', 'status' => 'pending']))->assertOk()->assertViewHas('references', fn ($refs) => $refs->total() === 1 && $refs->first()->id === $source->id);
        $this->configureIndependentWebsite(['storage_permitted' => false]);
        $this->get(route('admin.explorer.index', ['q' => 'independente']))->assertOk()->assertViewHas('references', fn ($refs) => $refs->total() === 0);
        Http::assertNothingSent();
    }

    public function test_reference_metadata_refresh_is_authorized_and_never_calls_google(): void
    {
        $admin = Admin::factory()->create();
        $reference = $this->reference($admin);
        $this->post(route('admin.explorer.metadata'), ['place_ids' => [$reference->place_id]])->assertRedirect(route('admin.login'));
        $this->actingAs($admin, 'admin')->postJson(route('admin.explorer.metadata'), ['place_ids' => [$reference->place_id]])->assertOk()->assertJsonPath('references.0.reference.id', $reference->id)->assertJsonPath('references.0.linked_prospect', null);
        $prospect = app(CreateProspectFromPlaceReference::class)->handle($reference, $admin, $this->input());
        $this->postJson(route('admin.explorer.metadata'), ['place_ids' => [$reference->place_id]])->assertOk()->assertJsonPath('references.0.linked_prospect.name', $prospect->business_name);
        $this->postJson(route('admin.explorer.metadata'), ['place_ids' => array_fill(0, 51, $reference->place_id)])->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_manual_and_reference_creation_require_only_minimum_business_fields_without_enrichment(): void
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');
        $manual = ['business_name' => 'Empresa manual mínima', 'city' => 'Vassouras', 'state' => 'RJ', 'submission_token' => (string) Str::uuid()];
        $this->post(route('admin.prospects.store'), $manual)->assertRedirect();
        $this->assertDatabaseCount('google_place_references', 0);
        $first = Prospect::sole();
        $this->assertNull($first->phone);
        $this->assertNull($first->website);
        $this->assertNull($first->instagram);
        $this->assertNull($first->email);
        $this->assertEmpty($first->confirmed_fields);
        $reference = $this->reference($admin);
        $this->post(route('admin.explorer.references.prospects.store', $reference), ['business_name' => 'Outra empresa declarada', 'city' => 'Barra do Piraí', 'state' => 'RJ', 'submission_token' => (string) Str::uuid(), 'assisted_mode' => 'simple'])->assertRedirect();
        $second = Prospect::findOrFail($reference->fresh()->prospect_id);
        $this->assertEmpty($second->confirmed_fields);
        $activity = $second->activities()->where('event', 'google_reference_linked')->sole();
        $this->assertContains('business_name', $activity->metadata['declared_fields']);
        $this->assertSame([], $activity->metadata['field_sources']);
        $this->assertDatabaseCount('prospects', 2);
        $this->assertDatabaseCount('email_outbox_messages', 0);
        Http::assertNothingSent();
    }

    public function test_reference_form_preserves_reference_filters_and_manual_data_after_validation_errors(): void
    {
        $admin = Admin::factory()->create();
        $reference = $this->reference($admin);
        $params = ['reference' => $reference, 'explorer_page' => 2, 'explorer_per_page' => 25, 'explorer_q' => 'nome', 'explorer_status' => 'pending'];
        $this->actingAs($admin, 'admin')->get(route('admin.explorer.references.create', $params))->assertOk()->assertSee('explorer_page=2', false)->assertSee('Novo estabelecimento');
        $this->post(route('admin.explorer.references.prospects.store', $params), ['business_name' => 'Nome preservado', 'city' => '', 'state' => 'RJ', 'submission_token' => (string) Str::uuid(), 'assisted_mode' => 'simple'])->assertRedirect(route('admin.explorer.references.create', $params))->assertSessionHasErrors('city')->assertSessionHasInput('business_name', 'Nome preservado');
        $this->get(route('admin.explorer.references.create', $params))->assertOk()->assertSee('Nome preservado');
        $this->assertDatabaseCount('prospects', 0);
        Http::assertNothingSent();
    }

    public function test_google_failure_does_not_prevent_reference_registration_with_minimum_data(): void
    {
        $admin = Admin::factory()->create();
        $reference = $this->reference($admin);
        Http::fake(['https://places.googleapis.com/v1/places/*' => Http::failedConnection()]);
        $this->actingAs($admin, 'admin')->postJson(route('admin.explorer.details'), ['reference_id' => $reference->id])->assertStatus(503);
        $this->post(route('admin.explorer.references.prospects.store', $reference), ['business_name' => 'Empresa sem dependência externa', 'city' => 'Vassouras', 'state' => 'RJ', 'submission_token' => (string) Str::uuid(), 'assisted_mode' => 'simple'])->assertRedirect();
        $this->assertNotNull($reference->fresh()->prospect_id);
        $this->assertDatabaseCount('prospects', 1);
        $this->assertDatabaseCount('email_outbox_messages', 0);
        $this->assertEmpty(Prospect::sole()->confirmed_fields);
        Http::assertSentCount(1);
    }

    private function publicCompany(array $changes = []): array
    {
        return [...['cnpj' => '11222333000181', 'nome_fantasia' => 'Empresa pública sintética', 'municipio' => 'Vassouras', 'uf' => 'RJ', 'cep' => '27700000', 'logradouro' => 'Rua Sintética', 'numero' => '42', 'bairro' => 'Centro', 'ddd_telefone_1' => '24999990000', 'qsa' => [['nome_socio' => 'NEVER_FORWARD_PARTNER']]], ...$changes];
    }

    public function test_public_cnpj_source_fills_without_website_only_after_explicit_identity_review_and_preserves_provenance(): void
    {
        $admin = Admin::factory()->create();
        $reference = $this->reference($admin);
        config(['business_explorer.independent_sources' => []]);
        Http::preventStrayRequests();
        Http::fake(['https://minhareceita.org/11222333000181' => Http::response($this->publicCompany())]);
        $candidate = $this->actingAs($admin, 'admin')->postJson($this->enrichmentUrl($reference), ['enterprise' => false, 'cost_acknowledged' => true, 'cnpj' => '11.222.333/0001-81'])->assertOk()->assertJsonPath('outcome', 'review')->assertJsonPath('fields', [])->assertDontSee('NEVER_FORWARD_PARTNER')->json();
        $this->assertArrayNotHasKey('phone', $candidate['review_fields']);
        $this->assertArrayNotHasKey('website', $candidate['review_fields']);
        $data = $this->postJson($this->enrichmentUrl($reference), ['enterprise' => false, 'cost_acknowledged' => true, 'company_token' => $candidate['company_token']])->assertOk()->assertJsonPath('fields.city', 'Vassouras')->assertJsonPath('fields.street_number', '42')->json();
        Http::assertSentCount(1);
        $this->post(route('admin.explorer.references.prospects.store', $reference), [...$data['fields'], 'assisted_mode' => 'simple', 'enrichment_token' => $data['enrichment_token'], 'submission_token' => (string) Str::uuid()])->assertSessionHasNoErrors()->assertRedirect();
        $prospect = $reference->fresh()->prospect;
        $this->assertNotNull($prospect);
        $this->assertSame('Empresa pública sintética', $prospect->business_name);
        $linked = $prospect->activities()->where('event', 'google_reference_linked')->first();
        $this->assertSame('public_data', $linked->metadata['field_sources']['business_name']['kind']);
        $this->assertArrayHasKey('business_name', $prospect->confirmed_fields);

        $this->assertStringNotContainsString('NEVER_FORWARD_PARTNER', json_encode($prospect->toArray()));
    }

    public function test_public_company_candidate_cannot_be_used_for_another_reference_or_administrator(): void
    {
        $admin = Admin::factory()->create();
        $reference = $this->reference($admin);
        Http::fake(['https://minhareceita.org/11222333000181' => Http::response($this->publicCompany())]);
        $token = $this->actingAs($admin, 'admin')->postJson($this->enrichmentUrl($reference), ['enterprise' => false, 'cost_acknowledged' => true, 'cnpj' => '11222333000181'])->assertOk()->json('company_token');
        $other = $this->reference($admin, 'ChIJ_OTHER_PUBLIC');
        $this->postJson($this->enrichmentUrl($other), ['enterprise' => false, 'cost_acknowledged' => true, 'company_token' => $token])->assertUnprocessable();
        $this->actingAs(Admin::factory()->create(), 'admin')->postJson($this->enrichmentUrl($reference), ['enterprise' => false, 'cost_acknowledged' => true, 'company_token' => $token])->assertUnprocessable();
        Http::assertSentCount(1);
    }

    public function test_public_company_failures_and_wrong_identity_never_fill_or_create_records(): void
    {
        $admin = Admin::factory()->create();
        $reference = $this->reference($admin);
        $this->actingAs($admin, 'admin');
        Http::fake(['https://minhareceita.org/11222333000181' => Http::sequence()->push([], 404)->push([], 429)->push('invalid json')->push($this->publicCompany(['cnpj' => '00000000000000']))]);
        foreach (['CNPJ não encontrado', 'base de empresas está ocupada', 'dados inválidos', 'dados inválidos'] as $message) {
            $this->postJson($this->enrichmentUrl($reference), ['enterprise' => false, 'cost_acknowledged' => true, 'cnpj' => '11222333000181'])->assertUnprocessable()->assertJsonPath('message', fn ($value) => str_contains($value, $message));
        }
        Http::fake();
        $this->postJson($this->enrichmentUrl($reference), ['enterprise' => false, 'cost_acknowledged' => true, 'cnpj' => 'invalid'])->assertUnprocessable();
        Http::assertNothingSent();
        $this->assertDatabaseCount('prospects', 0);
        $this->assertDatabaseCount('email_outbox_messages', 0);
    }

    public function test_existing_crm_identity_is_offered_for_review_without_copying_or_external_calls(): void
    {
        $admin = Admin::factory()->create();
        $reference = $this->reference($admin);
        $prospect = app(CreateProspect::class)->handle(['business_name' => 'Cadastro próprio sintético', 'city' => 'Vassouras', 'state' => 'RJ', 'submission_token' => (string) Str::uuid()], $admin);
        Http::preventStrayRequests();
        Http::fake();
        $this->actingAs($admin, 'admin')->postJson($this->enrichmentUrl($reference), ['enterprise' => false, 'cost_acknowledged' => true, 'business_name' => $prospect->business_name, 'city' => 'Vassouras', 'state' => 'RJ'])->assertOk()->assertJsonPath('reason_code', 'possible_existing_crm')->assertJsonPath('fields', [])->assertJsonPath('existing_prospect_url', route('admin.prospects.show', $prospect));
        Http::assertNothingSent();
        $this->assertDatabaseCount('prospects', 1);
    }

    public function test_public_company_timeout_and_quota_preserve_manual_creation_without_extra_calls(): void
    {
        $admin = Admin::factory()->create();
        $reference = $this->reference($admin);
        Http::fake(['https://minhareceita.org/11222333000181' => Http::failedConnection()]);
        $this->actingAs($admin, 'admin')->postJson($this->enrichmentUrl($reference), ['enterprise' => false, 'cost_acknowledged' => true, 'cnpj' => '11222333000181'])->assertStatus(503);
        config(['business_explorer.per_admin_minute' => 0]);
        Http::fake();
        $this->postJson($this->enrichmentUrl($reference), ['enterprise' => false, 'cost_acknowledged' => true, 'cnpj' => '11222333000181'])->assertStatus(429);
        Http::assertNothingSent();
        $this->post(route('admin.explorer.references.prospects.store', $reference), ['business_name' => 'Manual após falha sintética', 'city' => 'Vassouras', 'state' => 'RJ', 'assisted_mode' => 'simple', 'submission_token' => (string) Str::uuid()])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertNotNull($reference->fresh()->prospect_id);
    }

    public function test_public_company_rejects_oversized_bodies_and_incomplete_identification(): void
    {
        $admin = Admin::factory()->create();
        $reference = $this->reference($admin);
        $this->actingAs($admin, 'admin');
        Http::fake(['https://minhareceita.org/11222333000181' => Http::sequence()->push(str_repeat(' ', 131073))->push($this->publicCompany(['municipio' => '']))]);
        foreach (['dados inválidos', 'identificação suficiente'] as $message) {
            $this->postJson($this->enrichmentUrl($reference), ['enterprise' => false, 'cost_acknowledged' => true, 'cnpj' => '11222333000181'])->assertUnprocessable()->assertJsonPath('message', fn ($value) => str_contains($value, $message));
        }
        $this->assertDatabaseCount('prospects', 0);
    }

    private function googleDraftUrl(GooglePlaceReference $reference): string
    {
        return route('admin.explorer.references.google-draft', $reference);
    }

    private function googleImportFixture(): array
    {
        return [...$this->place(), 'addressComponents' => [
            ['longText' => 'Vassouras', 'types' => ['locality']], ['longText' => 'Município auxiliar', 'types' => ['administrative_area_level_2']],
            ['longText' => 'Rio de Janeiro', 'shortText' => 'RJ', 'types' => ['administrative_area_level_1']],
            ['longText' => 'Rua Sintética Google', 'types' => ['route']], ['longText' => '250', 'types' => ['street_number']],
            ['longText' => '27700-000', 'types' => ['postal_code']], ['longText' => 'Centro', 'types' => ['sublocality']]],
            'nationalPhoneNumber' => 'forbidden phone without enterprise', 'websiteUri' => 'https://forbidden.invalid'];
    }

    public function test_google_import_is_disabled_by_default_and_never_calls_provider(): void
    {
        $admin = Admin::factory()->create();
        $reference = $this->reference($admin);
        Http::fake();
        $this->actingAs($admin, 'admin')->postJson($this->googleDraftUrl($reference), ['enterprise' => false, 'cost_acknowledged' => true])->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_google_import_reuses_details_without_second_billable_call_and_persists_truthful_origin(): void
    {
        config(['business_explorer.google_import_enabled' => true, 'business_explorer.google_persistence_enabled' => true]);
        $admin = Admin::factory()->create();
        $reference = $this->reference($admin);
        Http::fake(['https://places.googleapis.com/v1/places/ChIJ_SYNTHETIC*' => Http::response($this->googleImportFixture())]);
        $details = $this->actingAs($admin, 'admin')->postJson(route('admin.explorer.details'), ['reference_id' => $reference->id])->assertOk()->json('place');
        $data = $this->postJson($this->googleDraftUrl($reference), ['enterprise' => false, 'cost_acknowledged' => true, 'details_token' => $details['import_token']])->assertOk()->assertJsonPath('fields.city', 'Vassouras')->assertJsonPath('fields.street_number', '250')->assertJsonPath('fields.neighborhood', 'Centro')->json();
        $this->assertCount(8, $data['fields']);
        $this->assertArrayNotHasKey('phone', $data['fields']);
        $this->assertArrayNotHasKey('website', $data['fields']);
        Http::assertSentCount(1);
        $input = [...$data['fields'], 'assisted_mode' => 'simple', 'google_enrichment_token' => $data['google_token'], 'google_applied_fields' => json_encode(array_keys($data['fields'])), 'google_origin_confirmed' => '1', 'submission_token' => (string) Str::uuid()];
        $this->post(route('admin.explorer.references.prospects.store', $reference), $input)->assertSessionHasNoErrors()->assertRedirect();
        $prospect = $reference->fresh()->prospect;
        $this->assertSame('Cafeteria Google sintética', $prospect->business_name);
        $this->assertEmpty($prospect->confirmed_fields);
        $history = $prospect->activities()->where('event', 'google_reference_linked')->first();
        $this->assertSame('google_places', $history->metadata['field_sources']['business_name']['kind']);
        $this->assertSame('google_places', $history->metadata['field_origins']['business_name']);
        $this->assertNotNull($history->metadata['google_queried_at']);
        $this->assertSame('google_content_contractual_risk_not_regularized', $history->metadata['storage_eligibility']);
        $source = $prospect->sources()->find($history->metadata['field_sources']['business_name']['source_id']);
        $this->assertStringContainsString('Google Places', $source->origin_reference);
        $this->assertNull($source->verified_at);
        Http::assertSentCount(1);
    }

    public function test_google_import_requires_confirmation_and_persistence_flag_and_retains_edited_origin(): void
    {
        config(['business_explorer.google_import_enabled' => true, 'business_explorer.google_persistence_enabled' => false]);
        $admin = Admin::factory()->create();
        $reference = $this->reference($admin);
        Http::fake(['https://places.googleapis.com/v1/places/ChIJ_SYNTHETIC*' => Http::response($this->googleImportFixture())]);
        $data = $this->actingAs($admin, 'admin')->postJson($this->googleDraftUrl($reference), ['enterprise' => false, 'cost_acknowledged' => true])->assertOk()->json();
        $input = [...$data['fields'], 'assisted_mode' => 'simple', 'google_enrichment_token' => $data['google_token'], 'google_applied_fields' => json_encode(array_keys($data['fields'])), 'submission_token' => (string) Str::uuid()];
        $this->post(route('admin.explorer.references.prospects.store', $reference), $input)->assertSessionHasErrors('google_enrichment_token');
        config(['business_explorer.google_persistence_enabled' => true]);
        $this->post(route('admin.explorer.references.prospects.store', $reference), $input)->assertSessionHasErrors('google_origin_confirmed');
        $input['google_origin_confirmed'] = '1';
        $input['business_name'] = 'Nome Google editado pelo administrador';
        $this->post(route('admin.explorer.references.prospects.store', $reference), $input)->assertSessionHasNoErrors()->assertRedirect();
        $entry = $reference->fresh()->prospect->activities()->where('event', 'google_reference_linked')->first()->metadata['field_sources']['business_name'];
        $this->assertSame('google_places', $entry['kind']);
        $this->assertTrue($entry['edited']);
    }

    public function test_google_import_tokens_are_bound_to_admin_and_place_and_enforce_expiry(): void
    {
        config(['business_explorer.google_import_enabled' => true]);
        $admin = Admin::factory()->create();
        $reference = $this->reference($admin);
        $other = $this->reference($admin, 'ChIJ_OTHER_GOOGLE');
        Http::fake(['https://places.googleapis.com/v1/places/ChIJ_SYNTHETIC*' => Http::response($this->googleImportFixture())]);
        $details = $this->actingAs($admin, 'admin')->postJson(route('admin.explorer.details'), ['reference_id' => $reference->id])->json('place.import_token');
        $input = ['enterprise' => false, 'cost_acknowledged' => true, 'details_token' => $details];
        $this->postJson($this->googleDraftUrl($other), $input)->assertUnprocessable();
        $this->actingAs(Admin::factory()->create(), 'admin')->postJson($this->googleDraftUrl($reference), $input)->assertUnprocessable();
        $this->actingAs($admin, 'admin');
        $this->travel(16)->minutes();
        $this->postJson($this->googleDraftUrl($reference), $input)->assertUnprocessable();
        $this->travelBack();
        Http::assertSentCount(1);
    }

    public function test_google_import_incomplete_fields_do_not_invent_contacts_or_postal_code_and_require_authorization(): void
    {
        config(['business_explorer.google_import_enabled' => true]);
        $admin = Admin::factory()->create();
        $reference = $this->reference($admin);
        Http::fake(['https://places.googleapis.com/v1/places/ChIJ_SYNTHETIC*' => Http::response($this->place())]);
        $this->postJson($this->googleDraftUrl($reference), ['enterprise' => false, 'cost_acknowledged' => true])->assertUnauthorized();
        Http::assertNothingSent();
        $this->actingAs($admin, 'admin')->postJson($this->googleDraftUrl($reference), ['enterprise' => false, 'cost_acknowledged' => true])->assertOk()->assertJsonCount(2, 'fields')->assertJsonPath('fields.business_name', 'Cafeteria Google sintética')->assertJsonMissingPath('fields.city')->assertJsonMissingPath('fields.postal_code')->assertJsonMissingPath('fields.phone')->assertJsonMissingPath('fields.website');
    }

    public function test_google_fields_cannot_be_forged_or_labeled_independent_and_must_belong_to_signed_snapshot(): void
    {
        config(['business_explorer.google_import_enabled' => true, 'business_explorer.google_persistence_enabled' => true]);
        $admin = Admin::factory()->create();
        $reference = $this->reference($admin);
        Http::fake(['https://places.googleapis.com/v1/places/ChIJ_SYNTHETIC*' => Http::response($this->googleImportFixture())]);
        $data = $this->actingAs($admin, 'admin')->postJson($this->googleDraftUrl($reference), ['enterprise' => false, 'cost_acknowledged' => true])->json();
        $input = [...$data['fields'], 'assisted_mode' => 'simple', 'google_enrichment_token' => $data['google_token'], 'google_applied_fields' => json_encode(['instagram']), 'google_origin_confirmed' => '1', 'submission_token' => (string) Str::uuid()];
        $this->post(route('admin.explorer.references.prospects.store', $reference), $input)->assertSessionHasErrors('google_enrichment_token');
        $forged = $this->input(['field_provenance' => array_fill_keys(array_keys(ReferenceFieldProvenance::fields()), ['kind' => 'google_places', 'source' => 'Google Places (New) | Place ID: ChIJ_SYNTHETIC', 'confirmed' => true])]);
        $this->expectException(ValidationException::class);
        app(CreateProspectFromPlaceReference::class)->handle($reference, $admin, $forged);
    }
}
