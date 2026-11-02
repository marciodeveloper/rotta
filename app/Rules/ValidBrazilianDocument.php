<?php

namespace App\Rules;

use App\Services\DocumentValidator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class ValidBrazilianDocument implements ValidationRule
{
    public function __construct(private string $applicantType) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! (new DocumentValidator)->isValidFor($this->applicantType, $value)) {
            $fail($this->applicantType === 'professional'
                ? 'Informe um CPF válido, incluindo os dígitos verificadores.'
                : 'Informe um CNPJ válido, incluindo os dígitos verificadores.');
        }
    }
}
