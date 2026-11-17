<?php

namespace App\Services;

use App\Contracts\BusinessSearchProvider;
use App\Exceptions\PlacesUnavailable;
use App\Models\Admin;
use App\Models\AdminAuditEvent;
use App\Models\GooglePlaceReference;
use App\Models\Prospect;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BusinessExplorer
{
    public function __construct(private BusinessSearchProvider $provider, private PlacesContext $context, private PlacesQuota $quota, private NormalizeProspect $normalize) {}

    public function search(Admin $admin, array $input): array
    {
        Gate::forUser($admin)->authorize('viewAny', GooglePlaceReference::class);
        $page = isset($input['cursor']) ? $this->context->read($admin, 'page', $input['cursor']) : [
            'query' => $input['query'], 'page' => 1, 'page_token' => null,
            'size' => max(1, min(20, (int) config('business_explorer.page_size'))),
            'max_pages' => max(1, min(3, (int) config('business_explorer.max_pages'))),
        ];
        $result = $this->call($admin, 'search', fn () => $this->provider->search($page['query'], $page['size'], $page['page_token']), $page['page']);
        $next = $result['next_page_token'] && $page['page'] < $page['max_pages']
            ? $this->context->issue($admin, 'page', [...$page, 'page' => $page['page'] + 1, 'page_token' => $result['next_page_token']]) : null;

        return ['places' => $this->enrich($admin, $result['places']), 'next_cursor' => $next, 'page' => $page['page'], 'max_pages' => $page['max_pages'], 'max_results' => $page['size'] * $page['max_pages']];
    }

    public function details(Admin $admin, array $input): array
    {
        Gate::forUser($admin)->authorize('viewAny', GooglePlaceReference::class);
        $placeId = isset($input['reference_id'])
            ? GooglePlaceReference::findOrFail($input['reference_id'])->place_id
            : $this->context->read($admin, 'place', $input['token'])['place_id'];
        $place = $this->call($admin, 'details', fn () => $this->provider->details($placeId));

        return $this->withImportToken($admin, $this->enrich($admin, [$place])[0]);
    }

    public function intelligence(Admin $admin, array $input): array
    {
        Gate::forUser($admin)->authorize('viewAny', GooglePlaceReference::class);
        $summary = $input['mode'] === 'summary';
        if (! config('business_explorer.enterprise_enabled') || ! config('business_explorer.reputation_enabled') || ($summary && ! config('business_explorer.review_summary_enabled'))) {
            throw new PlacesUnavailable('Esta consulta opcional está desabilitada. Solicite a habilitação ao administrador.', 422);
        }
        $placeId = isset($input['reference_id'])
            ? GooglePlaceReference::findOrFail($input['reference_id'])->place_id
            : $this->context->read($admin, 'place', $input['token'])['place_id'];

        return $this->call($admin, $summary ? 'review_summary' : 'reputation', fn () => $this->provider->intelligence($placeId, $summary));
    }

    public function enrichmentDetails(Admin $admin, GooglePlaceReference $reference, bool $enterprise): array
    {
        Gate::forUser($admin)->authorize('useForProspect', $reference);
        if ($enterprise && ! config('business_explorer.enterprise_enabled')) {
            throw new PlacesUnavailable('A consulta ampliada Google está desabilitada na configuração administrativa.', 422);
        }

        return $this->call($admin, $enterprise ? 'enrich_enterprise' : 'enrich_basic', fn () => $this->provider->details($reference->place_id, $enterprise));
    }

    private function withImportToken(Admin $admin, array $place): array
    {
        if (config('business_explorer.google_import_enabled')) {
            $place['import_token'] = $this->context->issue($admin, 'google_details', ['place_id' => $place['place_id'], 'fields' => $this->googleFields($place), 'queried_at' => $place['queried_at'], 'enterprise' => ($place['sku'] ?? 'Pro') === 'Enterprise']);
        }

        return $place;
    }

    public function googleFields(array $place): array
    {
        $address = $place['address_fields'] ?? [];
        $values = ['business_name' => $place['name'] ?? '', 'category' => $place['category'] ?? '',
            ...array_intersect_key($address, array_flip(['city', 'state', 'postal_code', 'street', 'street_number', 'neighborhood'])),
            'phone' => $place['phone'] ?? '', 'website' => $place['website'] ?? ''];
        $values = $this->normalize->handle($values);
        if (is_string($values['phone'] ?? null)) {
            $values['phone'] = $this->normalize->phoneKey($values['phone']);
        }
        $fields = [];
        foreach (ReferenceFieldProvenance::fields() as $field => $label) {
            if (is_string($values[$field] ?? null) && $values[$field] !== '' && Validator::make([$field => $values[$field]], [$field => ProspectRules::fields()[$field]])->passes()) {
                $fields[$field] = $values[$field];
            }
        }

        return $fields;
    }

    public function googleDraft(Admin $admin, GooglePlaceReference $reference, array $input): array
    {
        Gate::forUser($admin)->authorize('useForProspect', $reference);
        if (! config('business_explorer.google_import_enabled')) {
            throw new PlacesUnavailable('O preenchimento Google está desabilitado neste ambiente.', 422);
        }
        if ($reference->prospect_id) {
            throw new PlacesUnavailable('A referência já está vinculada. Abra a ficha existente.', 422);
        }
        $details = null;
        if (! empty($input['details_token'])) {
            $details = $this->context->read($admin, 'google_details', $input['details_token']);
            if (($details['place_id'] ?? null) !== $reference->place_id) {
                throw ValidationException::withMessages(['details_token' => 'A consulta pertence a outro estabelecimento.']);
            }
            if (($details['enterprise'] ?? false) && ! config('business_explorer.enterprise_enabled')) {
                throw new PlacesUnavailable('A consulta ampliada Google está desabilitada.', 422);
            }
        }
        if ($details && (! ($input['enterprise'] ?? false) || ($details['enterprise'] ?? false))) {
            $fields = $details['fields'];
            $queriedAt = $details['queried_at'];
            $place = null;
        } else {
            $place = $this->withImportToken($admin, $this->enrichmentDetails($admin, $reference, (bool) ($input['enterprise'] ?? false)));
            $fields = $this->googleFields($place);
            $queriedAt = $place['queried_at'];
        }
        $data = ['reference_id' => $reference->id, 'place_id' => $reference->place_id, 'fields' => $fields,
            'source' => mb_substr('Google Places (New) | Place ID: '.$reference->place_id, 0, 500),
            'evidence' => hash('sha256', json_encode($fields, JSON_THROW_ON_ERROR)), 'queried_at' => $queriedAt];
        AdminAuditEvent::create(['admin_id' => $admin->id, 'target_type' => GooglePlaceReference::class, 'target_id' => $reference->id,
            'action' => 'business_explorer.google_import_prepared', 'metadata' => ['field_names' => array_keys($fields), 'provider' => 'google_places', 'contractual_status' => 'not_verified', 'queried_at' => $queriedAt]]);

        return ['fields' => $fields, 'google' => $place, 'google_token' => $this->context->issue($admin, 'google_import', $data), 'source' => $data['source'], 'queried_at' => $queriedAt];
    }

    private function call(Admin $admin, string $operation, callable $request, ?int $page = null): array
    {
        $correlation = (string) Str::uuid();
        $started = hrtime(true);
        $quota = 'not_checked';
        $attempts = 0;
        Log::info('places.operation.started', ['correlation_id' => $correlation, 'operation' => $operation, 'started_at' => now()->toISOString()]);
        try {
            if ($message = $this->provider->configurationMessage()) {
                throw new PlacesUnavailable($message, 503, ['category' => 'configuration_error']);
            }
            $quota = 'checking';
            $this->quota->reserve($admin);
            $quota = 'reserved';
            $attempts = 1;
            $result = Context::scope($request, ['places_correlation_id' => $correlation, 'places_quota_status' => $quota]);
        } catch (PlacesUnavailable $exception) {
            $diagnostics = [...$exception->diagnostics, 'correlation_id' => $correlation, 'operation' => $operation,
                'operation_duration_ms' => round((hrtime(true) - $started) / 1e6, 2), 'local_status' => $exception->httpStatus,
                'quota' => $quota === 'checking' ? ($exception->httpStatus === 429 ? 'denied' : 'check_failed') : $quota,
                'attempts' => $attempts, 'category' => $exception->diagnostics['category'] ?? ($quota === 'checking' ? ($exception->httpStatus === 429 ? 'internal_quota' : 'quota_check_failed') : 'provider_failure')];
            Log::warning('places.operation.completed', $diagnostics);
            $this->audit($admin, $operation, ['outcome' => 'failed', 'status' => $exception->httpStatus, ...$diagnostics]);
            throw new PlacesUnavailable($exception->getMessage(), $exception->httpStatus, $diagnostics);
        }
        Log::info('places.operation.completed', ['correlation_id' => $correlation, 'operation' => $operation, 'duration_ms' => round((hrtime(true) - $started) / 1e6, 2), 'local_status' => 200, 'quota' => $quota, 'attempts' => $attempts, 'category' => 'success']);
        $this->audit($admin, $operation, ['outcome' => 'success', 'page' => $page, 'correlation_id' => $correlation, 'quota' => $quota, 'attempts' => $attempts]);

        return $result;
    }

    private function audit(Admin $admin, string $operation, array $metadata): void
    {
        AdminAuditEvent::create(['admin_id' => $admin->id, 'action' => 'business_explorer.'.$operation, 'metadata' => ['provider' => 'google_places_new', ...$metadata]]);
    }

    private function enrich(Admin $admin, array $places): array
    {
        $references = GooglePlaceReference::with('prospect:id,business_name,contact_blocked_at')->whereIn('place_id', array_column($places, 'place_id'))->get()->keyBy('place_id');
        $names = array_map(fn ($place) => $this->normalize->key($place['name']), $places);
        $matches = Prospect::whereIn('normalized_name', array_filter($names))->orderBy('id')->limit(60)->get(['id', 'business_name', 'normalized_name', 'contact_blocked_at'])->groupBy('normalized_name');

        return array_map(function ($place) use ($admin, $references, $matches): array {
            $reference = $references->get($place['place_id']);
            $crm = fn ($prospect) => ['name' => $prospect->business_name, 'blocked' => $prospect->contact_blocked_at !== null, 'url' => route('admin.prospects.show', $prospect)];

            return [...$place,
                'token' => $this->context->issue($admin, 'place', ['place_id' => $place['place_id'], 'queried_at' => now()->toIso8601String()]),
                'reference' => $reference ? ['id' => $reference->id, 'create_url' => route('admin.explorer.references.create', $reference)] : null,
                'linked_prospect' => $reference?->prospect ? $crm($reference->prospect) : null,
                'possible_duplicates' => ($matches->get($this->normalize->key($place['name'])) ?? collect())->reject(fn ($prospect) => $prospect->id === $reference?->prospect_id)->take(3)->map($crm)->values()->all(),
            ];
        }, $places);
    }
}
