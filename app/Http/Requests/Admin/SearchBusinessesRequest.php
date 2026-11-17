<?php

namespace App\Http\Requests\Admin;

class SearchBusinessesRequest extends BusinessExplorerRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('query'))) {
            $this->merge(['query' => trim(preg_replace('/[\p{C}\p{Z}\s]+/u', ' ', $this->input('query')))]);
        }
    }

    public function rules(): array
    {
        return ['query' => ['required_without:cursor', 'prohibits:cursor', 'string', 'min:3', 'max:200'], 'cursor' => ['sometimes', 'required', 'string', 'max:16384']];
    }
}
