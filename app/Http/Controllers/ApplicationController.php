<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveApplicationFormRequest;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\AuditEvent;
use App\Models\RecruitmentCall;
use App\Models\Vacancy;
use App\Services\ApplicantScreening;
use App\Services\ApplicationWorkflow;
use App\Services\VacancyEvaluationWorkflow;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ApplicationController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Applications', ['applications' => $request->user()->applications()->with('vacancy', 'recruitmentCall')->withCount('documents')->latest('id')->get()->map(VacancyEvaluationWorkflow::applicantView(...))]);
    }

    public function store(Request $request, Vacancy $vacancy, ApplicationWorkflow $workflow): RedirectResponse
    {
        $application = DB::transaction(function () use ($request, $vacancy, $workflow) {
            $call = RecruitmentCall::lockForUpdate()->findOrFail($vacancy->recruitment_call_id);
            $workflow->assertOpen($call);
            $existing = $request->user()->applications()->where('recruitment_call_id', $call->id)->first();
            if ($existing) {
                if ($existing->vacancy_id !== $vacancy->id) {
                    throw ValidationException::withMessages(['application' => 'Ya tienes un expediente para otra plaza en esta convocatoria. Solo se permite una plaza.']);
                }

                return $existing;
            }
            $application = new Application(['reference' => (string) Str::uuid()]);
            $application->user()->associate($request->user());
            $application->vacancy()->associate($vacancy);
            $application->recruitmentCall()->associate($call);
            $application->save();
            AuditEvent::record('BORRADOR_CREADO', $application);

            return $application;
        });

        return redirect()->route('applications.show', $application);
    }

    public function show(Request $request, Application $application, ApplicantScreening $screening): Response
    {
        abort_unless($application->user_id === $request->user()->id, 404);

        $application->load('vacancy', 'recruitmentCall', 'documents');

        $report = $screening->assess($application);

        return Inertia::render('Application', [
            'application' => VacancyEvaluationWorkflow::applicantView($application),
            'screening' => $report,
            'documentRequirements' => $screening->documents($application),
            'meritCriteria' => $application->status === 'BORRADOR' && VacancyEvaluationWorkflow::configured($application->vacancy)
                ? VacancyEvaluationWorkflow::criteria($application->vacancy) : [],
            'evaluationStatus' => VacancyEvaluationWorkflow::configured($application->vacancy) ? [
                'calendar' => VacancyEvaluationWorkflow::windows($application->vacancy),
                'advanced' => $application->submitted_at && isset($application->vacancy->stage_publications['external']) && VacancyEvaluationWorkflow::externalPassed($application),
                'externalPublished' => isset($application->vacancy->stage_publications['external']),
                'result' => $application->recruitmentCall->results_published_at ? $application->evaluation_outcome : null,
            ] : null,
            'profileCredentials' => $application->status === 'BORRADOR'
                && $application->recruitmentCall->accepting_applications
                && ($report['rules']['academic_credentials'] ?? false)
                ? array_values(array_filter(
                    $request->user()->academic_credentials ?? [],
                    fn (array $credential): bool => isset(ApplicantScreening::CREDENTIAL_LEVELS[$credential['kind']]),
                ))
                : [],
        ]);
    }

    public function update(Request $request, Application $application, ApplicationWorkflow $workflow): RedirectResponse
    {
        abort_unless($application->user_id === $request->user()->id, 404);
        $data = $request->validate(['statement' => ['nullable', 'string', 'max:4000'], 'consent' => ['required', 'boolean']]);
        DB::transaction(function () use ($application, $workflow, $data) {
            $call = RecruitmentCall::lockForUpdate()->findOrFail($application->recruitment_call_id);
            $locked = Application::lockForUpdate()->findOrFail($application->id);
            $workflow->assertOpen($call);
            abort_unless($locked->status === 'BORRADOR', 409);
            $locked->update(['statement' => $data['statement'], 'consented_at' => $data['consent'] ? now() : null]);
        });

        return back()->with('success', 'Avance guardado. El expediente todavía no se ha enviado.');
    }

    public function saveForm(SaveApplicationFormRequest $request, Application $application, ApplicationWorkflow $workflow, ApplicantScreening $screening): RedirectResponse
    {
        DB::transaction(function () use ($request, $application, $workflow, $screening) {
            $call = RecruitmentCall::lockForUpdate()->findOrFail($application->recruitment_call_id);
            $locked = Application::lockForUpdate()->findOrFail($application->id);
            $workflow->assertOpen($call);
            abort_unless($locked->status === 'BORRADOR', 409);
            abort_unless(! VacancyEvaluationWorkflow::configured($locked->vacancy) && ($locked->vacancy->screening_rules['enabled'] ?? false), 409);
            $data = array_replace(['experience' => [], 'training' => [], 'answers' => []], $request->safe()->only(['degree', 'professional_title', 'bachelor_date', 'credentials', 'experience', 'training', 'answers']));
            $previousCredentials = array_column($locked->form_data['credentials'] ?? [], null, 'id');
            $updatedCredentials = array_column($data['credentials'] ?? [], null, 'id');
            $allowedKeys = array_column($locked->vacancy->screening_rules['fields'] ?? [], 'key');
            $data['answers'] = array_intersect_key($data['answers'], array_flip($allowedKeys));
            $details = $request->safe()->only(['statement', 'consent']);
            $updates = ['form_data' => $data];
            if (array_key_exists('statement', $details)) {
                $updates['statement'] = $details['statement'];
            }
            if (array_key_exists('consent', $details)) {
                $updates['consented_at'] = $details['consent'] ? now() : null;
            }
            $locked->update($updates);
            $report = $screening->assess($locked);
            $locked->update(['declared_score' => $call->evaluation_template ? null : $report['total']]);
            $allowedDocuments = array_column($screening->documents($locked), 'key');
            foreach ($previousCredentials as $id => $credential) {
                if ($credential != ($updatedCredentials[$id] ?? null)) {
                    $allowedDocuments = array_diff($allowedDocuments, ['acad_'.$id]);
                }
            }
            foreach ($locked->documents()->whereNotIn('requirement_key', $allowedDocuments)->get() as $document) {
                $path = $document->path;
                $document->delete();
                DB::afterCommit(fn () => Storage::disk('local')->delete($path));
            }
            AuditEvent::record('FORMULARIO_GUARDADO', $locked, ['eligible' => $report['eligible']]);
        });

        return back()->with('success', 'Formulario guardado y calculado. Revisa los requisitos antes de continuar.');
    }

    public function upload(Request $request, Application $application, ApplicationWorkflow $workflow, ApplicantScreening $screening): RedirectResponse
    {
        abort_unless($application->user_id === $request->user()->id, 404);
        $application->load('vacancy');
        $request->validate(['requirement_key' => ['required', Rule::in(array_column($screening->documents($application), 'key'))], 'file' => ['required', 'file', 'mimes:pdf', 'extensions:pdf', 'max:'.config('recruitment.max_file_kb')]], ['file.max' => 'El PDF debe pesar como máximo 5 MB.', 'file.mimes' => 'Solo se aceptan documentos PDF.']);
        DB::transaction(function () use ($request, $application, $workflow, $screening) {
            $call = RecruitmentCall::lockForUpdate()->findOrFail($application->recruitment_call_id);
            $locked = Application::lockForUpdate()->findOrFail($application->id);
            $workflow->assertOpen($call);
            abort_unless($locked->status === 'BORRADOR', 409);
            $screening->assertEligible($locked);
            $file = $request->file('file');
            $key = $request->string('requirement_key')->toString();
            if (! in_array($key, array_column($screening->documents($locked), 'key'), true)) {
                throw ValidationException::withMessages(['requirement_key' => 'El requisito cambió. Actualiza la página antes de adjuntar el documento.']);
            }
            $old = $locked->documents()->where('requirement_key', $key)->first();
            $path = $file->store('expedientes/'.$locked->id, 'local');
            if (! $path) {
                throw ValidationException::withMessages(['file' => 'No se pudo almacenar el archivo. Inténtalo de nuevo.']);
            }
            try {
                $locked->documents()->updateOrCreate(['requirement_key' => $key], ['name' => mb_substr(basename($file->getClientOriginalName()), 0, 200), 'path' => $path, 'mime' => 'application/pdf', 'size' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getRealPath())]);
                AuditEvent::record('DOCUMENTO_CARGADO', $locked, ['requirement' => $key]);
            } catch (\Throwable $e) {
                Storage::disk('local')->delete($path);
                throw $e;
            }
            if ($old) {
                DB::afterCommit(fn () => Storage::disk('local')->delete($old->path));
            }
        });

        return back()->with('success', 'Documento guardado correctamente.');
    }

    public function saveMerits(Request $request, Application $application, ApplicationWorkflow $workflow): RedirectResponse
    {
        abort_unless($application->user_id === $request->user()->id, 404);
        $data = $request->validate([
            'merits' => ['present', 'array', 'list', 'max:150'],
            'merits.*' => ['array:id,criterion,quantity,description,reuse'],
            'merits.*.id' => ['required', 'uuid', 'distinct'],
            'merits.*.criterion' => ['required', 'string', 'max:105'],
            'merits.*.quantity' => ['required', 'integer', 'between:1,1000'],
            'merits.*.description' => ['required', 'string', 'max:500'],
            'merits.*.reuse' => ['nullable', 'string', 'max:40'],
        ]);

        DB::transaction(function () use ($application, $workflow, $data): void {
            $call = RecruitmentCall::lockForUpdate()->findOrFail($application->recruitment_call_id);
            $locked = Application::lockForUpdate()->findOrFail($application->id);
            $workflow->assertOpen($call);
            abort_unless($locked->status === 'BORRADOR' && VacancyEvaluationWorkflow::configured($locked->vacancy), 409);
            $criteria = VacancyEvaluationWorkflow::criteria($locked->vacancy);
            $requirements = array_diff(array_column($locked->vacancy->requirements, 'key'), ['bonificacion_discapacidad', 'silabo_interno']);
            foreach ($data['merits'] as $index => $entry) {
                if (! isset($criteria[$entry['criterion']])) {
                    throw ValidationException::withMessages(["merits.$index.criterion" => 'Elige un criterio de esta plaza.']);
                }
                if (! empty($entry['reuse']) && ! in_array($entry['reuse'], $requirements, true)) {
                    throw ValidationException::withMessages(["merits.$index.reuse" => 'Elige un sustento de aptitud de esta plaza.']);
                }
            }
            $locked->update(['form_data' => [...($locked->form_data ?? []), 'merits' => $data['merits']]]);
            $keys = array_map(fn (array $entry): string => 'merito_'.$entry['id'], array_filter($data['merits'], fn (array $entry): bool => empty($entry['reuse'])));
            foreach ($locked->documents()->where('requirement_key', 'like', 'merito%')->whereNotIn('requirement_key', $keys)->get() as $document) {
                if (str_starts_with($document->requirement_key, 'merito_')) {
                    $document->delete();
                    DB::afterCommit(fn () => Storage::disk('local')->delete($document->path));
                }
            }
        });

        return back()->with('success', 'Méritos guardados. Verifica que cada uno tenga un sustento antes del envío.');
    }

    public function submit(Request $request, Application $application, ApplicationWorkflow $workflow): RedirectResponse
    {
        abort_unless($application->user_id === $request->user()->id, 404);
        $request->validate(['confirm' => ['accepted']]);
        $workflow->submit($application, $request->user());

        return back()->with('success', 'Postulación recibida. Tu cargo de recepción ya está disponible.');
    }

    public function document(Request $request, ApplicationDocument $document, ApplicationWorkflow $workflow): BinaryFileResponse
    {
        $application = $document->application;
        abort_unless($application->user_id === $request->user()->id || ($application->submitted_at && ($workflow->canReview($request->user(), $application) || $request->user()->isManager())), 404);
        if ($request->user()->role === 'jurado' && VacancyEvaluationWorkflow::configured($application->vacancy)) {
            abort_if(in_array($application->status, ['RECIBIDA', 'NO_APTO'], true) && str_starts_with($document->requirement_key, 'merito_'), 404);
            abort_if($document->requirement_key === 'silabo_interno' && ! VacancyEvaluationWorkflow::externalPassed($application), 404);
        }
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return response()->file(Storage::disk('local')->path($document->path), ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="documento.pdf"', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    public function receipt(Request $request, Application $application): View
    {
        abort_unless($application->user_id === $request->user()->id && $application->submitted_at, 404);

        return view('receipt', ['application' => $application->load('vacancy', 'recruitmentCall', 'documents')]);
    }
}
