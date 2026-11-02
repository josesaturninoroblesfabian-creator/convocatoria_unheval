<?php

namespace Database\Factories;

use App\Models\RecruitmentCall;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuditEventFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory()->admin(), 'action' => 'CONVOCATORIA_CREADA', 'subject_type' => RecruitmentCall::class, 'subject_id' => RecruitmentCall::factory(), 'metadata' => []];
    }
}
