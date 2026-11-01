<?php

namespace App\Http\Requests\Admin;

use App\Models\PreRegistration;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePreRegistrationStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('admin')?->is_active === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(PreRegistration::STATUSES)],
            'status_version' => ['required', 'integer', 'min:1'],
            'reason' => [Rule::requiredIf(fn (): bool => in_array($this->string('status')->toString(), [PreRegistration::STATUS_REJECTED, PreRegistration::STATUS_ARCHIVED], true)), 'nullable', 'string', 'min:10', 'max:500'],
        ];
    }
}
