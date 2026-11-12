<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\RecruitmentCall;
use App\Models\User;
use App\Models\Vacancy;
use App\Services\ExternalEvaluationRubric;
use App\Services\InternalEvaluationRubric;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VacancyApplicationWorkflowTest extends TestCase
{
    public function test_demo_vacancy_goes_from_submission_to_published_results_using_its_rubrics(): void
    {
        Storage::fake('local');
        Notification::fake();
        $this->travelTo(now()->setDate(2026, 11, 10)->setTime(8, 0));
        $manager = User::factory()->manager()->create();
        $applicant = User::factory()->create();
        $jurors = User::factory()->juror()->count(3)->create();
        $call = RecruitmentCall::factory()->draft()->create([
            'modality' => 'CONTRATO',
            'rubric' => [],
            'closes_at' => now()->addHour(),
        ]);
        $schedule = [];
        foreach (['aptitude', 'external', 'internal'] as $index => $stage) {
            $schedule[] = [
                'stage' => $stage,
                'label' => $stage,
                'starts_at' => now()->addHours(2 + $index * 2)->format('Y-m-d\TH:i'),
                'ends_at' => now()->addHours(3 + $index * 2)->format('Y-m-d\TH:i'),
            ];
        }
        $vacancy = Vacancy::factory()->for($call)->create([
            'category' => 'DC A2',
            'schedule' => $schedule,
            'requirements' => [
                ['key' => 'titulo', 'label' => 'Título profesional', 'required' => true],
                ['key' => 'bonificacion_discapacidad', 'label' => 'Sustento de bonificación', 'required' => false],
            ],
            'external_evaluation' => ExternalEvaluationRubric::template('CONTRATO', 'DC A2'),
            'internal_evaluation' => InternalEvaluationRubric::template('CONTRATO', 'DC A2'),
        ]);
        $vacancy->jurors()->attach($jurors->modelKeys());
        $transition = route('management.calls.transition', $call);
        $this->actingAs($manager)->post($transition, ['status' => 'PUBLICADA'])->assertSessionHas('success');

        $this->actingAs($applicant)->post(route('applications.store', $vacancy))->assertRedirect();
        $application = Application::sole();
        $this->put(route('applications.update', $application), ['statement' => 'Declaración de prueba', 'consent' => true])->assertSessionHas('success');
        $merits = [
            ['id' => (string) Str::uuid(), 'criterion' => 'formacion:titulo', 'description' => 'Título', 'quantity' => 1, 'reuse' => 'titulo'],
            ['id' => (string) Str::uuid(), 'criterion' => 'formacion:doctor', 'description' => 'Doctorado', 'quantity' => 1],
            ['id' => (string) Str::uuid(), 'criterion' => 'formacion:maestro', 'description' => 'Maestría', 'quantity' => 1],
            ['id' => (string) Str::uuid(), 'criterion' => 'publicaciones:articulo_indizado', 'description' => 'Artículos', 'quantity' => 4],
        ];
        $this->put(route('applications.merits', $application), ['merits' => $merits])->assertSessionHas('success');
        $submit = route('applications.submit', $application);
        $this->post($submit, ['confirm' => true])->assertSessionHasErrors('application');

        foreach (['titulo', 'bonificacion_discapacidad', ...array_map(fn (array $entry): string => 'merito_'.$entry['id'], array_slice($merits, 1))] as $key) {
            $this->post(route('applications.upload', $application), [
                'requirement_key' => $key,
                'file' => new UploadedFile(database_path('fixtures/sustento-demo.pdf'), 'sustento.pdf', 'application/pdf', null, true),
            ])->assertSessionHas('success');
        }
        $this->post($submit, ['confirm' => true])->assertSessionHas('success');
        $this->assertSame('18.000', $application->fresh()->declared_score);
        $this->put(route('applications.merits', $application), ['merits' => []])->assertConflict();

        $this->travel(90)->minutes();
        $this->actingAs($manager)->post($transition, ['status' => 'EN_EVALUACION'])->assertSessionHas('success');
        $this->actingAs($jurors[0])->get(route('review.show', $application))
            ->assertInertia(fn (Assert $page) => $page->has('application.documents', 2));
        $aptitude = [
            'notes' => 'Sustentos de aptitud conformes.',
            'documents' => $application->documents()->whereIn('requirement_key', ['titulo', 'bonificacion_discapacidad'])->pluck('id')->mapWithKeys(fn ($id) => [$id => 'CUMPLE'])->all(),
        ];
        $this->post(route('review.eligibility', $application), $aptitude)->assertConflict();
        $this->travel(45)->minutes();
        $this->post(route('review.eligibility', $application), $aptitude)->assertSessionHas('success');
        $this->assertSame('APTO', $application->fresh()->status);
        $this->actingAs($applicant)->get(route('applications.show', $application))->assertInertia(fn (Assert $page) => $page
            ->where('application.status', 'RECIBIDA')->missing('application.review_notes'));
        $this->actingAs($jurors[0]);

        $evaluation = route('review.evaluate', $application);
        $proposal = [
            'stage' => 'externa', 'action' => 'proponer', 'revision' => 0, 'notes' => 'Méritos contrastados con sus documentos.',
            'merits' => array_column($merits, 'quantity', 'id'),
        ];
        $this->post($evaluation, $proposal)->assertSessionHasErrors('stage');
        $this->travel(2)->hours();
        $publication = route('management.vacancies.publish-stage', $vacancy);
        $this->post($evaluation, $proposal)->assertSessionHasErrors('stage');
        $this->actingAs($manager)->post($publication, ['phase' => 'aptitude'])->assertSessionHas('success');
        $this->actingAs($jurors[0]);
        $this->post($evaluation, [...$proposal, 'merits' => []])->assertSessionHasErrors('merits');
        $this->post($evaluation, $proposal)->assertSessionHas('success');
        foreach ($jurors->skip(1) as $juror) {
            $this->actingAs($juror)->post($evaluation, [
                'stage' => 'externa', 'action' => 'confirmar', 'revision' => 1, 'notes' => 'Conforme con los méritos acreditados.',
            ])->assertSessionHas('success');
        }
        $this->assertSame(18, $application->fresh()->external_evaluation['total']);

        $internal = [
            'stage' => 'interna', 'notes' => 'Evaluación individual realizada.',
            'item_scores' => ['silabo' => array_fill(0, 5, 1.5), 'clase' => array_fill(0, 10, 2.5), 'entrevista' => array_fill(0, 5, 2.5)],
        ];
        $this->post($evaluation, $internal)->assertSessionHasErrors('stage');
        $this->travel(2)->hours();
        $this->actingAs($manager)->post($publication, ['phase' => 'external'])->assertSessionHas('success');
        $this->get(route('vacancies.show', $vacancy))->assertInertia(fn (Assert $page) => $page
            ->where('stagePublications.external.rows.0.result', 'SUPERA_EXTERNA')
            ->where('stagePublications.external.rows.0.score', 18));
        foreach ($jurors as $juror) {
            $this->actingAs($juror)->post($evaluation, $internal)->assertSessionHas('success');
        }
        $this->assertSame(72.45, $application->fresh()->evaluation_outcome['final']);
        $this->actingAs($applicant)->get(route('applications.show', $application))->assertInertia(fn (Assert $page) => $page->where('evaluationStatus.result', null));
        $this->actingAs($manager)->get(route('management.calls.show', $call))->assertInertia(fn (Assert $page) => $page
            ->where("reviewed.{$vacancy->id}.0.score", 72.45));
        $this->travel(1)->hours();
        $this->actingAs($manager)->post($transition, ['status' => 'FINALIZADA'])->assertSessionHas('success');
        $this->get(route('results'))->assertInertia(fn (Assert $page) => $page
            ->where('calls.0.vacancies.0.rows.0.score', 72.45)
            ->missing('calls.0.vacancies.0.rows.0.outcome.bonus'));
    }

    public function test_stage_one_enforces_minimum_profile_filter_awards_doctor_points_when_magister_required_and_validates_folder_a(): void
    {
        Storage::fake('local');
        Notification::fake();
        $this->travelTo(now()->setDate(2026, 11, 10)->setTime(8, 0));

        $manager = User::factory()->manager()->create();
        $ineligibleApplicant = User::factory()->create();
        $doctorApplicant = User::factory()->create();
        $jurors = User::factory()->juror()->count(3)->create();

        $call = RecruitmentCall::factory()->draft()->create([
            'modality' => 'NOMBRAMIENTO',
            'rubric' => [],
            'closes_at' => now()->addHour(),
        ]);

        $schedule = [];
        foreach (['aptitude', 'external', 'internal'] as $index => $stage) {
            $schedule[] = [
                'stage' => $stage,
                'label' => $stage,
                'starts_at' => now()->addHours(2 + $index * 2)->format('Y-m-d\TH:i'),
                'ends_at' => now()->addHours(3 + $index * 2)->format('Y-m-d\TH:i'),
            ];
        }

        $vacancy = Vacancy::factory()->for($call)->create([
            'category' => 'AUXILIAR',
            'schedule' => $schedule,
            'requirements' => [],
            'external_evaluation' => ExternalEvaluationRubric::template('NOMBRAMIENTO', 'AUXILIAR'),
            'internal_evaluation' => InternalEvaluationRubric::template('NOMBRAMIENTO', 'AUXILIAR'),
        ]);
        $vacancy->jurors()->attach($jurors->modelKeys());

        $this->actingAs($manager)->put(route('management.vacancies.aptitude', $vacancy), [
            'requirements' => [
                ['key' => 'solicitud', 'label' => 'Solicitud de inscripción y declaraciones juradas (Fólder A)', 'required' => true],
                ['key' => 'habilitacion', 'label' => 'Constancia de colegiatura y habilitación vigente', 'required' => true],
            ],
            'screening_rules' => [
                'enabled' => true,
                'academic_credentials' => true,
                'allow_residency' => false,
                'minimum_degree' => 'maestro',
                'minimum_experience_months' => 60,
                'experience_type' => 'toda',
                'count_from_bachelor' => true,
                'minimum_training_hours' => 0,
                'degree_points' => [
                    'bachiller' => 1,
                    'titulo' => 2,
                    'maestro' => 6,
                    'segunda_especialidad' => 6,
                    'doctor' => 8,
                ],
                'experience_points_per_year' => 1,
                'experience_cap' => 10,
                'training_hours_per_point' => 40,
                'training_cap' => 5,
                'fields' => [
                    [
                        'key' => 'colegiatura',
                        'label' => 'Colegiatura profesional vigente y habilitada',
                        'type' => 'select',
                        'required' => true,
                        'options' => ['Sí, habilitado', 'No habilitado'],
                        'expected_value' => 'Sí, habilitado',
                        'requires_document' => true,
                    ],
                ],
            ],
        ])->assertSessionHas('success');

        $this->actingAs($manager)->post(route('management.calls.transition', $call), ['status' => 'PUBLICADA'])->assertSessionHas('success');

        // Postulante 1: no cumple experiencia mínima desde el bachiller -> descartado automáticamente por el sistema
        $this->actingAs($ineligibleApplicant)->post(route('applications.store', $vacancy))->assertRedirect();
        $ineligibleApp = Application::where('user_id', $ineligibleApplicant->id)->sole();
        $this->put(route('applications.form', $ineligibleApp), [
            'consent' => true,
            'credentials' => [
                ['id' => (string) Str::uuid(), 'kind' => 'bachiller', 'name' => 'Bachiller', 'institution' => 'UNHEVAL', 'awarded_at' => '2024-01-01'],
                ['id' => (string) Str::uuid(), 'kind' => 'titulo', 'name' => 'Licenciado', 'institution' => 'UNHEVAL', 'awarded_at' => '2024-06-01'],
                ['id' => (string) Str::uuid(), 'kind' => 'maestro', 'name' => 'Maestro', 'institution' => 'UNHEVAL', 'awarded_at' => '2025-01-01'],
            ],
            'experience' => [
                ['id' => (string) Str::uuid(), 'institution' => 'UNHEVAL', 'position' => 'Docente', 'type' => 'docente', 'start_date' => '2024-06-01', 'end_date' => '2026-06-01'],
            ],
            'training' => [],
            'answers' => ['colegiatura' => 'Sí, habilitado'],
        ])->assertSessionHas('success');

        $this->post(route('applications.upload', $ineligibleApp), [
            'requirement_key' => 'solicitud',
            'file' => new UploadedFile(database_path('fixtures/sustento-demo.pdf'), 'sustento.pdf', 'application/pdf', null, true),
        ])->assertSessionHasErrors('application');

        // Postulante 2: plaza pide Magíster y acredita Doctor -> pasa el filtro de Etapa 1 y suma los 8 pts de Doctor (+ 2 pts de Título = 10 pts)
        $this->actingAs($doctorApplicant)->post(route('applications.store', $vacancy))->assertRedirect();
        $doctorApp = Application::where('user_id', $doctorApplicant->id)->sole();
        $bachelorId = (string) Str::uuid();
        $titleId = (string) Str::uuid();
        $doctorId = (string) Str::uuid();
        $expId = (string) Str::uuid();

        $this->put(route('applications.form', $doctorApp), [
            'statement' => 'Cumplo con el perfil y adjunto Fólder A.',
            'consent' => true,
            'credentials' => [
                ['id' => $bachelorId, 'kind' => 'bachiller', 'name' => 'Bachiller en Ciencias', 'institution' => 'UNHEVAL', 'awarded_at' => '2016-01-01'],
                ['id' => $titleId, 'kind' => 'titulo', 'name' => 'Ingeniero de Sistemas', 'institution' => 'UNHEVAL', 'awarded_at' => '2017-01-01'],
                ['id' => $doctorId, 'kind' => 'doctor', 'name' => 'Doctor en Ingeniería', 'institution' => 'UNHEVAL', 'awarded_at' => '2021-01-01'],
            ],
            'experience' => [
                ['id' => $expId, 'institution' => 'UNHEVAL', 'position' => 'Docente universitario', 'type' => 'docente', 'start_date' => '2018-01-01', 'end_date' => '2025-06-01'],
            ],
            'training' => [],
            'answers' => ['colegiatura' => 'Sí, habilitado'],
        ])->assertSessionHas('success');

        $this->get(route('applications.show', $doctorApp))->assertInertia(fn (Assert $page) => $page
            ->where('screening.eligible', true)
            ->where('screening.academic_points', 10)
            ->where('screening.checks.1.label', 'Grado académico mínimo: Maestro / Magíster (acredita Doctor · 8 pts)')
            ->where('evaluationStatus.externalMinimum', 16)
            ->has('application.form_data.merits', 3));
        $this->assertSame('10.000', $doctorApp->fresh()->declared_score);

        $merits = [
            ['id' => (string) Str::uuid(), 'criterion' => 'formacion:titulo', 'description' => 'Título profesional', 'quantity' => 1, 'reuse' => 'acad_'.$titleId],
            ['id' => (string) Str::uuid(), 'criterion' => 'formacion:doctor', 'description' => 'Grado de Doctor', 'quantity' => 1, 'reuse' => 'acad_'.$doctorId],
        ];
        $this->put(route('applications.merits', $doctorApp), ['merits' => $merits])->assertSessionHas('success');

        foreach (['solicitud', 'habilitacion', 'acad_'.$bachelorId, 'acad_'.$titleId, 'acad_'.$doctorId, 'exp_'.$expId, 'campo_colegiatura'] as $key) {
            $this->post(route('applications.upload', $doctorApp), [
                'requirement_key' => $key,
                'file' => new UploadedFile(database_path('fixtures/sustento-demo.pdf'), 'sustento.pdf', 'application/pdf', null, true),
            ])->assertSessionHas('success');
        }

        $this->post(route('applications.submit', $doctorApp), ['confirm' => true])->assertSessionHas('success');
        $this->assertSame('10.000', $doctorApp->fresh()->declared_score);

        // Etapa 1: Jurado valida credenciales base y Fólder A
        $this->travel(150)->minutes();
        $this->actingAs($manager)->post(route('management.calls.transition', $call), ['status' => 'EN_EVALUACION'])->assertSessionHas('success');

        $this->actingAs($jurors[0])->post(route('review.eligibility', $doctorApp), [
            'notes' => 'Credenciales base y Fólder A conformes.',
            'academic_profile_confirmed' => true,
            'documents' => $doctorApp->documents()->pluck('id')->mapWithKeys(fn ($id) => [$id => 'CUMPLE'])->all(),
        ])->assertSessionHas('success');

        $this->assertSame('APTO', $doctorApp->fresh()->status);

        // Etapa 2: Evaluación de Aspectos Externos (Anexo 01) -> con 10 pts (< 16 pts de Auxiliar) queda eliminado automáticamente
        $this->travel(2)->hours();
        $this->actingAs($manager)->post(route('management.vacancies.publish-stage', $vacancy), ['phase' => 'aptitude'])->assertSessionHas('success');

        $this->actingAs($jurors[0])->post(route('review.evaluate', $doctorApp), [
            'stage' => 'externa',
            'action' => 'proponer',
            'revision' => 0,
            'notes' => 'Se acreditan título y grado de Doctor (10 pts), no alcanza el mínimo externo de 16 pts.',
            'merits' => array_column($merits, 'quantity', 'id'),
        ])->assertSessionHas('success');

        foreach ($jurors->skip(1) as $juror) {
            $this->actingAs($juror)->post(route('review.evaluate', $doctorApp), [
                'stage' => 'externa',
                'action' => 'confirmar',
                'revision' => 1,
                'notes' => 'Conforme con la hoja de aspectos externos.',
            ])->assertSessionHas('success');
        }

        $doctorApp->refresh();
        $this->assertSame('EVALUADA', $doctorApp->status);
        $this->assertSame('NO_SUPERA_EXTERNA', $doctorApp->evaluation_outcome['stage']);
        $this->assertFalse($doctorApp->evaluation_outcome['passed']);
        $this->assertSame(10, $doctorApp->evaluation_outcome['external']);
    }

    public function test_stage_three_evaluates_internal_rubrics_consolidates_jury_average_and_resolves_tie_by_doctor_degree(): void
    {
        Storage::fake('local');
        Notification::fake();
        $this->travelTo(now()->setDate(2026, 11, 10)->setTime(8, 0));

        $manager = User::factory()->manager()->create();
        $magisterApplicant = User::factory()->create();
        $doctorApplicant = User::factory()->create();
        $jurors = User::factory()->juror()->count(3)->create();

        $call = RecruitmentCall::factory()->draft()->create([
            'modality' => 'NOMBRAMIENTO',
            'rubric' => [],
            'closes_at' => now()->addHour(),
        ]);

        $schedule = [];
        foreach (['aptitude', 'external', 'internal'] as $index => $stage) {
            $schedule[] = [
                'stage' => $stage,
                'label' => $stage,
                'starts_at' => now()->addHours(2 + $index * 2)->format('Y-m-d\TH:i'),
                'ends_at' => now()->addHours(3 + $index * 2)->format('Y-m-d\TH:i'),
            ];
        }

        $vacancy = Vacancy::factory()->for($call)->create([
            'category' => 'AUXILIAR',
            'schedule' => $schedule,
            'requirements' => [
                ['key' => 'solicitud', 'label' => 'Solicitud Fólder A', 'required' => true],
                ['key' => 'silabo_interno', 'label' => 'Sílabo para evaluación interna', 'required' => false],
            ],
            'external_evaluation' => ExternalEvaluationRubric::template('NOMBRAMIENTO', 'AUXILIAR'),
            'internal_evaluation' => InternalEvaluationRubric::template('NOMBRAMIENTO', 'AUXILIAR'),
        ]);
        $vacancy->jurors()->attach($jurors->modelKeys());

        $this->actingAs($manager)->post(route('management.calls.transition', $call), ['status' => 'PUBLICADA'])->assertSessionHas('success');

        // Postulante A (Magíster: 6 + 2 + 2 (segunda especialidad) + 8 (publicaciones) = 18 pts externos)
        $this->actingAs($magisterApplicant)->post(route('applications.store', $vacancy))->assertRedirect();
        $magisterApp = Application::where('user_id', $magisterApplicant->id)->sole();
        $this->put(route('applications.update', $magisterApp), ['statement' => 'Postulante con Maestría', 'consent' => true]);
        $magisterMerits = [
            ['id' => (string) Str::uuid(), 'criterion' => 'formacion:titulo', 'description' => 'Título', 'quantity' => 1, 'reuse' => 'solicitud'],
            ['id' => (string) Str::uuid(), 'criterion' => 'formacion:maestro', 'description' => 'Maestría', 'quantity' => 1, 'reuse' => 'solicitud'],
            ['id' => (string) Str::uuid(), 'criterion' => 'formacion_servicio:especialidad', 'description' => 'Segunda especialidad', 'quantity' => 1, 'reuse' => 'solicitud'],
            ['id' => (string) Str::uuid(), 'criterion' => 'publicaciones:articulo_indizado', 'description' => '4 artículos indizados', 'quantity' => 4, 'reuse' => 'solicitud'],
        ];
        $this->put(route('applications.merits', $magisterApp), ['merits' => $magisterMerits])->assertSessionHas('success');
        foreach (['solicitud', 'silabo_interno'] as $key) {
            $this->post(route('applications.upload', $magisterApp), [
                'requirement_key' => $key,
                'file' => new UploadedFile(database_path('fixtures/sustento-demo.pdf'), 'sustento.pdf', 'application/pdf', null, true),
            ])->assertSessionHas('success');
        }
        $this->post(route('applications.submit', $magisterApp), ['confirm' => true])->assertSessionHas('success');

        // Postulante B (Doctor: 8 + 2 + 7.5 + 0.5 = 18 pts externos)
        $this->actingAs($doctorApplicant)->post(route('applications.store', $vacancy))->assertRedirect();
        $doctorApp = Application::where('user_id', $doctorApplicant->id)->sole();
        $this->put(route('applications.update', $doctorApp), ['statement' => 'Postulante con Doctorado', 'consent' => true]);
        $doctorMerits = [
            ['id' => (string) Str::uuid(), 'criterion' => 'formacion:titulo', 'description' => 'Título', 'quantity' => 1, 'reuse' => 'solicitud'],
            ['id' => (string) Str::uuid(), 'criterion' => 'formacion:doctor', 'description' => 'Doctorado', 'quantity' => 1, 'reuse' => 'solicitud'],
            ['id' => (string) Str::uuid(), 'criterion' => 'publicaciones:articulo_indizado', 'description' => '3 artículos indizados', 'quantity' => 3, 'reuse' => 'solicitud'],
            ['id' => (string) Str::uuid(), 'criterion' => 'publicaciones:articulo_issn', 'description' => '1 artículo con ISSN', 'quantity' => 1, 'reuse' => 'solicitud'],
        ];
        $this->put(route('applications.merits', $doctorApp), ['merits' => $doctorMerits])->assertSessionHas('success');
        foreach (['solicitud', 'silabo_interno'] as $key) {
            $this->post(route('applications.upload', $doctorApp), [
                'requirement_key' => $key,
                'file' => new UploadedFile(database_path('fixtures/sustento-demo.pdf'), 'sustento.pdf', 'application/pdf', null, true),
            ])->assertSessionHas('success');
        }
        $this->post(route('applications.submit', $doctorApp), ['confirm' => true])->assertSessionHas('success');

        // Etapa 1: Aptitud
        $this->travel(150)->minutes();
        $this->actingAs($manager)->post(route('management.calls.transition', $call), ['status' => 'EN_EVALUACION'])->assertSessionHas('success');
        foreach ([$magisterApp, $doctorApp] as $app) {
            $this->actingAs($jurors[0])->post(route('review.eligibility', $app), [
                'notes' => 'Cumple Fólder A.',
                'documents' => $app->documents()->where('requirement_key', 'solicitud')->pluck('id')->mapWithKeys(fn ($id) => [$id => 'CUMPLE'])->all(),
            ])->assertSessionHas('success');
        }

        // Etapa 2: Aspectos Externos (ambos obtienen 18 pts y superan el mínimo de 16 pts)
        $this->travel(2)->hours();
        $this->actingAs($manager)->post(route('management.vacancies.publish-stage', $vacancy), ['phase' => 'aptitude'])->assertSessionHas('success');
        foreach ([[$magisterApp, $magisterMerits], [$doctorApp, $doctorMerits]] as [$app, $meritList]) {
            $this->actingAs($jurors[0])->post(route('review.evaluate', $app), [
                'stage' => 'externa',
                'action' => 'proponer',
                'revision' => 0,
                'notes' => 'Méritos externos verificados.',
                'merits' => array_column($meritList, 'quantity', 'id'),
            ])->assertSessionHas('success');
            foreach ($jurors->skip(1) as $juror) {
                $this->actingAs($juror)->post(route('review.evaluate', $app), [
                    'stage' => 'externa',
                    'action' => 'confirmar',
                    'revision' => 1,
                    'notes' => 'Conforme.',
                ])->assertSessionHas('success');
            }
        }

        // Etapa 3: Aspectos Internos (ambos obtienen 45 pts -> empatan con 63 pts totales)
        $this->travel(2)->hours();
        $this->actingAs($manager)->post(route('management.vacancies.publish-stage', $vacancy), ['phase' => 'external'])->assertSessionHas('success');
        $internalPayload = [
            'stage' => 'interna',
            'notes' => 'Sílabo, clase modelo y entrevista calificados.',
            'item_scores' => [
                'silabo' => array_fill(0, 5, 1.5),
                'clase' => array_fill(0, 10, 2.5),
                'entrevista' => array_fill(0, 5, 2.5),
            ],
        ];
        foreach ([$magisterApp, $doctorApp] as $app) {
            foreach ($jurors as $juror) {
                $this->actingAs($juror)->post(route('review.evaluate', $app), $internalPayload)->assertSessionHas('success');
            }
        }

        $this->travel(1)->hours();
        $this->actingAs($manager)->post(route('management.calls.transition', $call), ['status' => 'FINALIZADA'])->assertSessionHas('success');

        // Desempate por Grado de Doctor: $doctorApp queda en primer lugar como GANADOR
        $this->get(route('results'))->assertInertia(fn (Assert $page) => $page
            ->where('calls.0.vacancies.0.rows.0.reference', $doctorApp->reference)
            ->where('calls.0.vacancies.0.rows.0.score', 63)
            ->where('calls.0.vacancies.0.rows.0.winner', true)
            ->where('calls.0.vacancies.0.rows.0.tie_breaker', 'doctor')
            ->where('calls.0.vacancies.0.rows.1.reference', $magisterApp->reference)
            ->where('calls.0.vacancies.0.rows.1.winner', false)
            ->where('calls.0.vacancies.0.rows.1.tie_breaker', 'doctor'));
    }
}
