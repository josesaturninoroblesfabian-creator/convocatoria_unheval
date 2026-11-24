<?php

namespace Tests\Feature;

use App\Models\RecruitmentCall;
use App\Models\User;
use App\Models\Vacancy;
use Tests\TestCase;

class RecusalTest extends TestCase
{
    public function test_juror_can_request_recusal_and_manager_approves_with_replacement(): void
    {
        $call = RecruitmentCall::factory()->create();
        $vacancy = Vacancy::factory()->for($call)->create();
        $juror = User::factory()->juror()->create();
        $accesitario = User::factory()->juror()->create();
        $manager = User::factory()->manager()->create();
        $vacancy->jurors()->attach([$juror->id => ['role' => 'titular'], $accesitario->id => ['role' => 'accesitario']]);

        $this->actingAs($juror)->post(route('jury.recusals.store', $vacancy), [
            'reason' => 'Conflicto de intereses por parentesco con un postulante.',
        ])->assertSessionHas('success');
        $recusal = $vacancy->recusals()->sole();
        $this->assertSame('SOLICITADA', $recusal->status);

        $this->actingAs($manager)->post(route('jury.recusals.resolve', $recusal), [
            'approve' => true,
            'decision' => 'Abstención aprobada por conflicto de intereses.',
        ])->assertSessionHas('success');

        $this->assertSame('APROBADA', $recusal->fresh()->status);
        $this->assertFalse($vacancy->fresh()->jurors()->where('users.id', $juror->id)->exists());
        $this->assertSame('titular', $vacancy->fresh()->jurors()->where('users.id', $accesitario->id)->first()->pivot->role);
    }

    public function test_manager_can_reject_a_recusal_without_changing_the_commission(): void
    {
        $call = RecruitmentCall::factory()->create();
        $vacancy = Vacancy::factory()->for($call)->create();
        $juror = User::factory()->juror()->create();
        $manager = User::factory()->manager()->create();
        $vacancy->jurors()->attach($juror->id);

        $this->actingAs($juror)->post(route('jury.recusals.store', $vacancy), [
            'reason' => 'Solicitud de abstención por razones personales.',
        ])->assertSessionHas('success');
        $recusal = $vacancy->recusals()->sole();

        $this->actingAs($manager)->post(route('jury.recusals.resolve', $recusal), [
            'approve' => false,
            'decision' => 'Abstención rechazada por no acreditar conflicto.',
        ])->assertSessionHas('success');

        $this->assertSame('RECHAZADA', $recusal->fresh()->status);
        $this->assertTrue($vacancy->fresh()->jurors()->where('users.id', $juror->id)->exists());
    }
}
