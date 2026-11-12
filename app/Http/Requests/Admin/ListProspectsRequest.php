<?php

namespace App\Http\Requests\Admin;

use App\Models\Prospect;
use App\Services\ProspectRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListProspectsRequest extends FormRequest
{
    public const COLUMNS = ['business_name', 'category', 'city', 'state', 'priority', 'assigned_to', 'commercial_status', 'created_at'];

    public function authorize(): bool
    {
        return $this->user('admin')?->can('viewAny', Prospect::class) ?? false;
    }

    public static function filterRules(): array
    {
        return ['q' => ['nullable', 'string', 'max:100'], 'city' => ['nullable', 'string', 'max:100'], 'category' => ['nullable', 'string', 'max:100'], 'state' => ['nullable', Rule::in(ProspectRules::STATES)], 'assigned_to' => ['nullable', 'uuid'], 'priority' => ['nullable', Rule::in(array_keys(Prospect::PRIORITIES))], 'commercial_status' => ['nullable', Rule::in(['identified'])], 'blocked' => ['nullable', Rule::in(['yes', 'no'])], 'sort' => ['sometimes', Rule::in(self::COLUMNS)], 'direction' => ['sometimes', Rule::in(['asc', 'desc'])], 'per_page' => ['sometimes', 'integer', Rule::in([10, 25, 50, 100])], 'page' => ['sometimes', 'integer', 'min:1', 'max:1000000'], 'columns' => ['sometimes', 'array', 'max:8'], 'columns.*' => ['string', 'distinct', Rule::in(self::COLUMNS)]];
    }

    public function rules(): array
    {
        return self::filterRules();
    }

    public function messages(): array
    {
        return ProspectRules::messages();
    }

    public function attributes(): array
    {
        return [...ProspectRules::attributes(), 'q' => 'busca', 'blocked' => 'bloqueio', 'sort' => 'ordenação', 'direction' => 'direção', 'per_page' => 'quantidade por página', 'page' => 'página', 'columns' => 'colunas'];
    }
}
