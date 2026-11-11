<?php

namespace App\Http\Requests\Admin;

use App\Models\PreRegistration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPreRegistrationsRequest extends FormRequest
{
    public const SORTS = ['business_name', 'category', 'city', 'state', 'status', 'created_at'];

    public const COLUMNS = ['business_name', 'category', 'city', 'state', 'status', 'created_at'];

    public function authorize(): bool
    {
        return $this->user('admin')?->is_active === true;
    }

    public static function filterRules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(PreRegistration::STATUSES)],
            'state' => ['nullable', 'string', 'size:2', 'alpha:ascii'],
            'category' => ['nullable', 'string', Rule::in(config('prelaunch.categories'))],
            'q' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function rules(): array
    {
        return [
            ...self::filterRules(),
            'sort' => ['sometimes', Rule::in(self::SORTS)],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
            'per_page' => ['sometimes', 'integer', Rule::in([10, 25, 50, 100])],
            'page' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
            'columns' => ['sometimes', 'array', 'max:6'],
            'columns.*' => ['string', 'distinct', Rule::in(self::COLUMNS)],
        ];
    }
}
