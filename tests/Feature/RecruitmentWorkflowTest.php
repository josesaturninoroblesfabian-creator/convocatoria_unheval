<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\Evaluation;
use App\Models\RecruitmentCall;
use App\Models\User;
use App\Models\Vacancy;
use App\Notifications\ApplicationReceived;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RecruitmentWorkflowTest extends TestCase
{
    public function test_public_registration_ignores_attempted_admin_role(): void
    {
        $response = $this->post('/registro', ['name' => 'Cuenta de prueba', 'document_number' => '12345678', 'phone' => '900000000', 'email' => 'PERSONA@example.test', 'password' => 'Unheval2026Prueba', 'password_confirmation' => 'Unheval2026Prueba', 'consent' => true, 'role' => 'administrador']);
        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('users', ['email' => 'persona@example.test', 'role' => 'postulante']);
        $this->assertAuthenticated();
    }

    public function test_password_reset_changes_password_and_invalidates_token(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);
        $response = $this->post('/restablecer', ['email' => $user->email, 'token' => $token, 'password' => 'NuevaClave2026', 'password_confirmation' => 'NuevaClave2026']);
        $response->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('NuevaClave2026', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_password_recovery_queues_a_link_for_the_registered_email(): void
    {
        $user = User::factory()->create();
        Notification::fake();
        $this->post('/recuperar-acceso', ['email' => $user->email])->assertSessionHas('success');
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_private_panel_requires_authentication(): void
    {
        $this->get('/panel')->assertRedirect(route('login'));
    }

    public static function restrictedRoles(): array
    {
        return ['postulante' => ['postulante'], 'jurado' => ['jurado'], 'gestor' => ['gestor']];
    }

    #[DataProvider('restrictedRoles')]
    public function test_non_admin_cannot_create_staff_users(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $this->actingAs($user)->post('/gestion/usuarios', ['name' => 'Intruso', 'email' => 'intruso@example.test', 'role' => 'administrador', 'password' => 'ClaveInicial2026!'])->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'intruso@example.test']);
    }

    public function test_drafts_are_not_exposed_on_the_public_portal(): void
    {
        $call = RecruitmentCall::factory()->draft()->create();
        $vacancy = Vacancy::factory()->for($call)->create();
        $this->get('/?status=todas')->assertInertia(fn (Assert $page) => $page->component('Portal')->has('vacancies.data', 0));
        $this->get('/plazas/'.$vacancy->id)->assertNotFound();
    }

    public function test_search_filters_by_school_without_accepting_sql_injection(): void
    {
        $this->freezeTime();
        Vacancy::factory()->create(['school' => 'Turismo y Hotelería']);
        Vacancy::factory()->create(['school' => 'Biología']);
        $this->get('/?q=Turismo')->assertInertia(fn (Assert $page) => $page->has('vacancies.data', 1)->where('vacancies.data.0.school', 'Turismo y Hotelería'));
        $this->get('/?q='.urlencode("' OR 1=1 --"))->assertInertia(fn (Assert $page) => $page->has('vacancies.data', 0));
    }

    public function test_applicant_cannot_open_another_plaza_in_the_same_call(): void
    {
        $this->freezeTime();
        $app = Application::factory()->create();
        $other = Vacancy::factory()->create(['recruitment_call_id' => $app->recruitment_call_id]);
        $this->actingAs($app->user)->post('/plazas/'.$other->id.'/postular')->assertSessionHasErrors('application');
        $this->assertDatabaseCount('applications', 1);
    }

    public static function closingBoundary(): array
    {
        return ['closing second still open' => ['2026-11-01 15:00:00.500000', true], 'next second closed' => ['2026-11-01 15:00:01', false], 'before opening closed' => ['2026-10-31 23:59:59', false]];
    }

    #[DataProvider('closingBoundary')]
    public function test_inscription_enforces_the_server_deadline(string $instant, bool $allowed): void
    {
        $call = RecruitmentCall::factory()->create(['opens_at' => '2026-11-01 00:00:00', 'closes_at' => '2026-11-01 15:00:00']);
        $v = Vacancy::factory()->for($call)->create();
        $user = User::factory()->create();
        $this->travelTo($instant);
        $response = $this->actingAs($user)->post('/plazas/'.$v->id.'/postular');
        if ($allowed) {
            $response->assertRedirect();
            $this->assertDatabaseHas('applications', ['user_id' => $user->id, 'vacancy_id' => $v->id]);
        } else {
            $response->assertSessionHasErrors('application');
            $this->assertDatabaseCount('applications', 0);
        }
    }

    public function test_submission_requires_all_mandatory_documents(): void
    {
        $this->freezeTime();
        $app = Application::factory()->create(['consented_at' => now()]);
        Notification::fake();
        $this->actingAs($app->user)->post('/mis-postulaciones/'.$app->id.'/enviar', ['confirm' => true])->assertSessionHasErrors(['application' => 'Falta el documento obligatorio: Título profesional']);
        $this->assertDatabaseHas('applications', ['id' => $app->id, 'status' => 'BORRADOR', 'submitted_at' => null]);
        Notification::assertNothingSent();
    }

    public function test_submission_creates_an_immutable_receipt_and_notification(): void
    {
        $this->freezeTime();
        $app = Application::factory()->create(['consented_at' => now()]);
        ApplicationDocument::factory()->for($app)->create();
        Notification::fake();
        $this->actingAs($app->user)->post('/mis-postulaciones/'.$app->id.'/enviar', ['confirm' => true])->assertSessionHas('success');
        $this->assertDatabaseHas('applications', ['id' => $app->id, 'status' => 'RECIBIDA']);
        $this->assertSame(64, strlen($app->fresh()->receipt_hash));
        Notification::assertSentTo($app->user, ApplicationReceived::class);
        $this->put('/mis-postulaciones/'.$app->id, ['statement' => 'Edición indebida', 'consent' => true])->assertConflict();
        $this->assertDatabaseMissing('applications', ['statement' => 'Edición indebida']);
    }

    public function test_submission_rechecks_the_deadline_even_for_an_existing_draft(): void
    {
        $this->freezeTime();
        $call = RecruitmentCall::factory()->create(['closes_at' => now()->subSecond()]);
        $app = Application::factory()->for($call)->create(['consented_at' => now()]);
        ApplicationDocument::factory()->for($app)->create();
        Notification::fake();
        $this->actingAs($app->user)->post('/mis-postulaciones/'.$app->id.'/enviar', ['confirm' => true])->assertSessionHasErrors('application');
        $this->assertDatabaseHas('applications', ['id' => $app->id, 'status' => 'BORRADOR']);
        Notification::assertNothingSent();
    }

    public function test_valid_pdf_is_stored_privately_with_a_content_hash(): void
    {
        $this->freezeTime();
        $app = Application::factory()->create();
        Storage::fake('local');
        $file = new UploadedFile(database_path('fixtures/sustento-demo.pdf'), 'titulo.pdf', 'application/pdf', null, true);
        $this->actingAs($app->user)->post('/mis-postulaciones/'.$app->id.'/documentos', ['requirement_key' => 'titulo', 'file' => $file])->assertSessionHas('success');
        $doc = $app->documents()->sole();
        Storage::disk('local')->assertExists($doc->path);
        $this->assertSame(hash_file('sha256', database_path('fixtures/sustento-demo.pdf')), $doc->sha256);
        $this->get('/documentos/'.$doc->id)->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_upload_rejects_non_pdf_content_and_oversized_files(): void
    {
        $this->freezeTime();
        $app = Application::factory()->create();
        Storage::fake('local');
        $this->actingAs($app->user)->post('/mis-postulaciones/'.$app->id.'/documentos', ['requirement_key' => 'titulo', 'file' => new UploadedFile(database_path('fixtures/no-es-pdf.txt'), 'titulo.pdf', 'application/pdf', null, true)])->assertSessionHasErrors('file');
        $this->post('/mis-postulaciones/'.$app->id.'/documentos', ['requirement_key' => 'titulo', 'file' => UploadedFile::fake()->create('large.pdf', 5121, 'application/pdf')])->assertSessionHasErrors(['file' => 'El PDF debe pesar como máximo 5 MB.']);
        $this->assertDatabaseCount('application_documents', 0);
        Storage::disk('local')->assertEmpty();
    }

    public function test_document_and_application_access_are_limited_to_owner_or_assigned_staff(): void
    {
        $app = Application::factory()->received()->create();
        $doc = ApplicationDocument::factory()->for($app)->create();
        $other = User::factory()->create();
        $this->actingAs($other)->get('/mis-postulaciones/'.$app->id)->assertNotFound();
        $this->get('/documentos/'.$doc->id)->assertNotFound();
        $juror = User::factory()->juror()->create();
        $this->actingAs($juror)->get('/expedientes/'.$app->id)->assertNotFound();
        $this->get('/documentos/'.$doc->id)->assertNotFound();
    }

    public function test_manager_creates_a_draft_without_accepting_published_status(): void
    {
        $this->freezeTime();
        $user = User::factory()->manager()->create();
        $payload = RecruitmentCall::factory()->make()->only(['code', 'title', 'description', 'modality', 'opens_at', 'closes_at', 'schedule', 'rubric', 'is_demo']);
        $payload['status'] = 'PUBLICADA';
        $this->actingAs($user)->post('/gestion/convocatorias', $payload)->assertRedirect();
        $this->assertDatabaseHas('recruitment_calls', ['code' => $payload['code'], 'status' => 'BORRADOR']);
        $this->assertDatabaseHas('audit_events', ['action' => 'CONVOCATORIA_CREADA', 'user_id' => $user->id]);
    }

    public function test_publication_requires_a_jury_and_rejects_official_processes(): void
    {
        $this->freezeTime();
        $manager = User::factory()->manager()->create();
        $call = RecruitmentCall::factory()->draft()->create();
        $vacancy = Vacancy::factory()->for($call)->create();
        $this->actingAs($manager)->post('/gestion/convocatorias/'.$call->id.'/etapa', ['status' => 'PUBLICADA'])->assertSessionHasErrors('status');
        $vacancy->jurors()->attach(User::factory()->juror()->create());
        $call->update(['is_demo' => false]);
        $this->post('/gestion/convocatorias/'.$call->id.'/etapa', ['status' => 'PUBLICADA'])->assertSessionHasErrors('status');
        $this->assertSame('BORRADOR', $call->fresh()->status);
    }

    public function test_manager_can_publish_a_complete_demo_call(): void
    {
        $this->freezeTime();
        $manager = User::factory()->manager()->create();
        $call = RecruitmentCall::factory()->draft()->create();
        $vacancy = Vacancy::factory()->for($call)->create();
        $vacancy->jurors()->attach(User::factory()->juror()->create());
        $this->actingAs($manager)->post('/gestion/convocatorias/'.$call->id.'/etapa', ['status' => 'PUBLICADA'])->assertSessionHas('success');
        $this->assertSame('PUBLICADA', $call->fresh()->status);
    }

    public function test_jury_cannot_be_changed_after_receiving_an_application(): void
    {
        $this->freezeTime();
        $app = Application::factory()->received()->create();
        $jury = User::factory()->juror()->create();
        $app->vacancy->jurors()->attach($jury);
        $replacement = User::factory()->juror()->create();
        $this->actingAs(User::factory()->manager()->create())->put('/gestion/plazas/'.$app->vacancy_id.'/jurados', ['jurors' => [$replacement->id]])->assertConflict();
        $this->assertDatabaseHas('jury_assignments', ['vacancy_id' => $app->vacancy_id, 'user_id' => $jury->id]);
    }

    public function test_eligibility_requires_review_of_each_document_and_can_reject(): void
    {
        $this->freezeTime();
        $call = RecruitmentCall::factory()->evaluating()->create();
        $app = Application::factory()->received()->for($call)->create();
        $juror = User::factory()->juror()->create();
        $app->vacancy->jurors()->attach($juror);
        $document = ApplicationDocument::factory()->for($app)->create();
        $this->actingAs($juror)->post('/expedientes/'.$app->id.'/aptitud', ['notes' => 'El título no corresponde al perfil.', 'documents' => [$document->id => 'NO_CUMPLE']])->assertSessionHas('success');
        $this->assertDatabaseHas('applications', ['id' => $app->id, 'status' => 'NO_APTO']);
        $this->assertDatabaseHas('application_documents', ['id' => $document->id, 'review_status' => 'NO_CUMPLE']);
    }

    public function test_score_above_the_configured_maximum_is_rejected(): void
    {
        $this->freezeTime();
        $call = RecruitmentCall::factory()->evaluating()->create();
        $app = Application::factory()->received()->for($call)->create(['status' => 'APTO']);
        $juror = User::factory()->juror()->create();
        $app->vacancy->jurors()->attach($juror);
        $this->actingAs($juror)->post('/expedientes/'.$app->id.'/evaluacion', ['scores' => ['meritos' => 56, 'clase' => 30], 'notes' => 'Calificación motivada.'])->assertSessionHasErrors('scores');
        $this->assertDatabaseCount('evaluations', 0);
        $this->assertSame('APTO', $app->fresh()->status);
    }

    public function test_individual_grades_remain_private_and_the_last_juror_closes_evaluation(): void
    {
        $this->freezeTime();
        $call = RecruitmentCall::factory()->evaluating()->create();
        $app = Application::factory()->received()->for($call)->create(['status' => 'APTO']);
        $juror = User::factory()->juror()->create();
        $other = User::factory()->juror()->create();
        $app->vacancy->jurors()->attach([$juror->id, $other->id]);
        Evaluation::factory()->for($app)->create(['user_id' => $other->id, 'notes' => 'Comentario privado del otro jurado']);
        $this->actingAs($juror)->get('/expedientes/'.$app->id)->assertInertia(fn (Assert $page) => $page->where('ownEvaluation', null)->where('evaluationCount', 1)->missing('application.evaluations'));
        $this->post('/expedientes/'.$app->id.'/evaluacion', ['scores' => ['meritos' => 45.125, 'clase' => 30.25], 'notes' => 'Calificación cerrada con sustento.'])->assertSessionHas('success');
        $this->assertDatabaseHas('evaluations', ['application_id' => $app->id, 'user_id' => $juror->id, 'total' => 75.375]);
        $this->assertSame('EVALUADA', $app->fresh()->status);
    }

    public function test_results_only_become_public_after_finalization_without_personal_data(): void
    {
        $this->freezeTime();
        $call = RecruitmentCall::factory()->evaluating()->create();
        $app = Application::factory()->received()->for($call)->create(['status' => 'EVALUADA']);
        Evaluation::factory()->for($app)->create();
        $this->get('/resultados')->assertInertia(fn (Assert $page) => $page->has('calls', 0));
        $this->actingAs(User::factory()->manager()->create())->post('/gestion/convocatorias/'.$call->id.'/etapa', ['status' => 'FINALIZADA'])->assertSessionHas('success');
        $this->get('/resultados')->assertInertia(fn (Assert $page) => $page->has('calls', 1)->where('calls.0.vacancies.0.rows.0.reference', $app->reference)->where('calls.0.vacancies.0.rows.0.score', 70)->missing('calls.0.vacancies.0.rows.0.user')->missing('calls.0.vacancies.0.rows.0.applicant_snapshot'));
    }

    public function test_finalization_refuses_pending_applications(): void
    {
        $this->freezeTime();
        $call = RecruitmentCall::factory()->evaluating()->create();
        Application::factory()->received()->for($call)->create();
        $this->actingAs(User::factory()->manager()->create())->post('/gestion/convocatorias/'.$call->id.'/etapa', ['status' => 'FINALIZADA'])->assertSessionHasErrors('status');
        $this->assertSame(null, $call->fresh()->results_published_at);
    }

    public function test_receipt_escapes_personal_text(): void
    {
        $app = Application::factory()->received()->create(['applicant_snapshot' => ['name' => '<script>alert(1)</script>', 'document_number' => '12345678'], 'receipt_hash' => str_repeat('a', 64)]);
        $this->actingAs($app->user)->get('/mis-postulaciones/'.$app->id.'/cargo')->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_juror_dashboard_only_lists_assigned_calls_and_vacancies(): void
    {
        $this->freezeTime();
        $juror = User::factory()->juror()->create();
        $assigned = Vacancy::factory()->create();
        $assigned->jurors()->attach($juror);
        Vacancy::factory()->create(['school' => 'Escuela sin asignación']);
        $this->actingAs($juror)->get('/panel')->assertInertia(fn (Assert $page) => $page->where('stats.calls', 1)->where('stats.vacancies', 1)->has('calls', 1)->where('calls.0.id', $assigned->recruitment_call_id));
    }

    public function test_manager_can_add_a_vacancy_preserving_leading_zeroes(): void
    {
        $call = RecruitmentCall::factory()->draft()->create();
        $payload = Vacancy::factory()->make()->only(['faculty', 'school', 'airhsp', 'cap', 'category', 'dedication', 'weekly_hours', 'requirements', 'courses']);
        $payload['airhsp'] = '000078';
        $this->actingAs(User::factory()->manager()->create())->post('/gestion/convocatorias/'.$call->id.'/plazas', $payload)->assertSessionHas('success');
        $this->assertDatabaseHas('vacancies', ['recruitment_call_id' => $call->id, 'airhsp' => '000078', 'cap' => '318']);
        $this->assertDatabaseHas('audit_events', ['action' => 'PLAZA_CREADA']);
    }

    public function test_sent_application_keeps_the_original_profile_after_profile_changes(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $app = Application::factory()->received()->for($user)->create();
        $snapshot = $app->applicant_snapshot;
        $this->actingAs($user)->put('/perfil', ['name' => 'Nombre actualizado', 'document_number' => $user->document_number, 'phone' => '999111222'])->assertSessionHas('success');
        $this->assertSame($snapshot, $app->fresh()->applicant_snapshot);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Nombre actualizado', 'phone' => '999111222']);
    }

    public function test_submission_without_saved_consent_is_rejected(): void
    {
        $this->freezeTime();
        $app = Application::factory()->create();
        ApplicationDocument::factory()->for($app)->create();
        Notification::fake();
        $this->actingAs($app->user)->post('/mis-postulaciones/'.$app->id.'/enviar', ['confirm' => true])->assertSessionHasErrors('application');
        $this->assertSame('BORRADOR', $app->fresh()->status);
        Notification::assertNothingSent();
    }
}
