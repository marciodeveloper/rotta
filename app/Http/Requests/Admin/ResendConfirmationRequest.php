<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ResendConfirmationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->can('manage-pre-registration', $this->route('preRegistration')) ?? false;
    }

    public function rules(): array
    {
        return ['status_version' => ['required', 'integer', 'min:1']];
    }
}
