<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\RecruitmentCall;
use App\Models\User;
use App\Services\NombramientoRubric;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NombramientoEvaluationTest extends TestCase
{
    public function test_manager_saves_canonical_template_even_if_browser_changes_the_scores(): void
    {
        $manager = User::factory()->manager()->create();
        $payload = [
            'code' => 'PRUEBA-ANEXOS',
            'title' => 'Prueba de la rúbrica por etapas',
            'description' => 'Convocatoria de demostración',
            'modality' => 'NOMBRAMIENTO',
            'evaluation_mode' => 'nombramiento_referencia',
            'opens_at' => now()->addDay()->toDateTimeString(),
            'closes_at' => now()->addDays(3)->toDateTimeString(),
            'schedule' => [['label' => 'Inscripción', 'date' => 'Fecha de prueba']],
            'rubric' => [['key' => 'formacion', 'name' => 'Modificación no autorizada', 'max' => 100]],
            'is_demo' => true,
        ];
        $this->actingAs($manager)->post('/gestion/convocatorias', $payload)->assertSessionHas('success');
        $call = RecruitmentCall::where('code', 'PRUEBA-ANEXOS')->sole();
        $this->assertCount(15, $call->rubric);
        $this->assertEquals(55, array_sum(array_column($call->evaluation_template['external'], 'max')));
        $this->assertEquals(45, array_sum(array_column($call->evaluation_template['internal'], 'max')));
        $this->assertSame(10, $call->rubric[0]['max']);
        $this->post('/gestion/convocatorias', [...$payload, 'code' => 'PRUEBA-CONTRATO', 'modality' => 'CONTRATO'])->assertSessionHasErrors('evaluation_mode');
    }

    public function test_commission_confirms_one_revision_then_internal_scores_are_averaged_and_published(): void
    {
        [$application, $jurors] = $this->scenario();
        $review = '/expedientes/'.$application->id;
        $endpoint = $review.'/evaluacion';
        $proposal = [
            'stage' => 'externa',
            'action' => 'proponer',
            'revision' => 0,
            'scores' => $this->externalScores(10),
            'notes' => 'Se revisaron los sustentos del expediente.',
        ];
        $confirm = ['stage' => 'externa', 'action' => 'confirmar', 'revision' => 1, 'notes' => 'Conformidad con la hoja externa.'];
        $internal = ['stage' => 'interna', 'item_scores' => $this->internalItems([...array_fill(0, 8, 2), 0.5, 0.5]), 'notes' => 'Evaluación individual de prueba.'];

        $this->actingAs(User::factory()->create(['role' => 'jurado']))->post($endpoint, $proposal)->assertNotFound();
        $this->actingAs($jurors[0])->post($endpoint, $internal)->assertSessionHasErrors('stage');
        $this->post($endpoint, [...$proposal, 'scores' => [...$proposal['scores'], 'clase' => 25]])->assertSessionHasErrors('scores');
        $this->post($endpoint, $proposal)->assertSessionHas('success');
        $this->actingAs($jurors[1])->post($endpoint, $confirm)->assertSessionHas('success');
        $this->post($endpoint, $confirm)->assertSessionHasErrors('action');

        $this->actingAs($jurors[0])->post($endpoint, [...$proposal, 'revision' => 1, 'scores' => $this->externalScores(8)])->assertSessionHas('success');
        $this->actingAs($jurors[2])->post($endpoint, $confirm)->assertSessionHasErrors('revision');
        $this->get($review)->assertInertia(fn (Assert $page) => $page->where('evaluationProgress.proposal.confirmed', 1)->where('evaluationProgress.stage', 'EXTERNA'));
        $this->post($endpoint, [...$confirm, 'revision' => 2])->assertSessionHas('success');
        $this->actingAs($jurors[1])->post($endpoint, [...$confirm, 'revision' => 2])->assertSessionHas('success');
        $this->get($review)->assertInertia(fn (Assert $page) => $page->where('evaluationProgress.stage', 'INTERNA')->where('evaluationProgress.external', 20));

        $invalid = $internal;
        $invalid['item_scores']['clase'][0] = 1;
        $this->actingAs($jurors[0])->post($endpoint, $invalid)->assertSessionHasErrors('item_scores');
        $this->assertDatabaseCount('evaluations', 0);
        $this->post($endpoint, $internal)->assertSessionHas('success');
        $this->assertEquals(17, $application->evaluations()->sole()->scores['clase']);
        $this->assertSame($internal['item_scores']['clase'], $application->evaluations()->sole()->scores['_items']['clase']);
        $this->post($endpoint, $internal)->assertSessionHasErrors('scores');
        $this->actingAs($jurors[1])->get($review)->assertInertia(fn (Assert $page) => $page->where('ownEvaluation', null));
        $this->post($endpoint, [...$internal, 'item_scores' => $this->internalItems(array_fill(0, 10, 2))])->assertSessionHas('success');
        $this->actingAs($jurors[2])->post($endpoint, [...$internal, 'item_scores' => $this->internalItems([...array_fill(0, 7, 2), 0, 0, 0])])->assertSessionHas('success');
        $this->assertSame('EVALUADA', $application->fresh()->status);
        $this->assertSame(['stage' => 'SUPERA_MINIMOS', 'passed' => true, 'external' => 20, 'internal' => 27, 'total' => 47], $application->fresh()->evaluation_outcome);

        $this->get('/resultados')->assertInertia(fn (Assert $page) => $page->has('calls', 0));
        $this->actingAs(User::factory()->manager()->create())->post('/gestion/convocatorias/'.$application->recruitment_call_id.'/etapa', ['status' => 'FINALIZADA'])->assertSessionHas('success');
        $this->get('/resultados')->assertInertia(fn (Assert $page) => $page
            ->where('calls.0.vacancies.0.rows.0.outcome.passed', true)
            ->where('calls.0.vacancies.0.rows.0.score', 47)
            ->missing('calls.0.vacancies.0.rows.0.applicant_snapshot'));
    }

    public function test_external_minimum_blocks_internal_and_the_case_can_be_closed(): void
    {
        [$application, $jurors] = $this->scenario();
        $endpoint = '/expedientes/'.$application->id.'/evaluacion';
        $this->actingAs($jurors[0])->post($endpoint, ['stage' => 'externa', 'action' => 'proponer', 'revision' => 0, 'scores' => $this->externalScores(2), 'notes' => 'Solo se acreditaron 14 puntos.'])->assertSessionHas('success');
        foreach ([$jurors[1], $jurors[2]] as $juror) {
            $this->actingAs($juror)->post($endpoint, ['stage' => 'externa', 'action' => 'confirmar', 'revision' => 1, 'notes' => 'Conformidad con la evaluación.'])->assertSessionHas('success');
        }
        $outcome = $application->fresh()->evaluation_outcome;
        $this->assertSame('NO_SUPERA_EXTERNA', $outcome['stage']);
        $this->assertSame(14, $outcome['external']);
        $this->assertNull($outcome['internal']);
        $this->assertNull($outcome['total']);
        $this->assertDatabaseCount('evaluations', 0);
        $this->post($endpoint, ['stage' => 'interna', 'scores' => ['silabo' => 7.5, 'clase' => 25, 'entrevista' => 12.5], 'notes' => 'La etapa está cerrada.'])->assertConflict();
    }

    public function test_higher_external_score_does_not_compensate_for_low_internal_score(): void
    {
        [$application, $jurors] = $this->scenario();
        $application->update(['external_evaluation' => [
            'scores' => $this->externalScores(10),
            'total' => 22,
            'notes' => 'Hoja externa aprobada por la comisión.',
            'revision' => 1,
            'confirmations' => array_fill_keys(array_column($jurors, 'id'), now()->toIso8601String()),
        ]]);
        foreach ($jurors as $juror) {
            $this->actingAs($juror)->post('/expedientes/'.$application->id.'/evaluacion', [
                'stage' => 'interna',
                'item_scores' => $this->internalItems([...array_fill(0, 5, 2), 0, 0, 0, 0, 0]),
                'notes' => 'Resultado individual de la evaluación.',
            ])->assertSessionHas('success');
        }
        $outcome = $application->fresh()->evaluation_outcome;
        $this->assertSame('NO_SUPERA_INTERNA', $outcome['stage']);
        $this->assertFalse($outcome['passed']);
        $this->assertSame(42, $outcome['total']);
    }

    private function scenario(): array
    {
        $application = Application::factory()->received()->create(['status' => 'APTO']);
        $application->recruitmentCall->update(['evaluation_template' => NombramientoRubric::template(), 'status' => 'EN_EVALUACION', 'is_demo' => true]);
        $jurors = User::factory()->count(3)->create(['role' => 'jurado']);
        $application->vacancy->jurors()->attach($jurors->modelKeys());

        return [$application, $jurors->all()];
    }

    private function internalItems(array $class): array
    {
        return [
            'silabo' => array_fill(0, 5, 1),
            'clase' => $class,
            'entrevista' => array_fill(0, 5, 1),
        ];
    }

    private function externalScores(int $formation): array
    {
        return [
            ...array_fill_keys(array_column(NombramientoRubric::template()['external'], 'key'), 0),
            'formacion' => $formation,
            'formacion_servicio' => 3,
            'experiencia_universitaria' => 5,
            'eventos' => 4,
        ];
    }
}
