<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\RecruitmentCall;
use App\Models\User;
use App\Models\Vacancy;
use App\Notifications\ApplicationReceived;
use App\Services\ApplicantScreening;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StructuredApplicationTest extends TestCase
{
    private function rules(): array
    {
        return ['enabled' => true, 'minimum_degree' => 'maestro', 'minimum_experience_months' => 60, 'experience_type' => 'toda', 'count_from_bachelor' => true, 'minimum_training_hours' => 0, 'degree_points' => ['titulo' => 2, 'maestro' => 6, 'doctor' => 8], 'experience_points_per_year' => 1, 'experience_cap' => 10, 'training_hours_per_point' => 40, 'training_cap' => 5, 'fields' => []];
    }

    private function application(array $rules = []): Application
    {
        $this->travelTo('2026-11-02 12:00:00');
        $application = Application::factory()->create();
        $application->vacancy->update(['screening_rules' => array_replace($this->rules(), $rules)]);

        return $application->fresh();
    }

    private function payload(): array
    {
        return ['degree' => 'maestro', 'professional_title' => 'Licenciado en Educación', 'bachelor_date' => '2010-01-01', 'experience' => [['id' => (string) Str::uuid(), 'institution' => 'Institución de prueba', 'position' => 'Docente', 'type' => 'docente', 'start_date' => '2018-01-01', 'end_date' => '2023-12-31']], 'training' => [['id' => (string) Str::uuid(), 'name' => 'Curso de prueba', 'institution' => 'Centro de formación', 'date' => '2025-01-01', 'hours' => 80]], 'answers' => []];
    }

    public function test_dates_generate_a_server_calculated_score_and_ignore_an_injected_total(): void
    {
        $application = $this->application();
        $data = [...$this->payload(), 'declared_score' => 99999];
        $this->actingAs($application->user)->put('/mis-postulaciones/'.$application->id.'/formulario', $data)->assertSessionHas('success');
        $report = app(ApplicantScreening::class)->assess($application->fresh());
        $this->assertTrue($report['eligible']);
        $this->assertSame(2191, $report['experience_days']);
        $this->assertSame(6, $report['complete_years']);
        $this->assertSame('14.000', $application->fresh()->declared_score);
    }

    public function test_overlapping_and_adjacent_service_periods_do_not_duplicate_days(): void
    {
        $application = $this->application();
        $data = $this->payload();
        $data['experience'][] = [...$data['experience'][0], 'id' => (string) Str::uuid(), 'start_date' => '2020-01-01', 'end_date' => '2022-12-31'];
        $this->actingAs($application->user)->put('/mis-postulaciones/'.$application->id.'/formulario', $data)->assertSessionHas('success');
        $report = app(ApplicantScreening::class)->assess($application->fresh());
        $this->assertSame(2191, $report['experience_days']);
        $this->assertSame('14.000', $application->fresh()->declared_score);
    }

    public function test_multiple_credentials_are_supported_and_only_the_best_verified_grade_and_one_title_score(): void
    {
        Notification::fake();
        Storage::fake('local');
        $application = $this->application(['academic_credentials' => true]);
        $data = [...$this->payload(), 'consent' => true];
        $data['credentials'] = array_map(
            fn (string $kind): array => ['id' => (string) Str::uuid(), 'kind' => $kind, 'name' => 'Credencial de prueba', 'institution' => 'Universidad de prueba', 'awarded_at' => '2012-01-01'],
            ['bachiller', 'bachiller', 'titulo', 'titulo', 'maestro', 'doctor'],
        );
        $path = '/mis-postulaciones/'.$application->id;
        $this->actingAs($application->user)->put($path.'/formulario', $data)->assertSessionHas('success');
        $application->refresh();
        $this->assertSame($data['credentials'], $application->form_data['credentials']);
        $this->assertArrayNotHasKey('degree', $application->form_data);
        $this->get($path)->assertInertia(fn (Assert $page) => $page->where('screening.academic_points', 10));
        $doctorKey = 'acad_'.$data['credentials'][5]['id'];
        $requirements = app(ApplicantScreening::class)->documents($application);
        foreach ($requirements as $requirement) {
            if ($requirement['required'] && $requirement['key'] !== $doctorKey) {
                ApplicationDocument::factory()->for($application)->create(['requirement_key' => $requirement['key']]);
            }
        }
        $this->post($path.'/enviar', ['confirm' => true])->assertSessionHasErrors('application');
        $file = new UploadedFile(database_path('fixtures/sustento-demo.pdf'), 'doctorado.pdf', 'application/pdf', null, true);
        $this->post($path.'/documentos', ['file' => $file, 'requirement_key' => $doctorKey])->assertSessionHas('success');
        $this->post($path.'/enviar', ['confirm' => true])->assertSessionHas('success');

        $juror = User::factory()->create(['role' => 'jurado']);
        $application->vacancy->jurors()->attach($juror);
        $application->recruitmentCall->update(['status' => 'EN_EVALUACION']);
        $verdicts = $application->documents()->get()->mapWithKeys(fn (ApplicationDocument $document): array => [
            $document->id => $document->requirement_key === $doctorKey ? 'NO_CUMPLE' : 'CUMPLE',
        ])->all();
        $reviewPath = '/expedientes/'.$application->id;
        $this->actingAs($juror)->post($reviewPath.'/aptitud', ['documents' => $verdicts, 'notes' => 'Revisión documental de prueba.'])->assertSessionHasErrors('academic_profile_confirmed');
        $this->post($reviewPath.'/aptitud', ['documents' => $verdicts, 'notes' => 'Revisión documental de prueba.', 'academic_profile_confirmed' => true])->assertSessionHas('success');
        $this->assertSame('APTO', $application->fresh()->status);
        $this->get($reviewPath)->assertInertia(fn (Assert $page) => $page->where('screening.academic_points', 10)->where('verifiedScreening.academic_points', 8));
    }

    public function test_editing_a_credential_requires_a_new_sustaining_document(): void
    {
        Storage::fake('local');
        $application = $this->application(['academic_credentials' => true, 'minimum_degree' => 'titulo', 'count_from_bachelor' => false]);
        $credential = ['id' => (string) Str::uuid(), 'kind' => 'titulo', 'name' => 'Título de prueba', 'institution' => 'Universidad de prueba', 'awarded_at' => '2012-01-01'];
        $data = [...$this->payload(), 'credentials' => [$credential]];
        $path = '/mis-postulaciones/'.$application->id;
        $this->actingAs($application->user)->put($path.'/formulario', $data)->assertSessionHas('success');
        $file = new UploadedFile(database_path('fixtures/sustento-demo.pdf'), 'titulo.pdf', 'application/pdf', null, true);
        $this->post($path.'/documentos', ['file' => $file, 'requirement_key' => 'acad_'.$credential['id']])->assertSessionHas('success');
        $document = $application->documents()->sole();

        $this->put($path.'/formulario', $data)->assertSessionHas('success');
        $this->assertModelExists($document);
        $data['credentials'][0]['institution'] = 'Otra universidad';
        $this->put($path.'/formulario', $data)->assertSessionHas('success');
        $this->assertModelMissing($document);
        Storage::disk('local')->assertMissing($document->path);
    }

    public function test_insufficient_experience_is_saved_but_blocks_documents_and_submission(): void
    {
        $application = $this->application();
        $data = $this->payload();
        $data['experience'][0]['start_date'] = '2024-11-03';
        $data['experience'][0]['end_date'] = '2026-11-02';
        Storage::fake('local');
        Notification::fake();
        $this->actingAs($application->user)->put('/mis-postulaciones/'.$application->id.'/formulario', $data)->assertSessionHas('success');
        $this->get('/mis-postulaciones/'.$application->id)->assertInertia(fn (Assert $page) => $page->where('screening.eligible', false)->where('screening.experience_days', 730));
        $file = new UploadedFile(database_path('fixtures/sustento-demo.pdf'), 'titulo.pdf', 'application/pdf', null, true);
        $this->post('/mis-postulaciones/'.$application->id.'/documentos', ['file' => $file, 'requirement_key' => 'titulo'])->assertSessionHasErrors('application');
        $this->post('/mis-postulaciones/'.$application->id.'/enviar', ['confirm' => true])->assertSessionHasErrors('application');
        $this->assertSame('BORRADOR', $application->fresh()->status);
        Storage::disk('local')->assertEmpty();
        Notification::assertNothingSent();
    }

    public function test_experience_before_bachelor_and_an_unaccepted_experience_type_are_excluded(): void
    {
        $application = $this->application(['experience_type' => 'profesional']);
        $data = $this->payload();
        $data['bachelor_date'] = '2022-01-01';
        $data['experience'][0]['type'] = 'profesional';
        $this->actingAs($application->user)->put('/mis-postulaciones/'.$application->id.'/formulario', $data)->assertSessionHas('success');
        $report = app(ApplicantScreening::class)->assess($application->fresh());
        $this->assertSame(730, $report['experience_days']);
        $this->assertFalse($report['eligible']);
        $data['experience'][0]['type'] = 'docente';
        $this->put('/mis-postulaciones/'.$application->id.'/formulario', $data)->assertSessionHas('success');
        $this->assertSame(0, app(ApplicantScreening::class)->assess($application->fresh())['experience_days']);
    }

    public function test_future_dates_and_reversed_dates_are_rejected_without_persisting(): void
    {
        $application = $this->application();
        $data = $this->payload();
        $data['experience'][0]['end_date'] = '2030-01-01';
        $this->actingAs($application->user)->put('/mis-postulaciones/'.$application->id.'/formulario', $data)->assertSessionHasErrors('experience.0.end_date');
        $this->assertNull($application->fresh()->form_data);
        $data['experience'][0]['end_date'] = '2017-01-01';
        $this->put('/mis-postulaciones/'.$application->id.'/formulario', $data)->assertSessionHasErrors(['experience.0.end_date' => 'La fecha de fin no puede ser anterior al inicio del servicio.']);
    }

    public function test_custom_required_selection_blocks_progress_until_its_expected_value_is_given(): void
    {
        $field = ['key' => 'idioma', 'label' => 'Idioma requerido de prueba', 'type' => 'select', 'required' => true, 'options' => ['Básico', 'Intermedio'], 'expected_value' => 'Intermedio', 'requires_document' => true];
        $application = $this->application(['fields' => [$field]]);
        $data = $this->payload();
        $this->actingAs($application->user)->put('/mis-postulaciones/'.$application->id.'/formulario', $data)->assertSessionHas('success');
        $this->assertFalse(app(ApplicantScreening::class)->assess($application->fresh())['eligible']);
        $data['answers'] = ['idioma' => 'Intermedio'];
        $this->put('/mis-postulaciones/'.$application->id.'/formulario', $data)->assertSessionHas('success');
        $this->assertTrue(app(ApplicantScreening::class)->assess($application->fresh())['eligible']);
        $this->assertContains('campo_idioma', array_column(app(ApplicantScreening::class)->documents($application->fresh()), 'key'));
    }

    public function test_doctor_degree_and_caps_are_applied_without_accumulating_other_degrees(): void
    {
        $application = $this->application(['experience_cap' => 3, 'training_cap' => 1]);
        $data = $this->payload();
        $data['degree'] = 'doctor';
        $this->actingAs($application->user)->put('/mis-postulaciones/'.$application->id.'/formulario', $data)->assertSessionHas('success');
        $report = app(ApplicantScreening::class)->assess($application->fresh());
        $this->assertSame(8.0, $report['academic_points']);
        $this->assertSame(3.0, $report['experience_points']);
        $this->assertSame(1.0, $report['training_points']);
        $this->assertSame('12.000', $application->fresh()->declared_score);
    }

    public function test_each_claimed_service_period_and_course_requires_evidence_at_submission(): void
    {
        $application = $this->application();
        $data = $this->payload();
        $application->update(['form_data' => $data, 'consented_at' => now()]);
        ApplicationDocument::factory()->for($application)->create();
        ApplicationDocument::factory()->for($application)->create(['requirement_key' => 'grado']);
        ApplicationDocument::factory()->for($application)->create(['requirement_key' => 'bachiller']);
        Notification::fake();
        $this->actingAs($application->user)->post('/mis-postulaciones/'.$application->id.'/enviar', ['confirm' => true])->assertSessionHasErrors('application');
        $this->assertSame('BORRADOR', $application->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_submitted_form_and_screening_snapshot_remain_frozen(): void
    {
        $application = $this->application();
        $data = $this->payload();
        $application->update(['form_data' => $data, 'consented_at' => now()]);
        foreach (app(ApplicantScreening::class)->documents($application) as $required) {
            ApplicationDocument::factory()->for($application)->create(['requirement_key' => $required['key']]);
        }Notification::fake();
        $this->actingAs($application->user)->post('/mis-postulaciones/'.$application->id.'/enviar', ['confirm' => true])->assertSessionHas('success');
        Notification::assertSentTo($application->user, ApplicationReceived::class);
        $snapshot = $application->fresh()->screening_snapshot;
        $this->assertTrue($snapshot['eligible']);
        $this->assertSame('14.000', $application->fresh()->declared_score);
        $this->put('/mis-postulaciones/'.$application->id.'/formulario', $data)->assertConflict();
        $this->travelTo('2027-01-01');
        $this->assertSame($snapshot, app(ApplicantScreening::class)->assess($application->fresh()));
    }

    public function test_admin_configures_the_form_only_while_the_call_is_a_draft(): void
    {
        $application = $this->application();
        $application->recruitmentCall->update(['status' => 'BORRADOR']);
        $manager = User::factory()->manager()->create();
        $rules = $this->rules();
        $rules['minimum_experience_months'] = 24;
        $rules['academic_credentials'] = true;
        $rules['allow_residency'] = false;
        $rules['degree_points']['bachiller'] = 1;
        $this->actingAs($manager)->put('/gestion/plazas/'.$application->vacancy_id.'/formulario', ['screening_rules' => $rules])->assertSessionHas('success');
        $this->assertSame(24, $application->vacancy->fresh()->screening_rules['minimum_experience_months']);
        $this->assertTrue($application->vacancy->fresh()->screening_rules['academic_credentials']);
        $application->recruitmentCall->update(['status' => 'PUBLICADA']);
        $this->put('/gestion/plazas/'.$application->vacancy_id.'/formulario', ['screening_rules' => $this->rules()])->assertConflict();
    }

    public function test_another_applicant_cannot_update_the_structured_answers(): void
    {
        $application = $this->application();
        $other = User::factory()->create();
        $this->actingAs($other)->put('/mis-postulaciones/'.$application->id.'/formulario', $this->payload())->assertNotFound();
        $this->assertNull($application->fresh()->form_data);
    }

    public function test_demo_credentials_use_the_dni_and_install_without_fake_applications(): void
    {
        $this->travelTo('2026-11-02');
        $this->app['env'] = 'local';
        $this->withoutMiddleware(PreventRequestForgery::class);
        config(['recruitment.demo' => true]);
        $this->artisan('unheval:demo')->assertSuccessful();
        $this->assertDatabaseCount('applications', 0);
        $this->assertDatabaseCount('vacancies', 2);
        foreach (['admin' => '90000001', 'gestor' => '90000002', 'jurado' => '90000003', 'jurado2' => '90000004', 'jurado3' => '90000005', 'postulante' => '90000006'] as $account => $dni) {
            $this->post('/ingresar', ['email' => $account.'@demo.test', 'password' => $dni])->assertRedirect(route('dashboard'));
            $this->assertAuthenticated();
            $this->post('/salir')->assertRedirect(route('portal'));
        }
    }

    public function test_demo_stage_shortcut_is_not_available_for_an_official_call(): void
    {
        $this->travelTo('2026-11-02');
        $this->app['env'] = 'local';
        $this->withoutMiddleware(PreventRequestForgery::class);
        config(['recruitment.demo' => true]);
        $call = RecruitmentCall::factory()->create(['is_demo' => false]);
        $this->actingAs(User::factory()->manager()->create())->post('/gestion/convocatorias/'.$call->id.'/cerrar-prueba')->assertNotFound();
        $this->assertSame('PUBLICADA', $call->fresh()->status);
    }

    public function test_demo_can_close_registration_without_changing_the_server_clock(): void
    {
        $this->travelTo('2026-11-02 12:00:00');
        $this->app['env'] = 'local';
        $this->withoutMiddleware(PreventRequestForgery::class);
        config(['recruitment.demo' => true]);
        $call = RecruitmentCall::factory()->create();
        $this->actingAs(User::factory()->manager()->create())->post('/gestion/convocatorias/'.$call->id.'/cerrar-prueba')->assertSessionHas('success');
        $this->assertSame('EN_EVALUACION', $call->fresh()->status);
        $this->assertSame('2026-11-02 11:59:59', $call->fresh()->closes_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-02 12:00:00', now()->format('Y-m-d H:i:s'));
    }

    public function test_upcoming_call_is_visible_with_documents_but_cannot_receive_applications(): void
    {
        $this->travelTo('2026-11-02');
        $call = RecruitmentCall::factory()->create(['opens_at' => now()->addDay(), 'closes_at' => now()->addDays(2)]);
        $vacancy = Vacancy::factory()->for($call)->create();
        $this->get('/')->assertInertia(fn (Assert $page) => $page->has('vacancies.data', 1)->where('vacancies.data.0.id', $vacancy->id));
        $this->get('/convocatorias/'.$call->id)->assertOk();
        $this->actingAs(User::factory()->create())->post('/plazas/'.$vacancy->id.'/postular')->assertSessionHasErrors('application');
        $this->assertDatabaseCount('applications', 0);
    }

    public static function calendarBoundaries(): array
    {
        return ['five complete years' => ['2021-11-03', 1826, true], 'one day short' => ['2021-11-04', 1825, false]];
    }

    #[DataProvider('calendarBoundaries')]
    public function test_calendar_minimum_includes_ongoing_service_and_distinguishes_one_day(string $start, int $days, bool $eligible): void
    {
        $application = $this->application();
        $data = $this->payload();
        $data['experience'][0]['start_date'] = $start;
        $data['experience'][0]['end_date'] = null;
        $this->actingAs($application->user)->put('/mis-postulaciones/'.$application->id.'/formulario', $data)->assertSessionHas('success');
        $report = app(ApplicantScreening::class)->assess($application->fresh());
        $this->assertSame($days, $report['experience_days']);
        $this->assertSame($eligible, $report['eligible']);
    }

    public function test_adjacent_periods_in_reverse_order_count_as_one_continuous_service(): void
    {
        $application = $this->application();
        $data = $this->payload();
        $data['experience'][0]['start_date'] = '2021-01-01';
        $data['experience'][] = [...$data['experience'][0], 'id' => (string) Str::uuid(), 'start_date' => '2018-01-01', 'end_date' => '2020-12-31'];
        $this->actingAs($application->user)->put('/mis-postulaciones/'.$application->id.'/formulario', $data)->assertSessionHas('success');
        $this->assertSame(2191, app(ApplicantScreening::class)->assess($application->fresh())['experience_days']);
    }

    public function test_manager_cannot_configure_an_impossible_selection_answer(): void
    {
        $application = $this->application();
        $application->recruitmentCall->update(['status' => 'BORRADOR']);
        $rules = $this->rules();
        $rules['fields'] = [['key' => 'nivel', 'label' => 'Nivel requerido', 'type' => 'select', 'required' => true, 'requires_document' => true, 'options' => ['Básico', 'Intermedio'], 'expected_value' => 'Avanzado']];
        $this->actingAs(User::factory()->manager()->create())->put('/gestion/plazas/'.$application->vacancy_id.'/formulario', ['screening_rules' => $rules])->assertSessionHasErrors(['screening_rules.fields.0.expected_value' => 'La respuesta exigida debe pertenecer a las opciones disponibles.']);
        $this->assertSame([], $application->vacancy->fresh()->screening_rules['fields']);
    }
}
