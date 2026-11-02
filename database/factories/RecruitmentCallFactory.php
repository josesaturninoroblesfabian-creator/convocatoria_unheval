<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class RecruitmentCallFactory extends Factory
{
    public function definition(): array
    {
        return ['code' => 'DEMO-'.fake()->unique()->numerify('#####'), 'title' => 'Concurso de demostración', 'description' => 'Proceso para pruebas del sistema.', 'modality' => 'NOMBRAMIENTO', 'status' => 'PUBLICADA', 'is_demo' => true, 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay(), 'schedule' => [['label' => 'Inscripción', 'date' => 'Fechas de prueba']], 'rubric' => [['key' => 'meritos', 'name' => 'Méritos de prueba', 'max' => 55], ['key' => 'clase', 'name' => 'Clase de prueba', 'max' => 45]]];
    }

    public function evaluating(): static
    {
        return $this->state(fn () => ['status' => 'EN_EVALUACION', 'opens_at' => now()->subDays(5), 'closes_at' => now()->subDay()]);
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => 'BORRADOR']);
    }
}
