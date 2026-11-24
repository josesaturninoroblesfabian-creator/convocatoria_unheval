<?php

namespace App\Console\Commands;

use App\Models\RecruitmentCall;
use App\Services\CandidateExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ExportConvocatoria extends Command
{
    protected $signature = 'unheval:export {code : Código de la convocatoria a exportar}';

    protected $description = 'Exporta los expedientes de una convocatoria a JSON estructurado para análisis, auditoría o herramientas de IA';

    public function handle(CandidateExport $export): int
    {
        $call = RecruitmentCall::where('code', $this->argument('code'))->first();
        if (! $call) {
            $this->error('No se encontró una convocatoria con ese código.');

            return self::FAILURE;
        }

        $applications = $call->applications()->whereNotNull('submitted_at')->orderBy('id')->get();
        $payload = [
            'exported_at' => now()->toIso8601String(),
            'call' => [
                'code' => $call->code,
                'title' => $call->title,
                'modality' => $call->modality,
                'status' => $call->status,
                'opens_at' => $call->opens_at?->toIso8601String(),
                'closes_at' => $call->closes_at?->toIso8601String(),
                'vacancies' => $call->vacancies->map(fn ($vacancy): array => [
                    'school' => $vacancy->school,
                    'faculty' => $vacancy->faculty,
                    'airhsp' => $vacancy->airhsp,
                    'cap' => $vacancy->cap,
                    'category' => $vacancy->category,
                ])->values()->all(),
            ],
            'applications' => $applications->map(fn ($application): array => $export->profile($application))->values()->all(),
        ];

        $path = 'exports/'.$call->code.'.json';
        Storage::disk('local')->put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        $this->info('Exportados '.count($applications).' expedientes en '.$path);

        return self::SUCCESS;
    }
}
