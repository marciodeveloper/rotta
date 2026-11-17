<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\GooglePlaceReference;
use App\Models\Prospect;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateProspectFromPlaceReference
{
    public function __construct(private CreateProspect $create, private ProspectAudit $audit, private ReferenceFieldProvenance $provenance, private SensitiveHash $hash) {}

    public function handle(GooglePlaceReference $reference, Admin $admin, array $input): Prospect
    {
        Gate::forUser($admin)->authorize('useForProspect', $reference);
        Gate::forUser($admin)->authorize('create', Prospect::class);
        if (($input['assisted_mode'] ?? null) === 'simple') {
            $input = $this->provenance->resolveSimpleInput($admin, $reference, $input);
        }
        $input['origin_reference'] = is_string($input['origin_reference'] ?? null) ? trim(preg_replace('/[\p{C}\p{Z}\s]+/u', ' ', $input['origin_reference'])) : ($input['origin_reference'] ?? null);
        $simple = ($input['assisted_mode'] ?? null) === 'simple';
        Validator::make($input, ['submission_token' => ['required', 'uuid'], 'independent_data_confirmed' => $simple ? ['sometimes', 'boolean'] : ['required', 'accepted'], 'origin_reference' => ['required', 'string', 'min:10', 'max:500', function ($attribute, $value, $fail): void {
            if (is_string($value) && (mb_strtolower(trim($value)) === 'google maps' || preg_match('~^(?:https?://)?(?:www\.)?(?:maps\.google\.[a-z.]+(?:/|$)|google\.[a-z.]+/maps|maps\.app\.goo\.gl(?:/|$))~i', $value))) {
                $fail('Uma referência do Google Maps não é uma fonte independente dos dados comerciais.');
            }
        }]], [
            'independent_data_confirmed.required' => 'Confirme a origem independente dos dados comerciais.',
            'independent_data_confirmed.accepted' => 'Confirme a origem independente dos dados comerciais.',
            'origin_reference.required' => 'Descreva a fonte independente ou a licença que permite conservar os dados.',
        ], ProspectRules::attributes())->validate();

        $fieldSources = $this->provenance->validate($input, $simple);
        $provenanceDigest = $this->hash->makeFor('reference-field-provenance', json_encode($fieldSources, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($reference, $admin, $input, $fieldSources, $provenanceDigest, $simple): Prospect {
            DB::select('SELECT pg_advisory_xact_lock(731204, 1)');
            $locked = GooglePlaceReference::lockForUpdate()->findOrFail($reference->id);
            $existing = Prospect::where('creation_token', $input['submission_token'] ?? '')->first();
            if (($locked->prospect_id && $existing?->id !== $locked->prospect_id) || (! $locked->prospect_id && $existing)) {
                throw ValidationException::withMessages(['submission_token' => 'Esta referência ou este envio já foi utilizado. Abra a ficha existente ou um novo formulário.']);
            }
            $prospect = $this->create->handle($input, $admin);
            if ($locked->prospect_id) {
                $previous = $prospect->activities()->where('event', 'google_reference_linked')->first();
                if (($previous?->metadata['provenance_digest'] ?? null) !== $provenanceDigest) {
                    throw ValidationException::withMessages(['submission_token' => 'Este envio já foi utilizado com outra proveniência. Abra a ficha existente.']);
                }

                return $prospect;
            }
            if (! $locked->prospect_id) {
                $confirmed = $prospect->confirmed_fields ?? [];
                $mapping = [];
                $sources = [];
                foreach ($fieldSources as $field => $provenance) {
                    $sourceKey = $this->hash->makeFor('reference-field-source', json_encode($provenance, JSON_THROW_ON_ERROR));
                    $source = $sources[$sourceKey] ??= $prospect->sources()->create([
                        'created_by' => $admin->id, 'submission_token' => (string) Str::uuid(), 'submission_digest' => $sourceKey,
                        'origin_reference' => $provenance['source'], 'usage_restrictions' => ReferenceFieldProvenance::KINDS[$provenance['kind']], 'verified_at' => $provenance['kind'] === 'google_places' ? null : now()->toDateString(),
                    ]);
                    $mapping[$field] = ['source_id' => $source->id, 'kind' => $provenance['kind']];
                    if (isset($provenance['evidence'])) {
                        $mapping[$field]['evidence'] = $provenance['evidence'];
                    }
                    if ($provenance['kind'] === 'google_places') {
                        $mapping[$field]['edited'] = $provenance['edited'] ?? false;

                        continue;
                    }
                    $confirmed[$field] = ['source_id' => $source->id, 'admin_id' => $admin->id, 'at' => now()->toIso8601String()];
                }
                $prospect->update(['confirmed_fields' => $confirmed ?: (object) []]);
                $locked->update(['prospect_id' => $prospect->id, 'linked_by' => $admin->id, 'linked_at' => now()]);
                $declared = array_values(array_diff(array_keys(array_filter(array_intersect_key($prospect->getAttributes(), ReferenceFieldProvenance::fields()), fn ($value) => $value !== null && $value !== '')), array_keys($mapping)));
                $this->audit->record($prospect, $admin, 'google_reference_linked', ['reference_id' => $locked->id, 'storage_eligibility' => in_array('google_places', array_column($mapping, 'kind'), true) ? 'google_content_contractual_risk_not_regularized' : 'place_id_only', 'commercial_data_source' => $simple ? 'field_level_sources_and_manual_declarations' : 'independent_manual_assertion', 'declared_fields' => $declared, 'field_origins' => [...array_fill_keys($declared, 'manual'), ...array_map(fn ($source) => $source['kind'], $mapping)], 'google_queried_at' => $input['google_queried_at'] ?? null, 'field_sources' => $mapping, 'provenance_digest' => $provenanceDigest]);
            }

            return $prospect;
        }, 3);
    }
}
