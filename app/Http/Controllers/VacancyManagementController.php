<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVacancyRequest;
use App\Models\AuditEvent;
use App\Models\RecruitmentCall;
use App\Models\Vacancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
            if ($locked->category !== $data['category'] && $locked->external_evaluation !== null) {
                $data['external_evaluation'] = null;
                AuditEvent::record('ASPECTOS_EXTERNOS_REINICIADOS', $locked, ['from' => $locked->category, 'to' => $data['category']]);
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
            'hasExternalEvaluation' => $vacancy?->external_evaluation !== null,
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
