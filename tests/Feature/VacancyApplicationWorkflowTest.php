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
}
