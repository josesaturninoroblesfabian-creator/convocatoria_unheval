<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ProfileRegistryLookup
{
    public function canLookUpDni(Request $request): bool
    {
        return $this->isLocalRequest($request) && filled(config('services.profile_registry.apisperu_token'));
    }

    public function sources(Request $request): array
    {
        return $this->isLocalRequest($request)
            && $request->user()?->role === 'postulante'
            && ! str_ends_with(mb_strtolower($request->user()->email), '@demo.test')
            && filled(config('services.profile_registry.apisted_token'))
            ? ['sunedu', 'minedu']
            : [];
    }

    private function isLocalRequest(Request $request): bool
    {
        return config('services.profile_registry.enabled')
            && app()->environment('local')
            && in_array($request->getHost(), ['localhost', '127.0.0.1', '[::1]'], true)
            && in_array($request->server('REMOTE_ADDR'), ['127.0.0.1', '::1'], true)
            && ! $request->headers->has('X-Forwarded-For')
            && ! $request->headers->has('Forwarded');
    }

    public function lookup(string $source, string $dni): array
    {
        $client = Http::acceptJson()->connectTimeout(4)->timeout(20)->withoutRedirecting();
        try {
            $response = $source === 'dni'
                ? $client->get('https://dniruc.apisperu.com/api/v1/dni/'.$dni, ['token' => config('services.profile_registry.apisperu_token')])
                : $client->withToken(config('services.profile_registry.apisted_token'))->post(
                    'https://apisted.com/api/minedu/'.($source === 'sunedu' ? 'universitarios' : 'titulos'),
                    ['dni' => $dni],
                );
        } catch (ConnectionException) {
            return $this->unavailable();
        }

        if (! $response->ok()) {
            return $this->failedResponse($response);
        }

        $body = $response->json();
        if (! is_array($body)) {
            return $this->unavailable();
        }
        if ($source === 'dni') {
            if (($body['dni'] ?? null) !== $dni) {
                return $this->unavailable();
            }

            $name = implode(' ', array_filter(array_map(
                fn (string $field): string => $this->plainText($body[$field] ?? null, 150),
                ['nombres', 'apellidoPaterno', 'apellidoMaterno'],
            )));

            return $name !== '' ? ['status' => 'found', 'name' => mb_substr($name, 0, 150)] : $this->unavailable();
        }

        if (($body['criterio'] ?? null) !== $dni || ! is_array($body['registros'] ?? null)) {
            return $this->unavailable();
        }
        if ($body['desactualizado'] ?? false) {
            return $this->unavailable();
        }

        $records = [];
        foreach (array_slice($body['registros'], 0, 40) as $record) {
            if (! is_array($record) || (isset($record['numero_documento']) && $record['numero_documento'] !== $dni)) {
                continue;
            }
            $title = $this->plainText($record['grado_o_titulo'] ?? null, 250);
            if ($title === '') {
                continue;
            }
            $date = $this->plainText($record[$source === 'sunedu' ? 'fecha_diploma' : 'fecha_emision'] ?? null, 40);
            $records[] = [
                'title' => $title,
                'institution' => $this->plainText($record[$source === 'sunedu' ? 'universidad' : 'institucion_educativa'] ?? null, 200),
                'date' => $date,
                'awarded_at' => $this->awardDate($date),
                'kind' => $source === 'minedu' ? 'instituto' : $this->universityKind($record['tipo'] ?? null, $title),
            ];
        }

        if ($records === [] && ($body['encontrado'] ?? null) !== false) {
            return $this->unavailable();
        }

        return ['status' => $records ? 'found' : 'empty', 'records' => $records];
    }

    private function universityKind(mixed $type, string $title): ?string
    {
        $kind = match (is_string($type) ? Str::lower($type) : null) {
            'grado_bachiller', 'bachiller' => 'bachiller',
            'grado_maestro', 'grado_magister', 'maestro', 'magister' => 'maestro',
            'grado_doctor', 'doctor' => 'doctor',
            'titulo_profesional', 'titulo' => 'titulo',
            'segunda_especialidad', 'titulo_segunda_especialidad' => 'segunda_especialidad',
            default => null,
        };
        if ($kind !== null) {
            return $kind;
        }

        $normalized = Str::upper(Str::ascii($title));

        return match (true) {
            (bool) preg_match('/^BACHILLER\b/', $normalized) => 'bachiller',
            (bool) preg_match('/^(MAESTR[OA]|MAESTRIA|MAGISTER)\b/', $normalized) => 'maestro',
            (bool) preg_match('/^DOCTOR(?:ADO|A)?\b/', $normalized) => 'doctor',
            default => null,
        };
    }

    private function awardDate(string $value): ?string
    {
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/D', $value, $parts)) {
            $value = $parts[3].'-'.$parts[2].'-'.$parts[1];
        }
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $parts)
            || ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])
            || $value > now()->toDateString()) {
            return null;
        }

        return $value;
    }

    private function plainText(mixed $value, int $limit): string
    {
        return is_string($value) ? mb_substr(trim($value), 0, $limit) : '';
    }

    private function failedResponse(Response $response): array
    {
        return match ($response->status()) {
            401, 403 => ['status' => 'unavailable', 'message' => 'El proveedor no autorizó esta consulta. Revisa el token y los permisos del servicio.'],
            402 => ['status' => 'unavailable', 'message' => 'La consulta requiere un plan habilitado o saldo disponible en el proveedor.'],
            429 => ['status' => 'unavailable', 'message' => 'El proveedor alcanzó su límite de consultas. Intenta más tarde.'],
            default => $this->unavailable(),
        };
    }

    private function unavailable(): array
    {
        return ['status' => 'unavailable', 'message' => 'El proveedor no devolvió un resultado válido. Puedes continuar con tus datos y sustentos.'];
    }
}
