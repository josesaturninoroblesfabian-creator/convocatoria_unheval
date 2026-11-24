<?php

namespace Database\Factories;

use App\Models\JuryRecusal;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JuryRecusal>
 */
class JuryRecusalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'vacancy_id' => Vacancy::factory(),
            'juror_id' => User::factory()->juror(),
            'reason' => 'Conflicto de intereses por parentesco con un postulante.',
            'status' => 'SOLICITADA',
        ];
    }
}
