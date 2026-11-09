<?php

namespace Tests\Feature;

use App\Models\RecruitmentCall;
use App\Models\User;
use App\Services\NombramientoRubric;
use Database\Seeders\NombramientoDemoSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NombramientoDemoSeederTest extends TestCase
{
    public function test_practice_setup_creates_an_open_call_and_a_separate_review_without_overwriting_them(): void
    {
        $this->prepareDemo();
        $open = RecruitmentCall::where('code', 'DEMO-NOMBRAMIENTO-INSCRIPCION')->sole();
        $review = RecruitmentCall::where('code', 'DEMO-NOMBRAMIENTO-EXPEDIENTE')->sole();
        $application = $review->applications()->sole();
        $this->assertTrue($open->accepting_applications);
        $this->assertSame(0, $open->applications()->count());
        $this->assertSame('EN_EVALUACION', $review->status);
        $this->assertSame('RECIBIDA', $application->status);
        $this->assertNotNull($application->receipt_hash);
        $this->assertCount(3, $application->vacancy->jurors);
        $this->assertCount(11, $application->documents);
        Storage::disk('local')->assertExists($application->documents->pluck('path')->all());

        $application->update(['review_notes' => 'Conservar la revisión iniciada.']);
        $this->seed(NombramientoDemoSeeder::class);
        $this->assertDatabaseCount('recruitment_calls', 2);
        $this->assertDatabaseCount('applications', 1);
        $this->assertSame('Conservar la revisión iniciada.', $application->fresh()->review_notes);
        Notification::assertNothingSent();
    }

    public function test_applicant_can_start_a_new_draft_upload_evidence_and_receive_a_receipt(): void
    {
        $this->prepareDemo();
        $open = RecruitmentCall::where('code', 'DEMO-NOMBRAMIENTO-INSCRIPCION')->sole();
        $vacancy = $open->vacancies()->sole();
        $applicant = User::where('email', 'expediente@demo.test')->sole();
        $source = $applicant->applications()->sole();
        $this->post('/ingresar', ['email' => $applicant->email, 'password' => '90000007'])->assertRedirect();
        $this->assertAuthenticatedAs($applicant);
        $this->post(route('applications.store', $vacancy))->assertRedirect();
        $draft = $open->applications()->sole();

        $this->get(route('applications.show', $draft))->assertInertia(fn (Assert $page) => $page
            ->component('Application')
            ->has('profileCredentials', 4)
            ->where('application.status', 'BORRADOR')
        );
        $this->put(route('applications.form', $draft), [...$source->form_data, 'consent' => true])->assertSessionHas('success');
        $this->post(route('applications.submit', $draft), ['confirm' => true])->assertSessionHasErrors('application');
        foreach ($source->documents as $document) {
            $file = new UploadedFile(database_path('fixtures/sustento-demo.pdf'), 'sustento.pdf', 'application/pdf', null, true);
            $this->post(route('applications.upload', $draft), ['file' => $file, 'requirement_key' => $document->requirement_key])->assertSessionHas('success');
        }
        $this->post(route('applications.submit', $draft), ['confirm' => true])->assertSessionHas('success');
        $this->get(route('applications.receipt', $draft))->assertOk()->assertSee($draft->reference);
        $this->assertSame('RECIBIDA', $draft->fresh()->status);
    }

    public function test_practice_file_can_be_reviewed_and_scored_using_the_annex_scales(): void
    {
        $this->prepareDemo();
        $review = RecruitmentCall::where('code', 'DEMO-NOMBRAMIENTO-EXPEDIENTE')->sole();
        $application = $review->applications()->sole();
        $jurors = $application->vacancy->jurors()->orderBy('users.id')->get();
        $this->actingAs($jurors[0])->get(route('review.show', $application))->assertInertia(fn (Assert $page) => $page
            ->component('Review')->where('application.status', 'RECIBIDA')
        );
        $this->get(route('documents.show', $application->documents->first()))->assertOk();
        $this->post(route('review.eligibility', $application), [
            'documents' => $application->documents->mapWithKeys(fn ($document) => [$document->id => 'CUMPLE'])->all(),
            'academic_profile_confirmed' => true,
            'notes' => 'Los PDF ficticios cubren los requisitos de la práctica.',
        ])->assertSessionHas('success');
        $this->assertSame('APTO', $application->fresh()->status);

        $scores = array_fill_keys(array_column(NombramientoRubric::template()['external'], 'key'), 0);
        $scores['formacion'] = 10;
        $scores['formacion_servicio'] = 1;
        $scores['experiencia_universitaria'] = 5;
        $this->post(route('review.evaluate', $application), [
            'stage' => 'externa', 'action' => 'proponer', 'revision' => 0, 'scores' => $scores,
            'notes' => 'Doctorado y título, diplomado afín y semestres docentes acreditados en la simulación.',
        ])->assertSessionHas('success');
        foreach ($jurors->skip(1) as $juror) {
            $this->actingAs($juror)->post(route('review.evaluate', $application), [
                'stage' => 'externa', 'action' => 'confirmar', 'revision' => 1, 'notes' => 'Conformidad con la hoja ficticia.',
            ])->assertSessionHas('success');
        }
        foreach ($jurors as $juror) {
            $this->actingAs($juror)->post(route('review.evaluate', $application), [
                'stage' => 'interna', 'notes' => 'Puntajes por criterio del caso de prueba.',
                'item_scores' => [
                    'silabo' => array_fill(0, 5, 1),
                    'clase' => array_fill(0, 10, 2),
                    'entrevista' => array_fill(0, 5, 1),
                ],
            ])->assertSessionHas('success');
        }
        $this->assertSame('SUPERA_MINIMOS', $application->fresh()->evaluation_outcome['stage']);
        $this->assertSame(46, $application->fresh()->evaluation_outcome['total']);
        $manager = User::where('email', 'gestor@demo.test')->sole();
        $this->actingAs($manager)->post(route('management.calls.transition', $review), ['status' => 'FINALIZADA'])->assertSessionHas('success');
        $this->get(route('results'))->assertInertia(fn (Assert $page) => $page
            ->where('calls.0.vacancies.0.rows.0.score', 46)
            ->where('calls.0.vacancies.0.rows.0.outcome.passed', true)
        );
    }

    private function prepareDemo(): void
    {
        $this->travelTo('2026-11-08 12:00:00');
        $this->app['env'] = 'local';
        $this->withoutMiddleware(PreventRequestForgery::class);
        config(['recruitment.demo' => true]);
        Storage::fake('local');
        Notification::fake();
        $this->seed(NombramientoDemoSeeder::class);
    }
}
