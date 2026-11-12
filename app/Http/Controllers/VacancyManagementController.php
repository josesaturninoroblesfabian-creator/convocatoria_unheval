<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfigureScreeningRequest;
use App\Http\Requests\StoreVacancyRequest;
use App\Models\Application;
use App\Models\AuditEvent;
use App\Models\RecruitmentCall;
use App\Models\Vacancy;
use App\Services\VacancyEvaluationWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class VacancyManagementController extends Controller
{
    public function createFirst(): Response
    {
        return $this->form();
    }

    public function storeFirst(StoreVacancyRequest $request): RedirectResponse
    {
        $vacancy = DB::transaction(function () use ($request): Vacancy {
            $call = RecruitmentCall::create([
                ...$request->safe()->only(['modality', 'opens_at', 'closes_at', 'is_demo']),
                'code' => 'CONV-'.now()->year.'-'.Str::ulid(),
                'title' => 'Convocatoria docente · '.$request->validated('school'),
                'description' => 'Consulta las fichas de las plazas, sus requisitos y los documentos del proceso.',
                'status' => 'BORRADOR',
                'schedule' => [['label' => 'Inscripción digital', 'date' => 'Consulta la apertura y el cierre de inscripción publicados.']],
                'rubric' => [],
            ]);
            AuditEvent::record('CONVOCATORIA_CREADA', $call);

            return $this->createVacancy($call, $request->safe()->except(['modality', 'opens_at', 'closes_at', 'is_demo']));
        });

        return redirect()->route('management.vacancies.edit', $vacancy)->with('success', 'Primera plaza y convocatoria guardadas en borrador. Puedes adjuntar los PDF de la plaza.');
    }

    public function create(RecruitmentCall $recruitmentCall): Response
    {
        return $this->form($recruitmentCall);
    }

    public function edit(Vacancy $vacancy): Response
    {
        return $this->form($vacancy->recruitmentCall, $vacancy);
    }

    public function store(StoreVacancyRequest $request, RecruitmentCall $recruitmentCall): RedirectResponse
    {
        $vacancy = DB::transaction(function () use ($request, $recruitmentCall): Vacancy {
            $call = $this->lockDraft($recruitmentCall->id);

            return $this->createVacancy($call, $request->validated());
        });

        return redirect()->route('management.vacancies.edit', $vacancy)->with('success', 'Ficha guardada. Puedes adjuntar las bases y otros documentos.');
    }

    public function update(StoreVacancyRequest $request, Vacancy $vacancy): RedirectResponse
    {
        DB::transaction(function () use ($request, $vacancy): void {
            $this->lockDraft($vacancy->recruitment_call_id);
            $locked = Vacancy::lockForUpdate()->findOrFail($vacancy->id);
            $data = $request->safe()->except(['requirements', 'screening_rules']);
            if ($locked->category !== $data['category'] && ($locked->external_evaluation !== null || $locked->internal_evaluation !== null)) {
                $data['external_evaluation'] = null;
                $data['internal_evaluation'] = null;
                AuditEvent::record('RUBRICAS_PLAZA_REINICIADAS', $locked, ['from' => $locked->category, 'to' => $data['category']]);
            }
            $locked->update($data);
            AuditEvent::record('FICHA_PLAZA_EDITADA', $locked);
        });

        return back()->with('success', 'Ficha de la plaza actualizada.');
    }

    public function preview(Vacancy $vacancy): Response
    {
        $vacancy->load('recruitmentCall');
        abort_unless($vacancy->recruitmentCall->status === 'BORRADOR', 409);

        return Inertia::render('Vacancy', ['vacancy' => $vacancy, 'preview' => true]);
    }

    public function aptitude(Request $request, Vacancy $vacancy): RedirectResponse
    {
        $data = $request->validate([
            ...ConfigureScreeningRequest::configurationRules(),
            ...StoreVacancyRequest::requirementRules(),
            'requirements' => ['present', 'array', 'list', 'max:30'],
            'requirements.*.key' => ['required', 'alpha_dash:ascii', 'distinct', 'max:40', 'not_regex:/^merito_/'],
        ]);
        foreach ($data['requirements'] as $index => $requirement) {
            if ($requirement['key'] === 'bonificacion_discapacidad' && $requirement['required']) {
                throw ValidationException::withMessages(["requirements.$index.required" => 'El sustento de bonificación es opcional.']);
            }
        }
        foreach ($data['screening_rules']['fields'] ?? [] as $index => $field) {
            if ($field['type'] === 'select') {
                if (! $field['options']) {
                    throw ValidationException::withMessages(["screening_rules.fields.$index.options" => 'Añade al menos una opción para la pregunta de selección.']);
                }
                if (($field['expected_value'] ?? '') !== '' && ! in_array($field['expected_value'], $field['options'], true)) {
                    throw ValidationException::withMessages(["screening_rules.fields.$index.expected_value" => 'La respuesta exigida debe pertenecer a las opciones disponibles.']);
                }
            }
        }
        DB::transaction(function () use ($vacancy, $data): void {
            $this->lockDraft($vacancy->recruitment_call_id);
            if (array_key_exists('screening_rules', $data) && ! ($data['screening_rules']['enabled'] ?? false) && $vacancy->screening_rules === null) {
                unset($data['screening_rules']);
            }
            $vacancy->update($data);
            AuditEvent::record('REQUISITOS_APTITUD_CONFIGURADOS', $vacancy);
        });

        return back()->with('success', 'Lista de sustentos y filtro de aptitud guardados.');
    }

    public function reschedule(Request $request, Vacancy $vacancy): RedirectResponse
    {
        $data = $request->validate([
            ...StoreVacancyRequest::scheduleRules(),
            'schedule' => ['required', 'array', 'list', 'max:20'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);
        DB::transaction(function () use ($vacancy, $data): void {
            $call = RecruitmentCall::lockForUpdate()->findOrFail($vacancy->recruitment_call_id);
            abort_unless(in_array($call->status, ['PUBLICADA', 'EN_EVALUACION'], true) && VacancyEvaluationWorkflow::configured($vacancy), 409);
            $vacancy->refresh();
            $previous = $vacancy->schedule;
            $previousWindows = VacancyEvaluationWorkflow::windows($vacancy);
            $vacancy->fill(['schedule' => $data['schedule']]);
            if (! VacancyEvaluationWorkflow::validCalendar($vacancy, $call->closes_at)) {
                throw ValidationException::withMessages(['schedule' => 'Indica las tres ventanas sucesivas de evaluación después del cierre de inscripción.']);
            }
            foreach (array_keys($vacancy->stage_publications ?? []) as $phase) {
                if (VacancyEvaluationWorkflow::windows($vacancy)[$phase] != $previousWindows[$phase]) {
                    throw ValidationException::withMessages(['schedule' => 'Las etapas con resultados publicados conservan su ventana.']);
                }
            }
            $vacancy->save();
            AuditEvent::record('CRONOGRAMA_PLAZA_REPROGRAMADO', $vacancy, ['reason' => $data['reason'], 'previous' => $previous, 'current' => $data['schedule']]);
        });

        return back()->with('success', 'Cronograma actualizado.');
    }

    public function publishStage(Request $request, Vacancy $vacancy): RedirectResponse
    {
        $phase = $request->validate(['phase' => ['required', Rule::in(['aptitude', 'external'])]])['phase'];
        DB::transaction(function () use ($vacancy, $phase): void {
            $call = RecruitmentCall::lockForUpdate()->findOrFail($vacancy->recruitment_call_id);
            abort_unless($call->status === 'EN_EVALUACION', 409);
            $vacancy->refresh();
            abort_unless(VacancyEvaluationWorkflow::configured($vacancy), 409);
            $publications = $vacancy->stage_publications ?? [];
            if (isset($publications[$phase])) {
                return;
            }
            if (! VacancyEvaluationWorkflow::phaseEnded($vacancy, $phase) || ($phase === 'external' && ! isset($publications['aptitude']))) {
                throw ValidationException::withMessages(['phase' => 'Espera el cierre de la ventana y la publicación de la etapa anterior.']);
            }
            $vacancy->loadCount('jurors');
            $applications = $vacancy->applications()->whereNotNull('submitted_at')->get();
            $applications->each(fn (Application $application) => $application->setRelation('vacancy', $vacancy));
            $pending = $applications->contains(fn (Application $application): bool => $application->status === 'RECIBIDA'
                || ($phase === 'external' && $application->status !== 'NO_APTO'
                    && ! VacancyEvaluationWorkflow::externalPassed($application)
                    && ($application->evaluation_outcome['stage'] ?? null) !== 'NO_SUPERA_EXTERNA'));
            if ($pending) {
                throw ValidationException::withMessages(['phase' => 'Hay expedientes pendientes de cerrar en esta etapa.']);
            }
            $rows = $applications
                ->filter(fn (Application $application): bool => $phase === 'aptitude' || $application->status !== 'NO_APTO')
                ->map(fn (Application $application): array => [
                    'reference' => $application->reference,
                    'result' => $phase === 'aptitude'
                        ? ($application->status === 'NO_APTO' ? 'NO_APTO' : 'APTO')
                        : (VacancyEvaluationWorkflow::externalPassed($application) ? 'SUPERA_EXTERNA' : 'NO_SUPERA_EXTERNA'),
                    'score' => $phase === 'external' ? $application->external_evaluation['total'] : null,
                ])->values()->all();
            $publications[$phase] = ['published_at' => now()->toIso8601String(), 'rows' => $rows];
            $vacancy->update(['stage_publications' => $publications]);
            AuditEvent::record('RESULTADOS_ETAPA_PUBLICADOS', $vacancy, ['phase' => $phase, 'count' => count($rows)]);
        });

        return back()->with('success', 'Resultados de etapa publicados.');
    }

    public function attach(Request $request, Vacancy $vacancy): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'file' => ['required', 'file', 'mimes:pdf', 'extensions:pdf', 'max:5120'],
        ], [
            'file.mimes' => 'Adjunta un archivo PDF.',
            'file.extensions' => 'El nombre del archivo debe terminar en .pdf.',
            'file.max' => 'El PDF debe pesar como máximo 5 MB.',
        ]);

        $path = null;
        try {
            DB::transaction(function () use ($data, $vacancy, &$path): void {
                $this->lockDraft($vacancy->recruitment_call_id);
                $locked = Vacancy::lockForUpdate()->findOrFail($vacancy->id);
                $documents = $locked->attached_documents ?? [];
                if (count($documents) >= 10) {
                    throw ValidationException::withMessages(['file' => 'La ficha admite hasta diez PDF.']);
                }
                $path = $data['file']->store('fichas-plazas/'.$locked->id, 'local');
                if (! $path) {
                    throw ValidationException::withMessages(['file' => 'No se pudo guardar el PDF. Intenta de nuevo.']);
                }
                $id = (string) Str::uuid();
                $documents[] = ['id' => $id, 'name' => $data['name'], 'path' => $path];
                $locked->update(['attached_documents' => $documents]);
                AuditEvent::record('PDF_PLAZA_ADJUNTADO', $locked, ['document' => $id]);
            });
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }

        return back()->with('success', 'PDF adjuntado a esta plaza.');
    }

    public function detach(Vacancy $vacancy, string $document): RedirectResponse
    {
        DB::transaction(function () use ($vacancy, $document): void {
            $this->lockDraft($vacancy->recruitment_call_id);
            $locked = Vacancy::lockForUpdate()->findOrFail($vacancy->id);
            $documents = collect($locked->attached_documents);
            $attachment = $documents->firstWhere('id', $document);
            abort_unless($attachment, 404);
            $locked->update(['attached_documents' => $documents->where('id', '!=', $document)->values()->all()]);
            AuditEvent::record('PDF_PLAZA_RETIRADO', $locked, ['document' => $document]);
            DB::afterCommit(fn () => Storage::disk('local')->delete($attachment['path']));
        });

        return back()->with('success', 'PDF retirado de la ficha.');
    }

    private function createVacancy(RecruitmentCall $call, array $data): Vacancy
    {
        $vacancy = $call->vacancies()->create(['requirements' => [], ...$data]);
        AuditEvent::record('PLAZA_CREADA', $vacancy);

        return $vacancy;
    }

    private function form(?RecruitmentCall $call = null, ?Vacancy $vacancy = null): Response
    {
        abort_if($call && $call->status !== 'BORRADOR', 409);

        return Inertia::render('VacancyForm', [
            'call' => $call?->only('id', 'code', 'title', 'modality', 'opens_at', 'closes_at'),
            'vacancy' => $vacancy,
            'hasEvaluation' => $vacancy?->external_evaluation !== null || $vacancy?->internal_evaluation !== null,
            'contractHours' => Vacancy::CONTRACT_HOURS,
            'faculties' => Vacancy::query()->distinct()->orderBy('faculty')->pluck('faculty'),
        ]);
    }

    private function lockDraft(int $id): RecruitmentCall
    {
        $call = RecruitmentCall::lockForUpdate()->findOrFail($id);
        abort_unless($call->status === 'BORRADOR', 409);

        return $call;
    }
}
