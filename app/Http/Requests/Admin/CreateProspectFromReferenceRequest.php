<?php

namespace App\Http\Requests\Admin;

use App\Services\ReferenceFieldProvenance;
use App\Services\SavedPlaceReferences;

class CreateProspectFromReferenceRequest extends SaveProspectRequest
{
    public function authorize(): bool
    {
        return parent::authorize() && ($this->user('admin')?->can('useForProspect', $this->route('reference')) ?? false);
    }

    public function rules(): array
    {
        return [...parent::rules(), ...($this->input('assisted_mode') === 'simple' ? [] : ReferenceFieldProvenance::rules()),
            'google_enrichment_token' => ['nullable', 'string', 'max:32768'], 'google_applied_fields' => ['nullable', 'string', 'max:2048'], 'google_origin_confirmed' => ['sometimes', 'boolean'],
            'assisted_mode' => ['sometimes', 'in:simple'], 'enrichment_token' => ['nullable', 'string', 'max:32768'],
            'independent_data_confirmed' => $this->input('assisted_mode') === 'simple' ? ['sometimes', 'boolean'] : ['required', 'accepted'], 'origin_reference' => ['required', 'string', 'min:10', 'max:500']];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        if ($this->input('assisted_mode') === 'simple' && $this->input('origin_reference') === null) {
            $this->merge(['origin_reference' => 'Dados comerciais declarados manualmente pelo administrador']);
        }
    }

    public function messages(): array
    {
        return [...parent::messages(), 'independent_data_confirmed.required' => 'Confirme a origem independente dos dados comerciais.', 'independent_data_confirmed.accepted' => 'Confirme a origem independente dos dados comerciais.', 'origin_reference.required' => 'Descreva a fonte independente ou a licença que permite conservar os dados.'];
    }

    public function attributes(): array
    {
        return [...parent::attributes(), ...ReferenceFieldProvenance::attributes()];
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.explorer.references.create', ['reference' => $this->route('reference'), ...collect(SavedPlaceReferences::returnContext($this->query()))->mapWithKeys(fn ($value, $key) => ['explorer_'.$key => $value])->all()]);
    }
}
