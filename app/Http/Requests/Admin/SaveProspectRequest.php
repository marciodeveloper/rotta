<?php

namespace App\Http\Requests\Admin;

use App\Models\Prospect;
use App\Services\NormalizeProspect;
use App\Services\ProspectRules;
use Illuminate\Foundation\Http\FormRequest;

class SaveProspectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $prospect = $this->route('prospect');

        return $prospect ? ($this->user('admin')?->can('update', $prospect) ?? false) : ($this->user('admin')?->can('create', Prospect::class) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $normalizer = new NormalizeProspect;
        $input = $normalizer->decisions($this->all());
        $this->merge([...$input, ...$normalizer->handle($input), ...$normalizer->source($input)]);
    }

    public function rules(): array
    {
        return [...ProspectRules::fields(), ...ProspectRules::decisions(), ...($this->route('prospect') ? ['lock_version' => ['required', 'integer', 'min:1']] : [...ProspectRules::source(), 'submission_token' => ['required', 'uuid']])];
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
        $context = array_intersect_key($this->query(), ListProspectsRequest::filterRules());

        return $this->route('prospect') ? route('admin.prospects.edit', ['prospect' => $this->route('prospect'), ...$context]) : route('admin.prospects.create', $context);
    }
}
