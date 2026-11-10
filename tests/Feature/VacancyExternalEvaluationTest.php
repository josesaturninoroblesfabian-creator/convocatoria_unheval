<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\RecruitmentCall;
use App\Models\User;
use App\Models\Vacancy;
use App\Services\ExternalEvaluationRubric;
use App\Services\InternalEvaluationRubric;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class VacancyExternalEvaluationTest extends TestCase
{
    #[TestWith(['NOMBRAMIENTO', 'AUXILIAR', 16, 'Profesor principal'])]
    #[TestWith(['NOMBRAMIENTO', 'ASOCIADO', 26, 'Profesor principal'])]
    #[TestWith(['NOMBRAMIENTO', 'PRINCIPAL', 36, 'Profesor principal'])]
    #[TestWith(['CONTRATO', 'DC A1', 18, 'Profesor principal o DC A'])]
    #[TestWith(['CONTRATO', 'DC A2', 18, 'Profesor principal o DC A'])]
    #[TestWith(['CONTRATO', 'DC A3', 18, 'Profesor principal o DC A'])]
    #[TestWith(['CONTRATO', 'DC B1', 16, 'Profesor principal o DC A'])]
    #[TestWith(['CONTRATO', 'DC B2', 16, 'Profesor principal o DC A'])]
    #[TestWith(['CONTRATO', 'DC B3', 16, 'Profesor principal o DC A'])]
    public function test_manager_loads_the_annex_and_cutoff_from_the_saved_vacancy(string $modality, string $category, int $minimum, string $principalCriterion): void
    {
        $call = RecruitmentCall::factory()->draft()->create(['modality' => $modality]);
        $vacancy = Vacancy::factory()->for($call)->create(['category' => $category]);
        $this->actingAs(User::factory()->manager()->create())
            ->get(route('management.vacancies.external.edit', $vacancy))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('VacancyEvaluation')
                ->where('template.category', $category)
                ->where('template.modality', $modality)
                ->where('template.minimum', $minimum)
                ->where('template.maximum', 55)
                ->where('template.disability_bonus.stage', 'resultado final aprobatorio')
                ->has('template.rubrics', 12)
                ->where('template.rubrics.0.items.0.points', 8)
                ->where('template.rubrics.0.items.3.points', 2)
                ->where('template.rubrics.4.items.0.name', $principalCriterion)
                ->where('configuration', null)
            );
    }

    public function test_adjustments_need_an_approved_reference_and_save_only_for_the_selected_vacancy(): void
    {
        $call = RecruitmentCall::factory()->draft()->create();
        $vacancy = Vacancy::factory()->for($call)->create(['category' => 'PRINCIPAL']);
        $otherVacancy = Vacancy::factory()->for($call)->create(['category' => 'ASOCIADO']);
        $payload = $this->payload($vacancy);
        $payload['rubrics'][0]['items'][0]['points'] = 9;
        $payload['rubrics'][0]['note'] = 'Revisar constancia de reconocimiento.';
        $payload['rubrics'][0]['name'] = 'Formación y credenciales';
        $payload['rubrics'][0]['max'] = 11;
        array_pop($payload['rubrics']);
        $payload['rubrics'][] = [
            'key' => 'innovacion',
            'name' => 'Innovación docente',
            'max' => 3,
            'items' => [['key' => 'proyecto', 'name' => 'Proyecto acreditado', 'unit' => 'Por proyecto', 'points' => 1.5]],
        ];
        $payload['minimum'] = 37;
        $route = route('management.vacancies.external.update', $vacancy);

        $this->actingAs(User::factory()->manager()->create())
            ->put($route, $payload)->assertSessionHasErrors([
                'reference' => 'Indica la resolución o el documento aprobado que respalda los cambios de la rúbrica.',
            ]);
        $this->assertNull($vacancy->fresh()->external_evaluation);
        $this->assertDatabaseCount('audit_events', 0);

        $this->put($route, [...$payload, 'reference' => 'Resolución de prueba, anexo 01, numeral 1'])->assertSessionHas('success');
        $saved = $vacancy->fresh()->external_evaluation;
        $this->assertSame(37, $saved['minimum']);
        $this->assertSame(56.5, $saved['maximum']);
        $this->assertSame('Formación y credenciales', $saved['rubrics'][0]['name']);
        $this->assertSame(9, $saved['rubrics'][0]['items'][0]['points']);
        $this->assertSame('Revisar constancia de reconocimiento.', $saved['rubrics'][0]['note']);
        $this->assertNull($otherVacancy->fresh()->external_evaluation);
        $this->assertSame('innovacion', $saved['rubrics'][11]['key']);
        $this->assertSame(['previous' => 36, 'current' => 37], AuditEvent::sole()->metadata['minimum']);

        $this->get(route('management.calls.show', $call))->assertInertia(fn (Assert $page) => $page
            ->where('configuredExternalVacancyIds', [$vacancy->id])
            ->missing('call.vacancies.0.external_evaluation')
        );
        $this->post(route('management.calls.transition', $call), ['status' => 'PUBLICADA'])->assertSessionHasErrors([
            'status' => 'La publicación queda pendiente hasta completar el flujo de evaluación por plaza.',
        ]);
    }

    public function test_template_rejects_values_above_the_rubric_cap(): void
    {
        $vacancy = Vacancy::factory()->for(RecruitmentCall::factory()->draft())->create();
        $payload = $this->payload($vacancy);
        $payload['rubrics'][0]['items'][0]['points'] = 11;

        $this->actingAs(User::factory()->manager()->create())
            ->put(route('management.vacancies.external.update', $vacancy), $payload)
            ->assertSessionHasErrors('rubrics.0.items.0.points');
        $this->assertNull($vacancy->fresh()->external_evaluation);
    }

    public function test_category_changes_reset_the_configuration_and_reject_an_old_open_form(): void
    {
        $vacancy = Vacancy::factory()->for(RecruitmentCall::factory()->draft())->create();
        $route = route('management.vacancies.external.update', $vacancy);
        $payload = $this->payload($vacancy);
        $this->actingAs(User::factory()->manager()->create())->put($route, $payload)->assertSessionHas('success');
        $internalRoute = route('management.vacancies.internal.update', $vacancy);
        $internalPayload = $this->payload($vacancy, true);
        $this->put($internalRoute, $internalPayload)->assertSessionHas('success');
        $this->put(route('management.vacancies.update', $vacancy), [
            ...$vacancy->only('faculty', 'school', 'airhsp', 'cap', 'dedication', 'weekly_hours', 'courses'),
            'category' => 'ASOCIADO',
        ])->assertSessionHas('success');

        $this->assertNull($vacancy->fresh()->external_evaluation);
        $this->assertNull($vacancy->fresh()->internal_evaluation);
        $this->assertDatabaseHas('audit_events', ['action' => 'RUBRICAS_PLAZA_REINICIADAS', 'subject_id' => $vacancy->id]);
        $this->put($route, $payload)->assertSessionHasErrors('category');
        $this->put($internalRoute, $internalPayload)->assertSessionHasErrors('category');
        $this->get(route('management.vacancies.external.edit', $vacancy))
            ->assertInertia(fn (Assert $page) => $page->where('template.minimum', 26));
    }

    public function test_non_manager_cannot_view_or_save_and_published_plazas_cannot_be_edited(): void
    {
        $vacancy = Vacancy::factory()->create();
        $edit = route('management.vacancies.external.edit', $vacancy);
        $update = route('management.vacancies.external.update', $vacancy);
        $payload = $this->payload($vacancy);

        $this->get($edit)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get($edit)->assertForbidden();
        $this->put($update, $payload)->assertForbidden();
        $this->actingAs(User::factory()->manager()->create())->put($update, $payload)->assertConflict();
        $this->assertNull($vacancy->fresh()->external_evaluation);
    }

    #[TestWith(['NOMBRAMIENTO', 'AUXILIAR', 24, 'Sílabo', 'veinte minutos'])]
    #[TestWith(['NOMBRAMIENTO', 'ASOCIADO', 34, 'Sílabo', 'veinte minutos'])]
    #[TestWith(['NOMBRAMIENTO', 'PRINCIPAL', 34, 'Sílabo', 'veinte minutos'])]
    #[TestWith(['CONTRATO', 'DC A2', 26, 'Sílabo o planeación didáctica', '30 minutos'])]
    #[TestWith(['CONTRATO', 'DC B3', 24, 'Sílabo o planeación didáctica', '30 minutos'])]
    public function test_internal_annex_loads_the_correct_criteria_and_minimum(string $modality, string $category, int $minimum, string $syllabus, string $duration): void
    {
        $call = RecruitmentCall::factory()->draft()->create(['modality' => $modality]);
        $vacancy = Vacancy::factory()->for($call)->create(['category' => $category]);
        $this->actingAs(User::factory()->manager()->create())
            ->get(route('management.vacancies.internal.edit', $vacancy))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('VacancyEvaluation')
                ->where('stage', 'internal')
                ->where('template.minimum', $minimum)
                ->where('template.maximum', 45)
                ->has('template.rubrics', 3)
                ->where('template.rubrics.0.name', $syllabus)
                ->where('template.rubrics.0.max', 7.5)
                ->where('template.rubrics.1.max', 25)
                ->where('template.rubrics.1.guidance', fn (string $guidance): bool => str_contains($guidance, $duration))
                ->where('template.rubrics.2.max', 12.5)
                ->has('template.rubrics.0.items', 5)
                ->has('template.rubrics.1.items', 10)
                ->has('template.rubrics.2.items', 5)
                ->where('template.rubrics.0.items.0.levels.0.points', 1.5)
                ->where('template.rubrics.1.items.0.levels.3.points', 0)
                ->where('template.rubrics.2.items.0.levels.1.points', 1.75)
            );
    }

    public function test_internal_scale_changes_recalculate_the_maximum_and_leave_external_rules_separate(): void
    {
        $vacancy = Vacancy::factory()->for(RecruitmentCall::factory()->draft())->create();
        $payload = $this->payload($vacancy, true);
        $route = route('management.vacancies.internal.update', $vacancy);
        $this->actingAs(User::factory()->manager()->create());
        $this->put($route, $payload)->assertSessionHas('success');

        $payload['rubrics'][0]['items'][0]['levels'][] = ['points' => 2, 'description' => 'Nivel añadido'];
        $this->put($route, $payload)->assertSessionHasErrors('revision');
        $payload['revision'] = $vacancy->fresh()->internal_evaluation['revision'];
        $this->put($route, $payload)->assertSessionHasErrors('reference');
        $payload['reference'] = 'Anexo aprobado de prueba';
        $this->put($route, [...$payload, 'minimum' => 46])->assertSessionHasErrors('minimum');
        $this->put($route, $payload)->assertSessionHas('success');

        $vacancy->refresh();
        $this->assertSame(45.5, $vacancy->internal_evaluation['maximum']);
        $this->assertSame(8, $vacancy->internal_evaluation['rubrics'][0]['max']);
        $this->assertNull($vacancy->external_evaluation);
        $this->assertDatabaseHas('audit_events', ['action' => 'ASPECTOS_INTERNOS_CONFIGURADOS', 'subject_id' => $vacancy->id]);
        $this->post(route('management.calls.transition', $vacancy->recruitmentCall), ['status' => 'PUBLICADA'])->assertSessionHasErrors('status');
    }

    private function payload(Vacancy $vacancy, bool $internal = false): array
    {
        $template = $internal
            ? InternalEvaluationRubric::template($vacancy->recruitmentCall->modality, $vacancy->category)
            : ExternalEvaluationRubric::template($vacancy->recruitmentCall->modality, $vacancy->category);
        $rubrics = $internal
            ? array_map(fn (array $rubric): array => array_diff_key($rubric, ['max' => true]), $template['rubrics'])
            : $template['rubrics'];

        return ['category' => $vacancy->category, 'version' => $template['version'], 'revision' => null, 'minimum' => $template['minimum'], 'rubrics' => $rubrics];
    }
}
