<?php

namespace Tests\Feature;

use App\Models\RecruitmentCall;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VacancyManagementTest extends TestCase
{
    public function test_new_call_opens_the_vacancy_form_and_saves_both_records_together(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager)->get(route('management.calls.create'))->assertInertia(fn (Assert $page) => $page
            ->component('VacancyForm')->where('call', null)->where('vacancy', null)
        );
        $data = [...$this->profile(), 'modality' => 'CONTRATO', 'is_demo' => true, 'opens_at' => '2026-11-10T09:00', 'closes_at' => '2026-11-12T17:00'];
        $this->post(route('management.calls.first-vacancy'), $data)->assertSessionHasErrors('category');
        $this->assertDatabaseCount('recruitment_calls', 0);
        $this->assertDatabaseCount('vacancies', 0);

        $response = $this->post(route('management.calls.first-vacancy'), [...$data, 'category' => 'DC A2', 'status' => 'PUBLICADA']);
        $call = RecruitmentCall::sole();
        $vacancy = $call->vacancies()->sole();
        $response->assertRedirect(route('management.vacancies.edit', $vacancy));
        $this->assertSame('BORRADOR', $call->status);
        $this->assertSame('CONTRATO', $call->modality);
        $this->assertSame('Convocatoria docente · Turismo y Hotelería', $call->title);
        $this->assertSame('2026-11-10 09:00', $call->opens_at->format('Y-m-d H:i'));
        $this->assertSame([], $call->rubric);
        $this->assertSame('DC A2', $vacancy->category);
        $this->assertSame(16, $vacancy->weekly_hours);
        $this->assertSame([], $vacancy->requirements);
        $this->assertNull($vacancy->screening_rules);
        $this->get(route('management.vacancies.external.edit', $vacancy))
            ->assertInertia(fn (Assert $page) => $page
                ->where('template.modality', 'CONTRATO')
                ->where('template.category', 'DC A2')
                ->where('template.minimum', 18)
            );
        $callDetails = $call->only(['code', 'title', 'description', 'modality', 'opens_at', 'closes_at', 'schedule', 'rubric', 'is_demo']);
        $this->put(route('management.calls.update', $call), [...$callDetails, 'modality' => 'NOMBRAMIENTO'])
            ->assertSessionHasErrors('modality');
        $this->put(route('management.calls.update', $call), $callDetails)->assertSessionHas('success');
        $this->assertSame([], $call->fresh()->rubric);
        $this->get(route('management.vacancies.internal.edit', $vacancy))
            ->assertInertia(fn (Assert $page) => $page->where('template.minimum', 26));
    }

    public function test_new_call_continues_to_first_vacancy_then_to_its_own_evaluation_settings(): void
    {
        $manager = User::factory()->manager()->create();
        $data = RecruitmentCall::factory()->make()->only(['code', 'title', 'description', 'modality', 'opens_at', 'closes_at', 'schedule', 'rubric', 'is_demo']);
        $response = $this->actingAs($manager)->post(route('management.calls.store'), $data);
        $call = RecruitmentCall::where('code', $data['code'])->sole();
        $response->assertRedirect(route('management.vacancies.create', $call));
        $this->get(route('management.calls.edit', $call))->assertInertia(fn (Assert $page) => $page->where('hasVacancies', false));

        $response = $this->post(route('management.vacancies.store', $call), $this->profile());
        $vacancy = $call->vacancies()->sole();
        $response->assertRedirect(route('management.vacancies.edit', $vacancy));
        $this->get(route('management.vacancies.external.edit', $vacancy))
            ->assertInertia(fn (Assert $page) => $page
                ->where('vacancy.id', $vacancy->id)
                ->where('links.other', route('management.vacancies.internal.edit', $vacancy, false))
            );
    }

    public function test_manager_creates_a_public_profile_then_configures_requirements_separately(): void
    {
        $manager = User::factory()->manager()->create();
        $call = RecruitmentCall::factory()->draft()->create();
        $this->actingAs($manager)->get(route('management.vacancies.create', $call))
            ->assertInertia(fn (Assert $page) => $page->component('VacancyForm')->where('call.modality', 'NOMBRAMIENTO')->where('vacancy', null));

        $data = $this->profile();
        $this->post(route('management.vacancies.store', $call), $data)->assertSessionHas('success');
        $vacancy = $call->vacancies()->sole();
        $this->assertSame('000206', $vacancy->airhsp);
        $this->assertSame([], $vacancy->requirements);
        $this->assertNull($vacancy->screening_rules);
        $this->get(route('management.vacancies.preview', $vacancy))->assertInertia(fn (Assert $page) => $page
            ->component('Vacancy')
            ->where('preview', true)
            ->where('vacancy.professional_profile', $data['professional_profile'])
            ->where('vacancy.schedule', $data['schedule'])
        );
        $vacancy->jurors()->attach(User::factory()->juror()->create());
        $this->post(route('management.calls.transition', $call), ['status' => 'PUBLICADA'])->assertSessionHasErrors('status');

        $requirements = [['key' => 'titulo', 'label' => 'Título profesional', 'required' => true]];
        $this->put(route('management.screening', $vacancy), ['screening_rules' => ['enabled' => false], 'requirements' => $requirements])
            ->assertSessionHas('success');
        $this->put(route('management.vacancies.update', $vacancy), [...$data, 'description' => 'Carga actualizada.', 'requirements' => []])
            ->assertSessionHas('success');
        $this->assertSame($requirements, $vacancy->fresh()->requirements);
        $this->post(route('management.calls.transition', $call), ['status' => 'PUBLICADA'])->assertSessionHas('success');
        $this->get(route('vacancies.show', $vacancy))->assertInertia(fn (Assert $page) => $page
            ->where('vacancy.description', 'Carga actualizada.')
            ->where('vacancy.requirements', $requirements)
        );
        $this->put(route('management.vacancies.update', $vacancy), $data)->assertConflict();
    }

    public function test_contracted_classification_controls_hours_and_schedule_rejects_reversed_dates(): void
    {
        $call = RecruitmentCall::factory()->draft()->create(['modality' => 'CONTRATO']);
        $this->actingAs(User::factory()->manager()->create());
        $route = route('management.vacancies.store', $call);
        $this->post($route, $this->profile())->assertSessionHasErrors('category');
        $data = [...$this->profile(), 'category' => 'DC B2', 'weekly_hours' => 40];
        $badSchedule = $data['schedule'];
        $badSchedule[0]['ends_at'] = '2026-11-20T08:00';
        $this->post($route, [...$data, 'schedule' => $badSchedule])->assertSessionHasErrors('schedule.0.ends_at');
        $this->post($route, $data)->assertSessionHas('success');
        $vacancy = $call->vacancies()->sole();
        $this->assertSame('DC B2', $vacancy->category);
        $this->assertSame(16, $vacancy->weekly_hours);
        $this->assertSame(['weekly' => 16, 'teaching' => 8, 'other' => 8], $vacancy->contract_hours);
    }

    public function test_pdf_attachments_are_scoped_to_the_vacancy_and_are_public_only_after_publication(): void
    {
        Storage::fake('local');
        $call = RecruitmentCall::factory()->draft()->create();
        $manager = User::factory()->manager()->create();
        $applicant = User::factory()->create();
        $this->actingAs($manager)->post(route('management.vacancies.store', $call), $this->profile())->assertSessionHas('success');
        $vacancy = $call->vacancies()->sole();
        $upload = route('management.vacancies.documents.store', $vacancy);
        $this->actingAs($applicant)->post($upload)->assertForbidden();
        $this->actingAs($manager)->post($upload, [
            'name' => 'No es PDF',
            'file' => new UploadedFile(database_path('fixtures/no-es-pdf.txt'), 'texto.pdf', null, null, true),
        ])->assertSessionHasErrors('file');

        foreach (['Bases aprobadas de muestra', 'Anexo de muestra'] as $name) {
            $this->post($upload, [
                'name' => $name,
                'file' => new UploadedFile(database_path('fixtures/sustento-demo.pdf'), 'documento.pdf', 'application/pdf', null, true),
            ])->assertSessionHas('success');
        }
        $documents = $vacancy->fresh()->attached_documents;
        $this->assertCount(2, $documents);
        Storage::disk('local')->assertExists(array_column($documents, 'path'));
        $publicUrl = route('vacancies.documents.show', [$vacancy, $documents[0]['id']]);
        $this->get($publicUrl)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->delete(route('management.vacancies.documents.destroy', [$vacancy, $documents[1]['id']]))->assertSessionHas('success');
        Storage::disk('local')->assertMissing($documents[1]['path']);
        $this->actingAs($applicant)->get($publicUrl)->assertNotFound();

        $call->update(['status' => 'PUBLICADA']);
        $this->get($publicUrl)->assertOk();
        $this->get(route('vacancies.show', $vacancy))->assertInertia(fn (Assert $page) => $page
            ->has('vacancy.public_documents', 1)
            ->where('vacancy.public_documents.0.name', 'Bases aprobadas de muestra')
            ->missing('vacancy.attached_documents')
        );
        $this->get(route('portal'))->assertInertia(fn (Assert $page) => $page->has('vacancies.data.0.public_documents', 1));
        $this->actingAs($manager)->post($upload, [
            'name' => 'Otro PDF',
            'file' => new UploadedFile(database_path('fixtures/sustento-demo.pdf'), 'documento.pdf', 'application/pdf', null, true),
        ])->assertConflict();
    }

    private function profile(): array
    {
        return [
            'faculty' => 'Ciencias Administrativas y Turismo',
            'school' => 'Turismo y Hotelería',
            'airhsp' => '000206',
            'cap' => '200',
            'category' => 'AUXILIAR',
            'dedication' => 'TIEMPO COMPLETO',
            'weekly_hours' => 40,
            'description' => 'Plaza docente de demostración.',
            'professional_profile' => "Licenciado en Turismo y Hotelería\nGrado de Maestro o Doctor",
            'courses' => [['code' => '1203', 'name' => 'Idioma Inglés', 'ht' => 3, 'hp' => 6]],
            'schedule' => [['label' => 'Clase modelo', 'starts_at' => '2026-11-20T09:00', 'ends_at' => '2026-11-20T09:30', 'detail' => 'Actividad de demostración']],
        ];
    }
}
