<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\RecruitmentCall;
use App\Models\User;
use App\Models\Vacancy;
use App\Services\ExternalEvaluationRubric;
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
                ->component('VacancyExternalEvaluation')
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
        $payload['rubrics']['formacion']['points']['doctor'] = 9;
        $payload['rubrics']['formacion']['note'] = 'Revisar constancia de reconocimiento.';
        $route = route('management.vacancies.external.update', $vacancy);

        $this->actingAs(User::factory()->manager()->create())
            ->put($route, $payload)->assertSessionHasErrors([
                'reference' => 'Indica la resolución o el documento aprobado que respalda los cambios de puntaje.',
            ]);
        $this->assertNull($vacancy->fresh()->external_evaluation);
        $this->assertDatabaseCount('audit_events', 0);

        $this->put($route, [...$payload, 'reference' => 'Resolución de prueba, anexo 01, numeral 1'])->assertSessionHas('success');
        $saved = $vacancy->fresh()->external_evaluation;
        $this->assertSame(36, $saved['minimum']);
        $this->assertSame(9, $saved['rubrics'][0]['items'][0]['points']);
        $this->assertSame('Revisar constancia de reconocimiento.', $saved['rubrics'][0]['note']);
        $this->assertNull($otherVacancy->fresh()->external_evaluation);
        $this->assertSame(['reference' => 8, 'configured' => 9], AuditEvent::sole()->metadata['adjustments']['formacion']['doctor']);

        $this->get(route('management.calls.show', $call))->assertInertia(fn (Assert $page) => $page
            ->where('configuredExternalVacancyIds', [$vacancy->id])
            ->missing('call.vacancies.0.external_evaluation')
        );
        $this->post(route('management.calls.transition', $call), ['status' => 'PUBLICADA'])->assertSessionHasErrors([
            'status' => 'Los aspectos externos por plaza están en configuración. La publicación requiere completar su flujo de evaluación.',
        ]);
    }

    public function test_template_rejects_extra_criteria_and_values_above_the_rubric_cap(): void
    {
        $vacancy = Vacancy::factory()->for(RecruitmentCall::factory()->draft())->create();
        $payload = $this->payload($vacancy);
        $payload['rubrics']['formacion']['points']['doctor'] = 11;
        $payload['rubrics']['idiomas']['points']['inventado'] = 0.5;

        $this->actingAs(User::factory()->manager()->create())
            ->put(route('management.vacancies.external.update', $vacancy), $payload)
            ->assertSessionHasErrors(['rubrics.formacion.points.doctor', 'rubrics.idiomas.points']);
        $this->assertNull($vacancy->fresh()->external_evaluation);
    }

    public function test_category_changes_reset_the_configuration_and_reject_an_old_open_form(): void
    {
        $vacancy = Vacancy::factory()->for(RecruitmentCall::factory()->draft())->create();
        $route = route('management.vacancies.external.update', $vacancy);
        $payload = $this->payload($vacancy);
        $this->actingAs(User::factory()->manager()->create())->put($route, $payload)->assertSessionHas('success');
        $this->put(route('management.vacancies.update', $vacancy), [
            ...$vacancy->only('faculty', 'school', 'airhsp', 'cap', 'dedication', 'weekly_hours', 'courses'),
            'category' => 'ASOCIADO',
        ])->assertSessionHas('success');

        $this->assertNull($vacancy->fresh()->external_evaluation);
        $this->assertDatabaseHas('audit_events', ['action' => 'ASPECTOS_EXTERNOS_REINICIADOS', 'subject_id' => $vacancy->id]);
        $this->put($route, $payload)->assertSessionHasErrors('category');
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

    private function payload(Vacancy $vacancy): array
    {
        $template = ExternalEvaluationRubric::template($vacancy->recruitmentCall->modality, $vacancy->category);
        $rubrics = [];
        foreach ($template['rubrics'] as $rubric) {
            $rubrics[$rubric['key']] = ['points' => array_column($rubric['items'], 'points', 'key')];
        }

        return ['category' => $vacancy->category, 'version' => $template['version'], 'rubrics' => $rubrics];
    }
}
