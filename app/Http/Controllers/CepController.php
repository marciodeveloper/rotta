<?php

namespace App\Http\Controllers;

use App\Contracts\CepLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class CepController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, CepLookup $lookup): JsonResponse
    {
        $validated = $request->validate(['postal_code' => ['required', 'regex:/^\d{5}-?\d{3}$/']]);
        try {
            $result = $lookup->find($validated['postal_code']);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Não foi possível consultar o CEP agora. Tente novamente ou preencha o endereço manualmente.',
            ], 503);
        }

        return $result === null
            ? response()->json(['message' => 'CEP não encontrado. Use o preenchimento manual.'], 404)
            : response()->json(['data' => $result]);
    }
}
