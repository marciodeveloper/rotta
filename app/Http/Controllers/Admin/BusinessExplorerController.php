<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\BusinessSearchProvider;
use App\Exceptions\PlacesUnavailable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PlaceDetailsRequest;
use App\Http\Requests\Admin\SavePlaceReferenceRequest;
use App\Http\Requests\Admin\SearchBusinessesRequest;
use App\Models\GooglePlaceReference;
use App\Services\BusinessExplorer;
use App\Services\EnrichProspectReference;
use App\Services\SavedPlaceReferences;
use App\Services\SaveGooglePlaceReference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BusinessExplorerController extends Controller
{
    public function index(Request $request, BusinessSearchProvider $provider, SavedPlaceReferences $saved): View
    {
        Gate::authorize('viewAny', GooglePlaceReference::class);
        $filters = $request->validate(SavedPlaceReferences::rules());
        $names = $saved->names();
        $duplicates = $saved->duplicateIds($names);

        return view('admin.explorer.index', ['configurationMessage' => $provider->configurationMessage(), 'references' => $saved->paginate($filters, $names, $duplicates), 'referenceFilters' => $filters, 'referenceNames' => $names, 'duplicateReferenceIds' => $duplicates]);
    }

    public function metadata(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', GooglePlaceReference::class);
        $input = $request->validate(['place_ids' => ['required', 'array', 'max:50'], 'place_ids.*' => ['required', 'string', 'max:2048', 'regex:'.GooglePlaceReference::ID_PATTERN]]);
        $references = GooglePlaceReference::with('prospect:id,business_name,contact_blocked_at')->whereIn('place_id', $input['place_ids'])->get();

        return response()->json(['references' => $references->map(fn ($reference) => ['place_id' => $reference->place_id,
            'reference' => ['id' => $reference->id, 'create_url' => route('admin.explorer.references.create', $reference)],
            'linked_prospect' => $reference->prospect ? ['name' => $reference->prospect->business_name, 'blocked' => $reference->prospect->contact_blocked_at !== null, 'url' => route('admin.prospects.show', $reference->prospect)] : null])]);
    }

    public function search(SearchBusinessesRequest $request, BusinessExplorer $explorer): JsonResponse
    {
        return $this->respond(fn () => $explorer->search($request->user('admin'), $request->validated()));
    }

    public function details(PlaceDetailsRequest $request, BusinessExplorer $explorer): JsonResponse
    {
        return $this->respond(fn () => ['place' => $explorer->details($request->user('admin'), $request->validated())]);
    }

    public function googleDraft(Request $request, GooglePlaceReference $reference, BusinessExplorer $explorer): JsonResponse
    {
        Gate::authorize('useForProspect', $reference);
        $input = $request->validate(['details_token' => ['nullable', 'string', 'max:32768'], 'enterprise' => ['required', 'boolean'], 'cost_acknowledged' => ['required', 'accepted']]);

        return $this->respond(fn () => $explorer->googleDraft($request->user('admin'), $reference, $input));
    }

    public function enrich(Request $request, GooglePlaceReference $reference, EnrichProspectReference $enrich): JsonResponse
    {
        Gate::authorize('useForProspect', $reference);
        $input = $request->validate(['enterprise' => ['required', 'boolean'], 'cost_acknowledged' => ['required', 'accepted'], 'cnpj' => ['nullable', 'string', 'max:30'], 'company_token' => ['nullable', 'string', 'max:20000'], 'business_name' => ['nullable', 'string', 'max:180'], 'city' => ['nullable', 'string', 'max:100'], 'state' => ['nullable', 'string', 'size:2']]);

        return $this->respond(fn () => $enrich->handle($request->user('admin'), $reference, (bool) $input['enterprise'], $input));
    }

    public function storeReference(SavePlaceReferenceRequest $request, SaveGooglePlaceReference $save): JsonResponse
    {
        return $this->respond(function () use ($request, $save): array {
            $reference = $save->handle($request->user('admin'), $request->validated('token'));

            return ['reference' => ['id' => $reference->id, 'create_url' => route('admin.explorer.references.create', $reference)], 'linked_url' => $reference->prospect_id ? route('admin.prospects.show', $reference->prospect_id) : null];
        });
    }

    private function respond(callable $operation): JsonResponse
    {
        try {
            return response()->json($operation());
        } catch (PlacesUnavailable $exception) {
            return response()->json(['message' => $exception->getMessage(), 'error_code' => $exception->diagnostics['category'] ?? null, 'correlation_id' => $exception->diagnostics['correlation_id'] ?? null], $exception->httpStatus);
        } catch (ValidationException $exception) {
            return response()->json(['message' => 'Revise a referência e tente novamente.', 'errors' => $exception->errors()], 422);
        }
    }
}
