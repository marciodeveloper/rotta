<?php

namespace App\Http\Requests\Admin;

use App\Models\GooglePlaceReference;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class BusinessExplorerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->can('viewAny', GooglePlaceReference::class) ?? false;
    }

    public function messages(): array
    {
        return ['required_without' => 'Informe :attribute.', 'string' => ':attribute deve ser um texto.', 'min' => ':attribute deve ter pelo menos :min caracteres.', 'max' => ':attribute excede o tamanho permitido.', 'uuid' => 'A referência selecionada é inválida.', 'prohibits' => 'Envie uma consulta ou uma continuação, não ambas.'];
    }

    public function attributes(): array
    {
        return ['query' => 'a pesquisa', 'cursor' => 'a continuação da pesquisa', 'token' => 'a referência', 'reference_id' => 'a referência salva'];
    }

    protected function failedValidation(Validator $validator): never
    {
        // Even non-AJAX failures must not flash provider cursors/content into a session.
        throw new HttpResponseException(response()->json(['message' => 'Revise os dados informados.', 'errors' => $validator->errors()], 422));
    }
}
