<?php

namespace App\Http\Requests\Admin;

use App\Services\NormalizeProspect;
use App\Services\ProspectRules;
use Illuminate\Foundation\Http\FormRequest;

class ProspectContactRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge((new NormalizeProspect)->decisions($this->all()));
    }

    public function authorize(): bool
    {
        return $this->user('admin')?->can('manageContact', $this->route('prospect')) ?? false;
    }

    public function rules(): array
    {
        return ['blocked' => ['required', 'boolean'], 'reason' => ['required', 'string', 'min:10', 'max:500'], 'lock_version' => ['required', 'integer', 'min:1']];
    }

    public function messages(): array
    {
        return ProspectRules::messages();
    }

    public function attributes(): array
    {
        return ProspectRules::attributes();
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.prospects.show', ['prospect' => $this->route('prospect'), ...array_intersect_key($this->query(), ListProspectsRequest::filterRules())]);
    }
}
