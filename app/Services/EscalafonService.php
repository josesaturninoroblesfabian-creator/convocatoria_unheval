<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Punto de integración con el escalafón docente institucional.
 *
 * Expone una consulta de la trayectoria del docente (categoría, régimen,
 * antigüedad) por DNI contra un endpoint configurable. La integración SSO y
 * escalafón queda lista para activarse cuando la universidad publique su
 * proveedor OIDC/SAML y el servicio de escalafón.
 */
class EscalafonService
{
    public function enabled(): bool
    {
        return config('services.sso.enabled')
            && filled(config('services.sso.escalafon_endpoint'))
            && filled(config('services.sso.escalafon_token'));
    }

    /**
     * @return array{status: string, data?: array, message?: string}
     */
    public function lookup(string $documentNumber): array
    {
        if (! $this->enabled()) {
            return ['status' => 'disabled', 'message' => 'La consulta de escalafón no está configurada.'];
        }

        try {
            $response = Http::acceptJson()
                ->withToken(config('services.sso.escalafon_token'))
                ->connectTimeout(4)
                ->timeout(20)
                ->get(config('services.sso.escalafon_endpoint'), ['document_number' => $documentNumber]);
        } catch (ConnectionException) {
            return ['status' => 'unavailable', 'message' => 'El servicio de escalafón no respondió.'];
        }

        if (! $response->ok()) {
            return ['status' => 'unavailable', 'message' => 'El servicio de escalafón devolvió un error.'];
        }

        $body = $response->json();

        return is_array($body) && $body !== [] ? ['status' => 'found', 'data' => $body] : ['status' => 'empty', 'message' => 'Sin registros de escalafón para este documento.'];
    }
}
