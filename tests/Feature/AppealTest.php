<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\RecruitmentCall;
use App\Models\User;
use App\Models\Vacancy;
use Tests\TestCase;

class AppealTest extends TestCase
{
    private function submittedApplicationWithPublishedAptitude(): array
    {
        $applicant = User::factory()->create();
        $call = RecruitmentCall::factory()->create(['status' => 'EN_EVALUACION']);
        $vacancy = Vacancy::factory()->for($call)->create([
            'stage_publications' => ['aptitude' => ['published_at' => now()->subHour()->toIso8601String(), 'rows' => []]],
        ]);
        $application = Application::factory()->received()->create([
            'user_id' => $applicant->id,
            'recruitment_call_id' => $call->id,
            'vacancy_id' => $vacancy->id,
        ]);

        return [$applicant, $vacancy, $application];
    }

    public function test_applicant_can_file_a_reconsideracion(): void
    {
        $this->travelTo('2026-11-24 10:00:00');
        [$applicant, , $application] = $this->submittedApplicationWithPublishedAptitude();

        $this->actingAs($applicant)->post(route('appeals.store', $application), [
            'type' => 'reconsideracion',
            'stage' => 'aptitude',
            'grounds' => 'Considero que no se valoró correctamente mi título profesional.',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('appeals', [
            'application_id' => $application->id,
            'type' => 'reconsideracion',
            'stage' => 'aptitude',
            'status' => 'PENDIENTE',
        ]);
    }

    public function test_applicant_cannot_file_the_same_reconsideracion_twice(): void
    {
        $this->travelTo('2026-11-24 10:00:00');
        [$applicant, , $application] = $this->submittedApplicationWithPublishedAptitude();
        $payload = ['type' => 'reconsideracion', 'stage' => 'aptitude', 'grounds' => 'Fundamentos válidos para la impugnación.'];
        $this->actingAs($applicant)->post(route('appeals.store', $application), $payload)->assertSessionHas('success');

        $this->actingAs($applicant)->post(route('appeals.store', $application), $payload)->assertSessionHasErrors('appeal');
    }

    public function test_reconsideracion_rejects_filing_after_the_deadline(): void
    {
        $this->travelTo('2026-11-24 10:00:00');
        [$applicant, , $application] = $this->submittedApplicationWithPublishedAptitude();
        $this->travelTo('2026-11-26 10:00:00');

        $this->actingAs($applicant)->post(route('appeals.store', $application), [
            'type' => 'reconsideracion',
            'stage' => 'aptitude',
            'grounds' => 'Fundamentos presentados fuera del plazo.',
        ])->assertSessionHasErrors('appeal');
    }

    public function test_assigned_juror_resolves_reconsideracion_and_manager_resolves_apelacion(): void
    {
        $this->travelTo('2026-11-24 10:00:00');
        [$applicant, $vacancy, $application] = $this->submittedApplicationWithPublishedAptitude();
        $juror = User::factory()->juror()->create();
        $vacancy->jurors()->attach($juror);
        $manager = User::factory()->manager()->create();

        $this->actingAs($applicant)->post(route('appeals.store', $application), [
            'type' => 'reconsideracion',
            'stage' => 'aptitude',
            'grounds' => 'Revisión documental que no consideró mi experiencia docente.',
        ])->assertSessionHas('success');
        $appeal = $application->appeals()->where('type', 'reconsideracion')->sole();

        $this->actingAs($juror)->post(route('appeals.resolve', $appeal), [
            'decision' => 'No fundada: el sustento no acredita el requisito del perfil.',
        ])->assertSessionHas('success');
        $this->assertSame('RESUELTA', $appeal->fresh()->status);

        $this->actingAs($applicant)->post(route('appeals.store', $application), [
            'type' => 'apelacion',
            'stage' => 'aptitude',
            'grounds' => 'Apelo la decisión de la comisión por no valorar mis sustentos.',
        ])->assertSessionHas('success');
        $apelacion = $application->appeals()->where('type', 'apelacion')->sole();

        $this->actingAs($manager)->post(route('appeals.resolve', $apelacion), [
            'decision' => 'Fundada: se reabre la revisión del sustento de experiencia.',
        ])->assertSessionHas('success');
        $this->assertSame('RESUELTA', $apelacion->fresh()->status);
    }

    public function test_apelacion_requires_a_resolved_reconsideracion(): void
    {
        $this->travelTo('2026-11-24 10:00:00');
        [$applicant, , $application] = $this->submittedApplicationWithPublishedAptitude();

        $this->actingAs($applicant)->post(route('appeals.store', $application), [
            'type' => 'apelacion',
            'stage' => 'aptitude',
            'grounds' => 'Apelación directa sin reconsideración previa.',
        ])->assertSessionHasErrors('appeal');
    }
}
