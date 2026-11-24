<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\RecruitmentCall;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Tests\TestCase;

class DeletionTest extends TestCase
{
    public function test_applicant_can_delete_their_own_draft_application(): void
    {
        $user = User::factory()->create();
        $call = RecruitmentCall::factory()->draft()->create();
        $vacancy = Vacancy::factory()->for($call)->create();
        $application = Application::factory()->create([
            'user_id' => $user->id,
            'recruitment_call_id' => $call->id,
            'vacancy_id' => $vacancy->id,
            'status' => 'BORRADOR',
        ]);

        $this->actingAs($user)->delete(route('applications.destroy', $application))
            ->assertRedirect(route('applications.index'));
        $this->assertDatabaseMissing('applications', ['id' => $application->id]);
    }

    public function test_manager_can_delete_a_draft_vacancy_with_its_drafts(): void
    {
        $manager = User::factory()->manager()->create();
        $call = RecruitmentCall::factory()->draft()->create();
        $vacancy = Vacancy::factory()->for($call)->create();
        $application = Application::factory()->create([
            'user_id' => User::factory()->create()->id,
            'recruitment_call_id' => $call->id,
            'vacancy_id' => $vacancy->id,
            'status' => 'BORRADOR',
        ]);

        $this->actingAs($manager)->delete(route('management.vacancies.destroy', $vacancy))
            ->assertRedirect(route('management.calls.show', $call));
        $this->assertDatabaseMissing('vacancies', ['id' => $vacancy->id]);
        $this->assertDatabaseMissing('applications', ['id' => $application->id]);
    }

    public function test_manager_cannot_delete_vacancy_with_submitted_applications(): void
    {
        $manager = User::factory()->manager()->create();
        $call = RecruitmentCall::factory()->draft()->create();
        $vacancy = Vacancy::factory()->for($call)->create();
        Application::factory()->received()->create([
            'recruitment_call_id' => $call->id,
            'vacancy_id' => $vacancy->id,
        ]);

        $this->actingAs($manager)->delete(route('management.vacancies.destroy', $vacancy))->assertConflict();
        $this->assertDatabaseHas('vacancies', ['id' => $vacancy->id]);
    }

    public function test_manager_can_delete_a_draft_call(): void
    {
        $manager = User::factory()->manager()->create();
        $call = RecruitmentCall::factory()->draft()->create();
        $vacancy = Vacancy::factory()->for($call)->create();

        $this->actingAs($manager)->delete(route('management.calls.destroy', $call))
            ->assertRedirect(route('management.calls'));
        $this->assertDatabaseMissing('recruitment_calls', ['id' => $call->id]);
        $this->assertDatabaseMissing('vacancies', ['id' => $vacancy->id]);
    }

    public function test_cleanup_demo_removes_only_draft_demo_calls(): void
    {
        $this->app['env'] = 'local';
        $this->withoutMiddleware(PreventRequestForgery::class);
        config(['recruitment.demo' => true]);
        $manager = User::factory()->manager()->create();
        $draftDemo = RecruitmentCall::factory()->draft()->create(['is_demo' => true]);
        Vacancy::factory()->for($draftDemo)->create();
        $publishedDemo = RecruitmentCall::factory()->create(['is_demo' => true, 'status' => 'PUBLICADA']);
        Vacancy::factory()->for($publishedDemo)->create();

        $this->actingAs($manager)->post(route('management.demo.cleanup'))->assertSessionHas('success');
        $this->assertDatabaseMissing('recruitment_calls', ['id' => $draftDemo->id]);
        $this->assertDatabaseHas('recruitment_calls', ['id' => $publishedDemo->id]);
    }
}
