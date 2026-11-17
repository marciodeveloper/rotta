<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class PlaceIntelligenceRequest extends PlaceDetailsRequest
{
    public function rules(): array
    {
        return [...parent::rules(), 'mode' => ['required', Rule::in(['reputation', 'summary'])], 'cost_acknowledged' => ['required', 'accepted']];
    }

    public function messages(): array
    {
        return [...parent::messages(), 'mode.in' => 'Selecione reputação ou resumo de avaliações.', 'cost_acknowledged.accepted' => 'Confirme o possível consumo de quota e cobrança antes de consultar.'];
    }
}
