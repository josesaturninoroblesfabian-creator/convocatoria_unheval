<?php

namespace Database\Seeders;

use App\Models\RecruitmentCall;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local') || ! config('recruitment.demo')) {
            throw new \RuntimeException('La demo requiere APP_ENV=local y RECRUITMENT_DEMO=true.');
        }
        DB::transaction(function () {
            $accounts = ['admin' => ['Administración de prueba', 'administrador', '90000001'], 'gestor' => ['Gestión de prueba', 'gestor', '90000002'], 'jurado' => ['Jurado 1 de prueba', 'jurado', '90000003'], 'jurado2' => ['Jurado 2 de prueba', 'jurado', '90000004'], 'jurado3' => ['Jurado 3 de prueba', 'jurado', '90000005'], 'postulante' => ['Postulante de prueba', 'postulante', '90000006']];
            $users = [];
            foreach ($accounts as $key => [$name,$role,$dni]) {
                $user = User::firstOrNew(['email' => $key.'@demo.test']);
                $user->fill(['name' => $name, 'password' => $dni, 'document_number' => $dni, 'phone' => '900000000']);
                $user->forceFill(['role' => $role, 'email_verified_at' => now()])->save();
                $users[$key] = $user;
            }
            $documents = [['name' => 'Cuadro de plazas de referencia', 'path' => '/documents/cuadro-plazas-2026-iv.pdf'], ['name' => 'Reglamento de referencia', 'path' => '/documents/reglamento-nombramiento.pdf'], ['name' => 'Cronograma histórico de referencia', 'path' => '/documents/cronograma-2026-iv.pdf']];
            $call = RecruitmentCall::firstOrCreate(['code' => 'DEMO-NOMBRAMIENTO-2026'], ['title' => 'Concurso docente · Caso práctico con plazas UNHEVAL', 'description' => 'Prueba del formulario estructurado con los perfiles de Educación y Turismo del cuadro 2026-IV. Las fechas y los puntajes de la demostración no son oficiales.', 'status' => 'PUBLICADA', 'is_demo' => true, 'opens_at' => now()->subDay(), 'closes_at' => now()->addDays(14)->endOfDay(), 'schedule' => [['label' => 'Inscripción digital de prueba', 'date' => now()->format('d/m/Y').' al '.now()->addDays(14)->format('d/m/Y')], ['label' => 'Revisión de requisitos y sustentos', 'date' => 'Después del cierre de inscripción'], ['label' => 'Resultados de prueba', 'date' => 'Después de cerrar las calificaciones de los jurados']], 'rubric' => [['key' => 'formacion', 'name' => 'Formación (prueba)', 'max' => 10], ['key' => 'experiencia', 'name' => 'Experiencia (prueba)', 'max' => 10], ['key' => 'capacitacion', 'name' => 'Capacitación (prueba)', 'max' => 5], ['key' => 'clase', 'name' => 'Clase magistral (prueba)', 'max' => 50], ['key' => 'entrevista', 'name' => 'Entrevista (prueba)', 'max' => 25]], 'public_documents' => $documents]);
            $education = [['key' => 'titulo', 'label' => 'Título de Licenciado en Educación, especialidad de Biología, Química y Ciencia del Ambiente, o Biología y Química', 'required' => true], ['key' => 'grado', 'label' => 'Grado de Magíster', 'required' => true], ['key' => 'enfoque', 'label' => 'Conocimiento y dominio del enfoque curricular por competencias', 'required' => true], ['key' => 'disciplina', 'label' => 'Manejo disciplinar de Biología, Química y Ciencias del Ambiente', 'required' => true], ['key' => 'experiencia', 'label' => 'Cinco (5) años de ejercicio profesional', 'required' => true], ['key' => 'habilitacion', 'label' => 'Pertenecer al Colegio Profesional y estar habilitado', 'required' => true], ['key' => 'cv', 'label' => 'Currículo documentado para evaluación de méritos (archivo de prueba)', 'required' => false]];
            $tourism = [['key' => 'titulo', 'label' => 'Título de Licenciado en Administración Turística y Hotelera y/o Licenciado en Turismo y Hotelería', 'required' => true], ['key' => 'grado', 'label' => 'Grado de Maestro y/o Doctor', 'required' => true], ['key' => 'experiencia', 'label' => 'Cinco (5) años de ejercicio profesional', 'required' => true], ['key' => 'habilitacion', 'label' => 'Pertenecer al Colegio Profesional y estar habilitado', 'required' => true], ['key' => 'cv', 'label' => 'Currículo documentado para evaluación de méritos (archivo de prueba)', 'required' => false]];
            $sources = [['airhsp' => '000078', 'cap' => '318', 'faculty' => 'Ciencias de la Educación', 'school' => 'Biología, Química y Ciencia del Ambiente', 'category' => 'AUXILIAR', 'dedication' => 'TIEMPO COMPLETO', 'weekly_hours' => 40, 'courses' => [['code' => '1103', 'name' => 'Metodología del Trabajo Universitario', 'ht' => 2, 'hp' => 4], ['code' => '3103', 'name' => 'Microbiología y Parasitología', 'ht' => 2, 'hp' => 4], ['code' => '4201', 'name' => 'Genética', 'ht' => 2, 'hp' => 4]], 'requirements' => $education], ['airhsp' => '000206', 'cap' => '200', 'faculty' => 'Ciencias Administrativas y Turismo', 'school' => 'Turismo y Hotelería', 'category' => 'AUXILIAR', 'dedication' => 'TIEMPO COMPLETO', 'weekly_hours' => 40, 'courses' => [['code' => '1203', 'name' => 'Idioma Inglés', 'ht' => 3, 'hp' => 6], ['code' => '1207', 'name' => 'Taller de Expresiones Artísticas', 'ht' => 0, 'hp' => 4], ['code' => '3208', 'name' => 'Taller de Turismo Alternativo', 'ht' => 0, 'hp' => 4], ['code' => '4206', 'name' => 'Marketing Digital', 'ht' => 2, 'hp' => 2]], 'requirements' => $tourism]];

            $rules = ['enabled' => true, 'minimum_degree' => 'maestro', 'minimum_experience_months' => 60, 'experience_type' => 'toda', 'count_from_bachelor' => true, 'minimum_training_hours' => 0, 'degree_points' => ['titulo' => 2, 'maestro' => 6, 'doctor' => 8], 'experience_points_per_year' => 1, 'experience_cap' => 10, 'training_hours_per_point' => 40, 'training_cap' => 5, 'fields' => []];
            foreach ($sources as $source) {
                $vacancy = $call->vacancies()->firstOrCreate(['airhsp' => $source['airhsp']], [...$source, 'screening_rules' => $rules]);
                if (! $vacancy->jurors()->exists()) {
                    $vacancy->jurors()->sync([$users['jurado']->id, $users['jurado2']->id, $users['jurado3']->id]);
                }
            }
        });
    }
}
