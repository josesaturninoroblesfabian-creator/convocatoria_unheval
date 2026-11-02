<?php

namespace Database\Factories;

use App\Models\RecruitmentCall;
use Illuminate\Database\Eloquent\Factories\Factory;

class VacancyFactory extends Factory
{
    public function definition(): array
    {
        return ['recruitment_call_id' => RecruitmentCall::factory(), 'faculty' => 'Ciencias de la Educación', 'school' => 'Biología, Química y Ciencia del Ambiente', 'airhsp' => fake()->unique()->numerify('######'), 'cap' => '318', 'category' => 'AUXILIAR', 'dedication' => 'TIEMPO COMPLETO', 'weekly_hours' => 40, 'courses' => [['code' => '1103', 'name' => 'Metodología del Trabajo Universitario', 'ht' => 2, 'hp' => 4]], 'requirements' => [['key' => 'titulo', 'label' => 'Título profesional', 'required' => true]]];
    }
}
