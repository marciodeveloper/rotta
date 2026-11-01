<?php

namespace App\Http\Requests;

use App\Rules\ValidBrazilianDocument;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResendConfirmationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'applicant_type' => ['required', Rule::in(['company', 'professional'])],
            'document' => ['required', 'string', new ValidBrazilianDocument($this->string('applicant_type')->toString())],
            'email' => ['required', 'email:strict', 'max:254'],
            'cf-turnstile-response' => ['required', 'string', 'max:2048'],
        ];
    }
}
