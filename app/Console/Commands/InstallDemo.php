<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\AuditEvent;
use App\Models\Evaluation;
use App\Models\RecruitmentCall;
use App\Models\Vacancy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InstallDemo extends Command
{
    protected $signature = 'unheval:demo {--reset : Elimina únicamente los procesos de ejemplo y sus expedientes de cuentas demo antes de recrearlos}';

    protected $description = 'Prepara una demostración limpia con dos plazas y contraseñas iguales al DNI';

    public function handle(): int
    {
        if (! app()->environment('local') || ! config('recruitment.demo')) {
            $this->error('Solo está disponible con APP_ENV=local y RECRUITMENT_DEMO=true.');

            return self::FAILURE;
        }
        if ($this->option('reset')) {
            $calls = RecruitmentCall::whereIn('code', ['NOMBRAMIENTO-2026-IV', 'DEMO-NOMBRAMIENTO-2026', 'DEMO-EVALUACION-2026'])->get();
            if (Application::whereIn('recruitment_call_id', $calls->modelKeys())->whereHas('user', fn ($query) => $query->whereNotIn('email', ['admin@demo.test', 'gestor@demo.test', 'jurado@demo.test', 'jurado2@demo.test', 'jurado3@demo.test', 'postulante@demo.test']))->exists()) {
                $this->error('Se encontraron postulantes ajenos a las cuentas de ejemplo. No se eliminó ningún dato.');

                return self::FAILURE;
            }
            DB::transaction(function () use ($calls) {
                $applicationIds = Application::whereIn('recruitment_call_id', $calls->modelKeys())->pluck('id');
                $vacancyIds = Vacancy::whereIn('recruitment_call_id', $calls->modelKeys())->pluck('id');
                $paths = ApplicationDocument::whereIn('application_id', $applicationIds)->pluck('path')->unique()->all();
                Evaluation::whereIn('application_id', $applicationIds)->delete();
                ApplicationDocument::whereIn('application_id', $applicationIds)->delete();
                Application::whereIn('id', $applicationIds)->delete();
                DB::table('jury_assignments')->whereIn('vacancy_id', $vacancyIds)->delete();
                Vacancy::whereIn('id', $vacancyIds)->delete();
                RecruitmentCall::whereIn('id', $calls->modelKeys())->delete();
                AuditEvent::where(fn ($query) => $query->where('subject_type', Application::class)->whereIn('subject_id', $applicationIds))->orWhere(fn ($query) => $query->where('subject_type', Vacancy::class)->whereIn('subject_id', $vacancyIds))->orWhere(fn ($query) => $query->where('subject_type', RecruitmentCall::class)->whereIn('subject_id', $calls->modelKeys()))->delete();
                DB::afterCommit(fn () => Storage::disk('local')->delete($paths));
            });
        }
        $this->call('db:seed', ['--class' => 'DemoSeeder']);
        $this->table(['Cuenta', 'Correo', 'Contraseña (DNI de prueba)'], [['Administrador', 'admin@demo.test', '90000001'], ['Gestor', 'gestor@demo.test', '90000002'], ['Jurado 1', 'jurado@demo.test', '90000003'], ['Jurado 2', 'jurado2@demo.test', '90000004'], ['Jurado 3', 'jurado3@demo.test', '90000005'], ['Postulante', 'postulante@demo.test', '90000006']]);
        $this->info('Dos plazas de ejemplo. Los expedientes se crean al postular; no se inventan postulantes ni resultados.');

        return self::SUCCESS;
    }
}
