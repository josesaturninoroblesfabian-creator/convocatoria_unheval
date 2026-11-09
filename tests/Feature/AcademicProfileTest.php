<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AcademicProfileTest extends TestCase
{
    public function test_applicant_can_store_and_clear_their_own_academic_credentials(): void
    {
        $applicant = User::factory()->create();
        $otherApplicant = User::factory()->create();
        $credential = $this->credential();

        $this->actingAs($applicant)
            ->from(route('profile'))
            ->put(route('profile.academic.update'), ['credentials' => [$credential], 'user_id' => $otherApplicant->id])
            ->assertRedirect(route('profile'))
            ->assertSessionHas('success');
        $this->get(route('profile'))->assertInertia(fn (Assert $page) => $page
            ->component('Profile')
            ->where('academicCredentials', [$credential])
            ->missing('auth.user.academic_credentials')
        );
        $this->assertNull($otherApplicant->fresh()->academic_credentials);
        $this->assertStringNotContainsString($credential['name'], $applicant->fresh()->getRawOriginal('academic_credentials'));

        $this->put(route('profile.academic.update'), ['credentials' => []])->assertSessionHas('success');
        $this->assertSame([], $applicant->fresh()->academic_credentials);
    }

    public function test_staff_cannot_save_academic_credentials(): void
    {
        $juror = User::factory()->juror()->create();

        $this->actingAs($juror)
            ->put(route('profile.academic.update'), ['credentials' => [$this->credential()]])
            ->assertForbidden();
        $this->assertNull($juror->fresh()->academic_credentials);
    }

    public function test_future_dates_and_duplicate_credential_ids_are_rejected(): void
    {
        $applicant = User::factory()->create();
        $credential = $this->credential();

        $this->actingAs($applicant)
            ->put(route('profile.academic.update'), ['credentials' => [
                $credential,
                [...$credential, 'awarded_at' => now()->addDay()->toDateString()],
            ]])
            ->assertSessionHasErrors(['credentials.1.id', 'credentials.1.awarded_at']);
        $this->assertNull($applicant->fresh()->academic_credentials);
    }

    public function test_profile_credentials_are_available_to_copy_into_the_owners_open_draft(): void
    {
        $previousCredential = $this->credential();
        $application = Application::factory()->create(['form_data' => ['credentials' => [$previousCredential]]]);
        $application->vacancy->update(['screening_rules' => [
            'enabled' => true, 'academic_credentials' => true, 'minimum_degree' => 'maestro',
            'minimum_experience_months' => 60, 'experience_type' => 'toda', 'count_from_bachelor' => true,
            'minimum_training_hours' => 0, 'degree_points' => ['titulo' => 2, 'maestro' => 6, 'doctor' => 8],
            'experience_points_per_year' => 1, 'experience_cap' => 10, 'training_hours_per_point' => 40,
            'training_cap' => 5, 'fields' => [],
        ]]);
        $newCredential = [...$this->credential(), 'name' => 'Doctorado de prueba', 'kind' => 'doctor'];
        $institute = [...$this->credential(), 'name' => 'Título de instituto de prueba', 'kind' => 'instituto'];

        $this->actingAs($application->user)
            ->put(route('profile.academic.update'), ['credentials' => [$newCredential, $institute]])
            ->assertSessionHas('success');
        $this->assertSame([$newCredential, $institute], $application->user->fresh()->academic_credentials);
        $this->get(route('applications.show', $application))->assertInertia(fn (Assert $page) => $page
            ->where('profileCredentials', [$newCredential])
            ->where('application.form_data.credentials', [$previousCredential])
        );
        $application->update(['status' => 'RECIBIDA']);
        $this->get(route('applications.show', $application))->assertInertia(fn (Assert $page) => $page
            ->where('profileCredentials', [])
            ->where('application.form_data.credentials', [$previousCredential])
        );
        $this->actingAs(User::factory()->create())->get(route('applications.show', $application))->assertNotFound();
    }

    private function credential(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'kind' => 'titulo',
            'name' => 'Título de prueba',
            'institution' => 'Universidad de prueba',
            'awarded_at' => '2012-01-01',
        ];
    }
}
