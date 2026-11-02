<?php

namespace Database\Factories;

use App\Models\RecruitmentCall;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ApplicationFactory extends Factory
{
    public function definition(): array
    {
        return ['reference' => (string) Str::uuid(), 'user_id' => User::factory(), 'recruitment_call_id' => RecruitmentCall::factory(), 'vacancy_id' => fn (array $attributes) => Vacancy::factory()->create(['recruitment_call_id' => $attributes['recruitment_call_id']])->id, 'status' => 'BORRADOR'];
    }

    public function received(): static
    {
        return $this->state(fn () => ['status' => 'RECIBIDA', 'submitted_at' => now(), 'consented_at' => now(), 'applicant_snapshot' => ['name' => 'Postulante de prueba', 'email' => 'persona@example.test', 'document_number' => '12345678', 'phone' => '900000000']]);
    }
}
