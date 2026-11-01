<?php

namespace App\Http\Controllers;

use App\Contracts\CepLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CepController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, CepLookup $lookup): JsonResponse
    {
        $validated = $request->validate(['postal_code' => ['required', 'regex:/^\d{5}-?\d{3}$/']]);
        $result = $lookup->find($validated['postal_code']);

        return $result === null
            ? response()->json(['message' => 'CEP não encontrado. Use o preenchimento manual.'], 404)
            : response()->json(['data' => $result]);
    }
}
