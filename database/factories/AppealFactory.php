<?php

namespace Database\Factories;

use App\Models\Appeal;
use App\Models\Application;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appeal>
 */
class AppealFactory extends Factory
{
    public function definition(): array
    {
        return [
            'application_id' => Application::factory(),
            'type' => 'reconsideracion',
            'stage' => 'aptitude',
            'grounds' => 'Fundamentos de la impugnación de prueba.',
            'status' => 'PENDIENTE',
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn () => ['status' => 'RESUELTA', 'decision' => 'Impugnación resuelta sin modificar el resultado.', 'resolved_at' => now()]);
    }
}
