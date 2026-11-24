<?php

namespace Tests\Feature;

use App\Models\RecruitmentCall;
use App\Models\User;
use App\Models\Vacancy;
use App\Services\ActaService;
use Tests\TestCase;

class ActaTest extends TestCase
{
    public function test_acta_generates_with_hash_and_closes_when_all_jurors_sign(): void
    {
        $call = RecruitmentCall::factory()->create(['status' => 'EN_EVALUACION']);
        $vacancy = Vacancy::factory()->for($call)->create();
        $juror1 = User::factory()->juror()->create();
        $juror2 = User::factory()->juror()->create();
        $vacancy->jurors()->attach([$juror1->id, $juror2->id]);

        $acta = app(ActaService::class)->generate($vacancy, 'aptitude', ['rows' => []]);

        $this->assertSame(64, strlen($acta->content_hash));
        $this->assertSame('ABIERTA', $acta->status);

        $this->actingAs($juror1)->post(route('actas.sign', $acta))->assertSessionHas('success');
        $this->assertSame('ABIERTA', $acta->fresh()->status);

        $this->actingAs($juror2)->post(route('actas.sign', $acta))->assertSessionHas('success');
        $this->assertSame('FIRMADA', $acta->fresh()->status);
        $this->assertNotNull($acta->fresh()->signed_at);
    }

    public function test_non_juror_cannot_sign_an_acta(): void
    {
        $call = RecruitmentCall::factory()->create();
        $vacancy = Vacancy::factory()->for($call)->create();
        $acta = app(ActaService::class)->generate($vacancy, 'final', ['rows' => []]);
        $outsider = User::factory()->juror()->create();

        $this->actingAs($outsider)->post(route('actas.sign', $acta))->assertNotFound();
    }
}
