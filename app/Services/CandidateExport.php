<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Evaluation;

/**
 * Construye una vista estructurada y estable de un expediente para su consumo
 * por herramientas de análisis, auditoría o modelos de IA.
 *
 * El sistema guarda los datos de forma normalizada; este servicio los consolida
 * en un único arreglo JSON estable, de modo que un motor externo pueda leer un
 * candidato sin acoplarse al esquema interno de Eloquent.
 */
class CandidateExport
{
    /**
     * @return array<string, mixed>
     */
    public function profile(Application $application): array
    {
        $application->loadMissing('user', 'vacancy.recruitmentCall', 'documents', 'evaluations');
        $vacancy = $application->vacancy;
        $call = $vacancy->recruitmentCall;

        return [
            'reference' => $application->reference,
            'status' => $application->status,
            'submitted_at' => $application->submitted_at?->toIso8601String(),
            'process' => [
                'call' => $call->code,
                'modality' => $call->modality,
                'vacancy' => $vacancy->school,
                'faculty' => $vacancy->faculty,
                'airhsp' => $vacancy->airhsp,
                'cap' => $vacancy->cap,
                'category' => $vacancy->category,
                'dedication' => $vacancy->dedication,
                'weekly_hours' => $vacancy->weekly_hours,
            ],
            'applicant' => [
                'name' => $application->applicant_snapshot['name'] ?? $application->user->name,
                'document_number' => $application->applicant_snapshot['document_number'] ?? $application->user->document_number,
            ],
            'declared' => $application->form_data,
            'screening' => $application->screening_snapshot,
            'declared_score' => $application->declared_score,
            'documents' => $application->documents
                ->map(fn ($document): array => [
                    'key' => $document->requirement_key,
                    'name' => $document->name,
                    'mime' => $document->mime,
                    'size' => $document->size,
                    'sha256' => $document->sha256,
                    'review_status' => $document->review_status,
                ])
                ->values()
                ->all(),
            'evaluations' => $application->evaluations
                ->map(fn (Evaluation $evaluation): array => [
                    'scores' => $evaluation->scores,
                    'total' => $evaluation->total,
                    'notes' => $evaluation->notes,
                    'closed_at' => $evaluation->closed_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'outcome' => $application->evaluation_outcome,
        ];
    }
}
