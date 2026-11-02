<?php

namespace Database\Factories;

use App\Models\Application;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApplicationDocumentFactory extends Factory
{
    public function definition(): array
    {
        return ['application_id' => Application::factory(), 'requirement_key' => 'titulo', 'name' => 'sustento.pdf', 'path' => 'pruebas/sustento.pdf', 'mime' => 'application/pdf', 'size' => 1024, 'sha256' => str_repeat('a', 64)];
    }
}
