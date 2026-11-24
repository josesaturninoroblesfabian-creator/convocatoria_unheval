<?php

namespace App\Services;

use App\Models\Application;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class AppealService
{
    public const WINDOW_HOURS = 24;

    public function canFileReconsideracion(Application $application, string $stage): bool
    {
        $publishedAt = $this->publishedAt($application, $stage);
        if ($publishedAt === null) {
            return false;
        }
        if ($application->appeals()->where('type', 'reconsideracion')->where('stage', $stage)->exists()) {
            return false;
        }

        return now()->lte($publishedAt->addHours(self::WINDOW_HOURS));
    }

    public function canFileApelacion(Application $application, string $stage): bool
    {
        $reconsideracion = $application->appeals()->where('type', 'reconsideracion')->where('stage', $stage)->first();
        if (! $reconsideracion || $reconsideracion->status !== 'RESUELTA' || $reconsideracion->resolved_at === null) {
            return false;
        }
        if ($application->appeals()->where('type', 'apelacion')->where('stage', $stage)->exists()) {
            return false;
        }

        return now()->lte($reconsideracion->resolved_at->addHours(self::WINDOW_HOURS));
    }

    public function assertCanFile(Application $application, string $type, string $stage): void
    {
        $canFile = $type === 'reconsideracion'
            ? $this->canFileReconsideracion($application, $stage)
            : $this->canFileApelacion($application, $stage);

        if (! $canFile) {
            throw ValidationException::withMessages(['appeal' => 'La impugnación no está habilitada o el plazo venció. Revisa el cronograma de resultados.']);
        }
    }

    private function publishedAt(Application $application, string $stage): ?CarbonImmutable
    {
        $publishedAt = $application->vacancy->stage_publications[$stage]['published_at'] ?? null;

        return $publishedAt ? CarbonImmutable::parse($publishedAt) : null;
    }
}
