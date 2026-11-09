<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfigureScreeningRequest;
use App\Http\Requests\StoreCallRequest;
use App\Http\Requests\UpdateAcademicProfileRequest;
use App\Models\Application;
use App\Models\AuditEvent;
use App\Models\RecruitmentCall;
use App\Models\User;
use App\Models\Vacancy;
use App\Services\NombramientoRubric;
use App\Services\ProfileRegistryLookup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ManagementController extends Controller
{
    public function dashboard(Request $request): Response
    {
        $user = $request->user();
        if ($user->role === 'postulante') {
            return Inertia::render('Dashboard', ['applications' => $user->applications()->with('vacancy', 'recruitmentCall')->latest('id')->get()]);
        }
        $applications = Application::whereNotNull('submitted_at');
        $calls = RecruitmentCall::query();
        $vacancies = Vacancy::query();
        if ($user->role === 'jurado') {
            $applications->whereHas('vacancy.jurors', fn ($q) => $q->where('users.id', $user->id));
            $calls->whereHas('vacancies.jurors', fn ($q) => $q->where('users.id', $user->id));
            $vacancies->whereHas('jurors', fn ($q) => $q->where('users.id', $user->id));
        }

        return Inertia::render('Dashboard', [
            'stats' => [
                'calls' => (clone $calls)->count(),
                'vacancies' => $vacancies->count(),
                'applications' => (clone $applications)->count(),
                'pending' => (clone $applications)->where('status', 'RECIBIDA')->count(),
            ],
            'applications' => $applications->with('vacancy', 'recruitmentCall', 'user:id,name')->latest('submitted_at')->orderByDesc('id')->limit(6)->get(),
            'events' => $user->isManager() ? AuditEvent::latest('id')->limit(6)->get() : [],
            'calls' => $calls->withCount([
                'vacancies' => fn ($query) => $query->when($user->role === 'jurado', fn ($query) => $query->whereHas('jurors', fn ($query) => $query->where('users.id', $user->id))),
                'applications' => fn ($query) => $query->whereNotNull('submitted_at')->when($user->role === 'jurado', fn ($query) => $query->whereHas('vacancy.jurors', fn ($query) => $query->where('users.id', $user->id))),
            ])->latest('id')->limit(4)->get(),
        ]);
    }

    public function calls(): Response
    {
        return Inertia::render('ManageCalls', ['calls' => RecruitmentCall::withCount('vacancies', 'applications')->latest('id')->get()]);
    }

    public function store(StoreCallRequest $request): RedirectResponse
    {
        $call = DB::transaction(function () use ($request) {
            $call = RecruitmentCall::create($request->callData());
            AuditEvent::record('CONVOCATORIA_CREADA', $call);

            return $call;
        });

        return redirect()->route('management.vacancies.create', $call)->with('success', 'Convocatoria guardada como borrador. Completa la ficha de la primera plaza.');
    }

    public function show(Request $request, RecruitmentCall $recruitmentCall): Response
    {
        $recruitmentCall->load('vacancies.jurors:id,name');
        $vacancy = $recruitmentCall->status === 'BORRADOR'
            ? $recruitmentCall->vacancies->firstWhere('id', $request->integer('configurar'))
            : null;

        return Inertia::render('ManageCall', [
            'call' => $recruitmentCall,
            'jurors' => User::where('role', 'jurado')->get(['id', 'name', 'email']),
            'requirementsVacancyId' => $vacancy?->id,
            'configuredExternalVacancyIds' => $recruitmentCall->vacancies
                ->filter(fn (Vacancy $vacancy): bool => $vacancy->external_evaluation !== null)
                ->values()
                ->modelKeys(),
        ]);
    }

    public function edit(RecruitmentCall $recruitmentCall): Response
    {
        abort_unless($recruitmentCall->status === 'BORRADOR', 409);

        return Inertia::render('CallForm', [
            'call' => $recruitmentCall,
            'hasVacancies' => $recruitmentCall->vacancies()->exists(),
            'nombramientoTemplate' => $recruitmentCall->evaluation_template ?? NombramientoRubric::template(),
        ]);
    }

    public function update(StoreCallRequest $request, RecruitmentCall $recruitmentCall): RedirectResponse
    {
        DB::transaction(function () use ($request, $recruitmentCall) {
            $call = RecruitmentCall::lockForUpdate()->findOrFail($recruitmentCall->id);
            abort_unless($call->status === 'BORRADOR', 409);
            $data = $request->callData($call->evaluation_template);
            if ($call->modality !== $data['modality'] && $call->vacancies()->exists()) {
                throw ValidationException::withMessages(['modality' => 'La modalidad queda definida al crear la primera plaza.']);
            }
            $call->update([...$data, 'rules_approved' => false]);
            AuditEvent::record('CONVOCATORIA_EDITADA', $call);
        });

        return redirect()->route('management.calls.show', $recruitmentCall)->with('success', 'Configuración guardada.');
    }

    public function configureScreening(ConfigureScreeningRequest $request, Vacancy $vacancy): RedirectResponse
    {
        DB::transaction(function () use ($request, $vacancy) {
            $call = RecruitmentCall::lockForUpdate()->findOrFail($vacancy->recruitment_call_id);
            abort_unless($call->status === 'BORRADOR', 409);
            $vacancy->update($request->validated());
            AuditEvent::record('FORMULARIO_CONFIGURADO', $vacancy);
        });

        return back()->with('success', 'Reglas del formulario guardadas. Se congelarán al publicar.');
    }

    public function assign(Request $request, Vacancy $vacancy): RedirectResponse
    {
        $data = $request->validate(['jurors' => ['required', 'array', 'min:1', 'max:5'], 'jurors.*' => ['required', 'integer', 'distinct', Rule::exists('users', 'id')->where('role', 'jurado')]]);
        DB::transaction(function () use ($vacancy, $data) {
            $call = RecruitmentCall::lockForUpdate()->findOrFail($vacancy->recruitment_call_id);
            abort_unless(in_array($call->status, ['BORRADOR', 'PUBLICADA'], true), 409);
            abort_if($call->applications()->whereNotNull('submitted_at')->exists(), 409, 'La comisión no se puede cambiar después de recibir expedientes.');
            if ($call->evaluation_template && $call->status === 'PUBLICADA' && count($data['jurors']) !== 3) {
                throw ValidationException::withMessages(['jurors' => 'Esta convocatoria requiere tres jurados titulares por plaza.']);
            }
            $vacancy->jurors()->sync($data['jurors']);
            AuditEvent::record('JURADO_ASIGNADO', $vacancy, ['jurors' => $data['jurors']]);
        });

        return back()->with('success', 'Comisión asignada.');
    }

    public function closeDemo(RecruitmentCall $recruitmentCall): RedirectResponse
    {
        abort_unless(app()->environment('local') && config('recruitment.demo') && $recruitmentCall->is_demo, 404);
        DB::transaction(function () use ($recruitmentCall) {
            $call = RecruitmentCall::lockForUpdate()->findOrFail($recruitmentCall->id);
            abort_unless($call->status === 'PUBLICADA' && $call->opens_at->isPast(), 409);
            $call->update(['closes_at' => now()->subSecond(), 'status' => 'EN_EVALUACION']);
            AuditEvent::record('INSCRIPCION_DEMO_CERRADA', $call);
        });

        return back()->with('success', 'Inscripción de prueba cerrada. Ya puedes revisar los expedientes enviados.');
    }

    public function transition(Request $request, RecruitmentCall $recruitmentCall): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['PUBLICADA', 'EN_EVALUACION', 'FINALIZADA', 'ARCHIVADA'])]]);
        DB::transaction(function () use ($recruitmentCall, $data) {
            $call = RecruitmentCall::lockForUpdate()->findOrFail($recruitmentCall->id);
            $next = ['BORRADOR' => 'PUBLICADA', 'PUBLICADA' => 'EN_EVALUACION', 'EN_EVALUACION' => 'FINALIZADA', 'FINALIZADA' => 'ARCHIVADA'];
            if (($next[$call->status] ?? null) !== $data['status']) {
                throw ValidationException::withMessages(['status' => 'La transición de etapa no es válida.']);
            }
            if ($data['status'] === 'PUBLICADA') {
                if ($call->vacancies()->whereNotNull('external_evaluation')->exists()) {
                    throw ValidationException::withMessages(['status' => 'Los aspectos externos por plaza están en configuración. La publicación requiere completar su flujo de evaluación.']);
                }
                if (! $call->is_demo) {
                    throw ValidationException::withMessages(['status' => 'La publicación de procesos oficiales está pendiente de implementar y validar la normativa específica de nombramiento. Usa una convocatoria de demostración.']);
                }
                if (! $call->vacancies()->exists() || $call->vacancies()->doesntHave('jurors')->exists()) {
                    throw ValidationException::withMessages(['status' => 'Añade al menos una plaza y asigna jurados a todas las plazas.']);
                }
                $missingRequirements = $call->vacancies()->whereNotNull('professional_profile')
                    ->whereJsonLength('requirements', 0)
                    ->get(['screening_rules'])
                    ->contains(fn (Vacancy $vacancy): bool => ! ($vacancy->screening_rules['enabled'] ?? false));
                if ($missingRequirements) {
                    throw ValidationException::withMessages(['status' => 'Configura los requisitos de postulación de cada ficha nueva antes de publicar.']);
                }
                if ($call->evaluation_template && $call->vacancies()->has('jurors', '!=', 3)->exists()) {
                    throw ValidationException::withMessages(['status' => 'Asigna tres jurados titulares a cada plaza para probar la plantilla de nombramiento.']);
                }
                if (! $call->rubric) {
                    throw ValidationException::withMessages(['status' => 'Configura la evaluación de la convocatoria antes de publicar.']);
                }
                if ($call->closes_at->isPast()) {
                    throw ValidationException::withMessages(['status' => 'La fecha de cierre ya pasó. Edita el cronograma antes de publicar.']);
                }
            }
            if ($data['status'] === 'EN_EVALUACION' && now()->startOfSecond()->lte($call->closes_at)) {
                throw ValidationException::withMessages(['status' => 'La inscripción debe haber cerrado antes de comenzar la evaluación.']);
            }
            if ($data['status'] === 'FINALIZADA') {
                if ($call->applications()->whereNotNull('submitted_at')->whereNotIn('status', ['EVALUADA', 'NO_APTO'])->exists()) {
                    throw ValidationException::withMessages(['status' => 'Hay expedientes pendientes de revisión o de calificaciones del jurado.']);
                }
                $call->results_published_at = now();
            }
            $before = $call->status;
            $call->status = $data['status'];
            $call->save();
            AuditEvent::record('ETAPA_CAMBIADA', $call, ['from' => $before, 'to' => $call->status]);
        });

        return back()->with('success', 'Etapa actualizada correctamente.');
    }

    public function applications(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'call' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(['RECIBIDA', 'APTO', 'NO_APTO', 'EVALUADA'])],
            'sort' => ['nullable', Rule::in(['score', 'recent'])],
        ]);
        $user = $request->user();
        $query = Application::whereNotNull('submitted_at')->with('user:id,name,email', 'vacancy', 'recruitmentCall');
        $calls = RecruitmentCall::where('status', '!=', 'BORRADOR');
        if ($user->role === 'jurado') {
            $query->whereHas('vacancy.jurors', fn ($q) => $q->where('users.id', $user->id));
            $calls->whereHas('vacancies.jurors', fn ($q) => $q->where('users.id', $user->id));
        }
        if (! empty($filters['call'])) {
            $query->where('recruitment_call_id', $filters['call']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['q'])) {
            $term = $filters['q'];
            $query->where(fn ($q) => $q->where('reference', 'like', "%{$term}%")->orWhereHas('user', fn ($q) => $q->where('name', 'like', "%{$term}%")));
        }

        if (($filters['sort'] ?? 'score') === 'score') {
            $query->orderByDesc('declared_score');
        }

        return Inertia::render('ReviewList', [
            'applications' => $query->latest('submitted_at')->orderByDesc('id')->paginate(15)->withQueryString(),
            'calls' => $calls->latest('id')->get(['id', 'code', 'title']),
            'filters' => $filters,
        ]);
    }

    public function users(): Response
    {
        return Inertia::render('Users', ['users' => User::orderBy('name')->paginate(20)]);
    }

    public function createUser(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'email' => ['required', 'email', 'max:150', 'unique:users'], 'role' => ['required', Rule::in(['administrador', 'gestor', 'jurado'])], 'password' => ['required', Password::min(12)->letters()->numbers()->symbols()]]);
        DB::transaction(function () use ($data) {
            $user = User::create(collect($data)->except('role')->all());
            $user->forceFill(['role' => $data['role']])->save();
            AuditEvent::record('USUARIO_CREADO', $user, ['role' => $user->role]);
        });

        return back()->with('success', 'Usuario creado. Entrega su contraseña inicial por un canal seguro.');
    }

    public function audit(): Response
    {
        return Inertia::render('Audit', ['events' => AuditEvent::latest()->paginate(30)]);
    }

    public function profile(Request $request, ProfileRegistryLookup $registry): Response
    {
        return Inertia::render('Profile', [
            'registrySources' => $registry->sources($request),
            'academicCredentials' => $request->user()->role === 'postulante'
                ? ($request->user()->academic_credentials ?? [])
                : null,
            'credentialDateLimit' => now()->toDateString(),
        ]);
    }

    public function updateAcademicProfile(UpdateAcademicProfileRequest $request): RedirectResponse
    {
        $request->user()->update(['academic_credentials' => $request->validated('credentials')]);

        return back()->with('success', 'Formación guardada en tu perfil. Puedes copiarla a una postulación en borrador.');
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'phone' => ['required', 'regex:/^[0-9+ ()-]{7,20}$/'], 'document_number' => ['required', 'digits:8', Rule::unique('users')->ignore($request->user()->id)]]);
        $request->user()->update($data);

        return back()->with('success', 'Perfil actualizado. Los datos de los expedientes enviados se conservan en su cargo.');
    }
}
