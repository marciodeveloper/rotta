<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\GooglePlaceReference;
use App\Models\Prospect;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReferenceFieldProvenance
{
    public const KINDS = ['google_places' => 'Google Places — restrição contratual não regularizada', 'public_data' => 'Dados cadastrais públicos', 'direct_contact' => 'Informado pelo estabelecimento', 'owned_records' => 'Registro próprio independente', 'licensed_source' => 'Fonte com licença de armazenamento'];

    public static function fields(): array
    {
        return array_diff_key(Prospect::FIELDS, array_flip(['notes', 'priority', 'assigned_to']));
    }

    public function resolveSimpleInput(Admin $admin, GooglePlaceReference $reference, array $input): array
    {
        $proof = [];
        if (! empty($input['enrichment_token'])) {
            $proof = app(PlacesContext::class)->read($admin, 'independent_enrichment', $input['enrichment_token']);
            if (($proof['reference_id'] ?? null) !== $reference->id) {
                throw ValidationException::withMessages(['enrichment_token' => 'Os dados consultados pertencem a outra referência. Consulte novamente.']);
            }
        }
        $input['origin_reference'] = $input['origin_reference'] ?? 'Dados comerciais declarados manualmente pelo administrador';
        $input['confirmed_fields'] = array_values(array_intersect(is_array($input['confirmed_fields'] ?? null) ? $input['confirmed_fields'] : [], ['notes', 'priority', 'assigned_to']));
        $normalized = (new NormalizeProspect)->handle($input);
        $input['field_provenance'] = [];
        foreach (self::fields() as $field => $label) {
            if (! is_string($normalized[$field] ?? null) || $normalized[$field] === '') {
                continue;
            }
            $automatic = isset($proof['fields'][$field]) && ($field === 'phone'
                ? (new NormalizeProspect)->phoneKey($normalized[$field]) === (new NormalizeProspect)->phoneKey($proof['fields'][$field])
                : $normalized[$field] === $proof['fields'][$field]);
            if ($automatic) {
                $input['field_provenance'][$field] = ['kind' => in_array($proof['source_kind'] ?? null, array_keys(self::KINDS), true) ? $proof['source_kind'] : 'licensed_source', 'source' => $proof['source'], 'confirmed' => true, 'evidence' => $proof['evidence']];
            }
        }

        $input['google_import_proof_verified'] = false;
        if (! empty($input['google_enrichment_token'])) {
            $google = app(PlacesContext::class)->read($admin, 'google_import', $input['google_enrichment_token']);
            $applied = json_decode(is_string($input['google_applied_fields'] ?? null) ? $input['google_applied_fields'] : '[]', true);
            if (($google['reference_id'] ?? null) !== $reference->id || ! is_array($applied) || ! array_is_list($applied) || count($applied) > count(self::fields())) {
                throw ValidationException::withMessages(['google_enrichment_token' => 'Revise os campos e a referência da importação Google.']);
            }
            if (! config('business_explorer.google_import_enabled') || ! config('business_explorer.google_persistence_enabled')) {
                throw ValidationException::withMessages(['google_enrichment_token' => 'O salvamento de conteúdo Google está desabilitado neste ambiente. Seus dados foram preservados.']);
            }
            if ($applied && ! in_array($input['google_origin_confirmed'] ?? null, [true, 1, '1'], true)) {
                throw ValidationException::withMessages(['google_origin_confirmed' => 'Confirme a origem Google e a restrição contratual antes de salvar.']);
            }
            foreach ($applied as $field) {
                if (! is_string($field) || ! array_key_exists($field, $google['fields'])) {
                    throw ValidationException::withMessages(['google_enrichment_token' => 'A importação contém um campo que não foi consultado.']);
                }
                if (! empty($normalized[$field]) && ! isset($input['field_provenance'][$field])) {
                    $input['field_provenance'][$field] = ['kind' => 'google_places', 'source' => $google['source'], 'confirmed' => true, 'evidence' => $google['evidence'], 'edited' => $normalized[$field] !== $google['fields'][$field]];
                }
            }
            $input['google_import_proof_verified'] = true;
            $input['google_queried_at'] = $google['queried_at'];
            if ($applied && ($input['origin_reference'] ?? '') === 'Dados comerciais declarados manualmente pelo administrador') {
                $input['origin_reference'] = 'Cadastro assistido: dados Google e demais origens discriminados por campo';
            }
        }

        return $input;
    }

    public static function rules(): array
    {
        return ['field_provenance' => ['required', 'array:'.implode(',', array_keys(self::fields()))],
            'field_provenance.*' => ['array:kind,source,confirmed,evidence,edited'],
            'field_provenance.*.edited' => ['sometimes', 'boolean'],
            'field_provenance.*.evidence' => ['sometimes', 'nullable', 'regex:/^[a-f0-9]{64}$/D'],
            'field_provenance.*.kind' => ['nullable', Rule::in(array_keys(self::KINDS))],
            'field_provenance.*.source' => ['nullable', 'string', 'min:10', 'max:500'],
            'field_provenance.*.confirmed' => ['sometimes', 'boolean']];
    }

    public static function independentSource(mixed $value): bool
    {
        return is_string($value) && ! preg_match('~google|goo\.gl|g\.co|gstatic|googleapis~iu', $value);
    }

    public static function attributes(): array
    {
        $attributes = ['field_provenance' => 'origens dos campos'];
        foreach (self::fields() as $field => $label) {
            $attributes['field_provenance.'.$field] = 'origem de '.$label;
            $attributes['field_provenance.'.$field.'.source'] = 'fonte independente de '.$label;
            $attributes['field_provenance.'.$field.'.kind'] = 'tipo da fonte de '.$label;
            $attributes['field_provenance.'.$field.'.confirmed'] = 'confirmação de '.$label;
        }

        return $attributes;
    }

    public function validate(array $input, bool $allowDeclared = false): array
    {
        $provenance = $input['field_provenance'] ?? null;
        if (is_array($provenance)) {
            foreach ($provenance as &$entry) {
                if (is_array($entry) && is_string($entry['source'] ?? null)) {
                    $entry['source'] = trim(preg_replace('/[\p{C}\p{Z}\s]+/u', ' ', $entry['source']) ?? '');
                }
            }
            unset($entry);
        }
        $rules = self::rules();
        if ($allowDeclared) {
            $rules['field_provenance'][0] = 'present';
        }
        $validator = Validator::make(['field_provenance' => $provenance], $rules, ProspectRules::messages(), self::attributes());
        $result = [];
        $validator->after(function ($validator) use ($input, $provenance, $allowDeclared, &$result): void {
            foreach (self::fields() as $field => $label) {
                if (! is_scalar($input[$field] ?? null) || trim((string) $input[$field]) === '') {
                    continue;
                }
                $entry = is_array($provenance) && is_array($provenance[$field] ?? null) ? $provenance[$field] : [];
                if ($allowDeclared && $entry === []) {
                    continue;
                }
                if (! in_array($entry['kind'] ?? null, array_keys(self::KINDS), true) || ! in_array($entry['confirmed'] ?? null, [true, 1, '1'], true)) {
                    $validator->errors()->add('field_provenance.'.$field.'.confirmed', 'Confirme a fonte independente de '.$label.'.');
                }
                $source = $entry['source'] ?? null;
                $google = ($entry['kind'] ?? null) === 'google_places';
                $googleAllowed = $google && $allowDeclared && ($input['google_import_proof_verified'] ?? false) === true && config('business_explorer.google_import_enabled') && config('business_explorer.google_persistence_enabled');
                if (($google && ! $googleAllowed) || (! $google && ! self::independentSource($source)) || ! is_string($source) || mb_strlen($source) < 10) {
                    $validator->errors()->add('field_provenance.'.$field.'.source', 'Informe a fonte independente de '.$label.'; conteúdo Google não autoriza armazenamento.');
                }
                $result[$field] = ['kind' => $entry['kind'] ?? '', 'source' => $source ?? '', 'confirmed' => true];
                if ($google) {
                    $result[$field]['edited'] = (bool) ($entry['edited'] ?? false);
                }
                if (! empty($entry['evidence'])) {
                    $result[$field]['evidence'] = $entry['evidence'];
                }
            }
        });
        $validator->validate();
        ksort($result);

        return $result;
    }
}
