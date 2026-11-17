<?php

namespace App\Http\Requests\Admin;

class PlaceDetailsRequest extends BusinessExplorerRequest
{
    public function rules(): array
    {
        return ['token' => ['required_without:reference_id', 'prohibits:reference_id', 'string', 'max:8192'], 'reference_id' => ['sometimes', 'required', 'uuid']];
    }
}
