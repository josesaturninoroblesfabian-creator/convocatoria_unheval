<?php

namespace App\Services;

use App\Models\Application;
use App\Models\AuditEvent;
use App\Models\Evaluation;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class NombramientoEvaluation
{
    public function progress(Application $application, User $user): ?array
    {
        $template = $application->recruitmentCall->evaluation_template;
        if (! $template) {
            return null;
        }

        $sheet = $application->external_evaluation;
        $outcome = $application->evaluation_outcome;
        $required = $application->vacancy->jurors()->count();
        $confirmed = count($sheet['confirmations'] ?? []);
        $externalClosed = $sheet !== null && $confirmed === $required;
        $stage = match (true) {
            ($outcome['stage'] ?? null) === 'NO_SUPERA_EXTERNA' => 'NO_SUPERA_EXTERNA',
            $outcome !== null => 'COMPLETA',
            $externalClosed => 'INTERNA',
            default => 'EXTERNA',
        };

        return [
            'stage' => $stage,
            'minimums' => $template['minimums'][$application->vacancy->category],
            'external' => $externalClosed ? $sheet['total'] : null,
            'ownExternal' => isset($sheet['confirmations'][$user->id]) ? $sheet['scores'] : null,
            'proposal' => $sheet ? [
                'scores' => $sheet['scores'],
                'total' => $sheet['total'],
                'notes' => $sheet['notes'],
                'revision' => $sheet['revision'],
                'confirmed' => $confirmed,
                'required' => $required,
            ] : null,
            'outcome' => $outcome,
        ];
    }

    public function record(Application $application, User $user, array $data): string
    {
        $progress = $this->progress($application, $user);
        if (($data['stage'] ?? null) === 'externa' && $progress['stage'] === 'EXTERNA') {
            return $this->recordExternal($application, $user, $data, $progress['minimums']);
        }
        if (($data['stage'] ?? null) === 'interna' && $progress['stage'] === 'INTERNA') {
            return $this->recordInternal($application, $user, $data, $progress['minimums']);
        }

        throw ValidationException::withMessages(['stage' => 'La etapa cambió o todavía no está habilitada. Actualiza la página.']);
    }

    private function recordExternal(Application $application, User $user, array $data, array $minimums): string
    {
        $sheet = $application->external_evaluation;
        $revision = $sheet['revision'] ?? 0;
        if (! isset($data['revision']) || (int) $data['revision'] !== $revision) {
            throw ValidationException::withMessages(['revision' => 'La hoja externa cambió. Revisa la versión vigente antes de continuar.']);
        }

        $action = $data['action'] ?? null;
        if ($action === 'proponer') {
            [$scores, $total] = $this->score($data['scores'] ?? [], $application->recruitmentCall->evaluation_template['external']);
            $sheet = ['scores' => $scores, 'total' => $total / 1000, 'notes' => $data['notes'], 'revision' => $revision + 1, 'confirmations' => []];
        } elseif ($action !== 'confirmar' || ! $sheet || isset($sheet['confirmations'][$user->id])) {
            throw ValidationException::withMessages(['action' => 'Se necesita una propuesta vigente que aún no hayas confirmado.']);
        }

        $sheet['confirmations'][$user->id] = now()->toIso8601String();
        $application->update(['external_evaluation' => $sheet]);
        AuditEvent::record($action === 'proponer' ? 'HOJA_EXTERNA_PROPUESTA' : 'HOJA_EXTERNA_CONFIRMADA', $application, [
            'revision' => $sheet['revision'],
            'notes' => $data['notes'],
            'scores' => $action === 'proponer' ? $sheet['scores'] : null,
        ]);

        if (count($sheet['confirmations']) !== $application->vacancy->jurors()->count()) {
            return 'Conformidad registrada. La hoja se cierra cuando confirmen los tres jurados.';
        }
        if ($sheet['total'] < $minimums['external']) {
            $this->close($application, 'NO_SUPERA_EXTERNA', (float) $sheet['total'], null, null);
        }

        return 'Hoja externa cerrada con la conformidad de la comisión.';
    }

    private function recordInternal(Application $application, User $user, array $data, array $minimums): string
    {
        if ($application->evaluations()->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages(['scores' => 'Tu evaluación interna ya está cerrada.']);
        }

        $criteria = $application->recruitmentCall->evaluation_template['internal'];
        $details = [];
        if (isset($criteria[0]['items'])) {
            [$scores, $internalMilli, $details] = $this->scoreInternalItems($data['item_scores'] ?? [], $criteria);
        } else {
            [$scores, $internalMilli] = $this->score($data['scores'] ?? [], $criteria);
        }
        $sheet = $application->external_evaluation;
        $externalMilli = (int) round($sheet['total'] * 1000);
        $evaluation = $application->evaluations()->create([
            'user_id' => $user->id,
            'scores' => [...$sheet['scores'], ...$scores, ...($details ? ['_items' => $details] : [])],
            'total' => ($internalMilli + $externalMilli) / 1000,
            'notes' => $data['notes'],
            'closed_at' => now(),
        ]);
        AuditEvent::record('HOJA_INTERNA_CERRADA', $application, ['evaluation_id' => $evaluation->id]);

        $evaluations = $application->evaluations()->get();
        $jurorCount = $application->vacancy->jurors()->count();
        if ($evaluations->count() === $jurorCount) {
            $totalSum = $evaluations->sum(fn (Evaluation $row): int => (int) round((float) $row->total * 1000));
            $internalSum = $totalSum - $externalMilli * $jurorCount;
            $stage = match (true) {
                $internalSum < $minimums['internal'] * 1000 * $jurorCount => 'NO_SUPERA_INTERNA',
                $totalSum < $minimums['total'] * 1000 * $jurorCount => 'NO_SUPERA_TOTAL',
                default => 'SUPERA_MINIMOS',
            };
            $this->close($application, $stage, (float) $sheet['total'], round($internalSum / ($jurorCount * 1000), 3), round($totalSum / ($jurorCount * 1000), 3));
        }

        return 'Calificación interna cerrada. Se consolida al finalizar las tres evaluaciones.';
    }

    private function close(Application $application, string $stage, float $external, ?float $internal, ?float $total): void
    {
        $outcome = ['stage' => $stage, 'passed' => $stage === 'SUPERA_MINIMOS', 'external' => $external, 'internal' => $internal, 'total' => $total];
        $application->update(['status' => 'EVALUADA', 'evaluation_outcome' => $outcome]);
        AuditEvent::record('EVALUACION_CONSOLIDADA', $application, $outcome);
    }

    private function scoreInternalItems(array $submitted, array $criteria): array
    {
        if (count($submitted) !== count($criteria)) {
            throw ValidationException::withMessages(['item_scores' => 'Completa los criterios de sílabo, clase modelo y entrevista.']);
        }

        $subtotals = [];
        $details = [];
        $totalMilli = 0;
        foreach ($criteria as $criterion) {
            $key = $criterion['key'];
            $values = $submitted[$key] ?? null;
            if (! is_array($values) || ! array_is_list($values) || count($values) !== count($criterion['items'])) {
                throw ValidationException::withMessages(['item_scores' => 'Completa todos los criterios de '.$criterion['name'].'.']);
            }

            $scale = array_map('floatval', $criterion['scale']);
            $subtotalMilli = 0;
            foreach ($values as $index => $value) {
                if (! is_numeric($value) || ! in_array((float) $value, $scale, true)) {
                    throw ValidationException::withMessages(['item_scores' => 'Selecciona un valor de la escala para '.$criterion['items'][$index].'.']);
                }
                $details[$key][] = (float) $value;
                $subtotalMilli += (int) round((float) $value * 1000);
            }
            $subtotals[$key] = $subtotalMilli / 1000;
            $totalMilli += $subtotalMilli;
        }

        return [$subtotals, $totalMilli, $details];
    }

    private function score(array $submitted, array $criteria): array
    {
        $scores = [];
        $milli = 0;
        foreach ($criteria as $criterion) {
            $value = $submitted[$criterion['key']] ?? null;
            if (! is_numeric($value) || $value < 0 || $value > $criterion['max'] || ! preg_match('/^\d+(\.\d{1,3})?$/', (string) $value)) {
                throw ValidationException::withMessages(['scores' => 'Completa todos los rubros de la etapa con puntajes dentro de su tope.']);
            }
            $scores[$criterion['key']] = (string) $value;
            $milli += (int) round((float) $value * 1000);
        }
        if (count($scores) !== count($submitted)) {
            throw ValidationException::withMessages(['scores' => 'Solo se admiten rubros de la etapa actual.']);
        }

        return [$scores, $milli];
    }
}
