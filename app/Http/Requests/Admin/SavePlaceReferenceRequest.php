<?php

namespace App\Http\Requests\Admin;

class SavePlaceReferenceRequest extends BusinessExplorerRequest
{
    public function rules(): array
    {
        return ['token' => ['required', 'string', 'max:8192']];
    }
}
