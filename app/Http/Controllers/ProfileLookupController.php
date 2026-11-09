<?php

namespace App\Http\Controllers;

use App\Services\ProfileRegistryLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProfileLookupController extends Controller
{
    public function __invoke(Request $request, ProfileRegistryLookup $registry): JsonResponse
    {
        $sources = $registry->sources($request);
        abort_if($sources === [], 404);
        $data = $request->validate([
            'source' => ['required', Rule::in($sources)],
            'consent' => ['accepted'],
            'dni' => ['prohibited'],
        ]);

        $dni = $request->user()->document_number;
        if (! is_string($dni) || ! preg_match('/^[0-9]{8}$/D', $dni)) {
            throw ValidationException::withMessages(['source' => 'Guarda un DNI válido en tus datos personales antes de consultar.']);
        }

        return response()->json($registry->lookup($data['source'], $dni))->header('Cache-Control', 'no-store');
    }
}
