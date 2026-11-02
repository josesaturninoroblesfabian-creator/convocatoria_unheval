<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EvaluationFactory extends Factory
{
    public function definition(): array
    {
        return ['application_id' => Application::factory()->received(), 'user_id' => User::factory()->juror(), 'scores' => ['meritos' => 40, 'clase' => 30], 'total' => 70, 'notes' => 'Calificación de prueba con sustento.', 'closed_at' => now()];
    }
}
