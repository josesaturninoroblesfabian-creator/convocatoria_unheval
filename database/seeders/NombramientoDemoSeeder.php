<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\AuditEvent;
use App\Models\RecruitmentCall;
use App\Models\User;
use App\Models\Vacancy;
use App\Services\ApplicantScreening;
use App\Services\ApplicationWorkflow;
use App\Services\NombramientoRubric;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class NombramientoDemoSeeder extends Seeder
{
    private const CODE = 'DEMO-NOMBRAMIENTO-EXPEDIENTE';

    private const OPEN_CODE = 'DEMO-NOMBRAMIENTO-INSCRIPCION';

    private const APPLICANT_EMAIL = 'expediente@demo.test';

    private const APPLICANT_NAME = 'Postulante del expediente ficticio';

    private const APPLICANT_DNI = '90000007';

    private const DEMO_PHONE = '900000000';

    public function run(ApplicantScreening $screening, ApplicationWorkflow $workflow): void
    {
        if (! app()->environment('local') || ! config('recruitment.demo')) {
            throw new RuntimeException('El expediente de práctica requiere APP_ENV=local y RECRUITMENT_DEMO=true.');
        }

        $fixture = file_get_contents(database_path('fixtures/sustento-demo.pdf'));
        if ($fixture === false || ! str_starts_with($fixture, '%PDF-')) {
            throw new RuntimeException('No se pudo leer el PDF de muestra del expediente.');
        }

        $paths = [];
        try {
            [$application, $openVacancy] = DB::transaction(function () use ($screening, $workflow, $fixture, &$paths): array {
                $today = CarbonImmutable::today();
                $openCall = $this->createCall($today, self::OPEN_CODE);
                if ($openCall->wasRecentlyCreated) {
                    $openVacancy = $this->createVacancy($openCall);
                    $this->assignJurors($openVacancy);
                } else {
                    $openVacancy = $openCall->vacancies()->firstOrFail();
                }

                $call = $this->createCall($today, self::CODE);
                if (! $call->wasRecentlyCreated) {
                    return [$call->applications()->firstOrFail(), $openVacancy];
                }

                $vacancy = $this->createVacancy($call);
                $this->assignJurors($vacancy);
                $this->demoUser('gestor@demo.test', 'Gestión de prueba', 'gestor', '90000002');
                $formData = $this->formData($today);
                $applicant = $this->demoUser(self::APPLICANT_EMAIL, self::APPLICANT_NAME, 'postulante', self::APPLICANT_DNI, $formData['credentials']);
                if ($applicant->name !== self::APPLICANT_NAME || $applicant->phone !== self::DEMO_PHONE) {
                    throw new RuntimeException('El perfil de la cuenta reservada para la práctica fue modificado.');
                }
                $application = $applicant->applications()->make([
                    'reference' => (string) Str::uuid(),
                    'status' => 'BORRADOR',
                    'statement' => 'Expediente ficticio para practicar la revisión de un concurso docente.',
                    'consented_at' => now(),
                    'form_data' => $formData,
                ]);
                $application->vacancy()->associate($vacancy);
                $application->recruitmentCall()->associate($call);
                $application->save();

                $this->attachDocuments($application, $screening, $fixture, $paths);
                $workflow->submit($application, $applicant, notifyApplicant: false);
                $application->refresh();

                $call->update([
                    'status' => 'EN_EVALUACION',
                    'closes_at' => $application->submitted_at,
                    'schedule' => $this->schedule($today, CarbonImmutable::parse($application->submitted_at)),
                ]);
                AuditEvent::record('CASO_PRACTICO_PREPARADO', $call, ['application_id' => $application->id]);

                return [$application, $openVacancy];
            });
        } catch (Throwable $error) {
            if ($paths !== []) {
                Storage::disk('local')->delete($paths);
            }

            throw $error;
        }

        $this->command?->info('Casos prácticos disponibles. Las convocatorias y expedientes existentes conservan su estado.');
        $this->command?->line('Plaza para practicar una postulación nueva: '.route('vacancies.show', $openVacancy, false));
        $this->command?->line('Convocatoria: '.route('management.calls.show', $application->recruitment_call_id, false));
        $this->command?->line('Postulación: '.route('applications.show', $application, false));
        $this->command?->line('Revisión del jurado: '.route('review.show', $application, false));
        $this->command?->line('Cuenta ficticia: '.self::APPLICANT_EMAIL.' · DNI de prueba: '.self::APPLICANT_DNI);
    }

    private function assignJurors(Vacancy $vacancy): void
    {
        $vacancy->jurors()->attach([
            $this->demoUser('jurado@demo.test', 'Jurado 1 de prueba', 'jurado', '90000003')->id,
            $this->demoUser('jurado2@demo.test', 'Jurado 2 de prueba', 'jurado', '90000004')->id,
            $this->demoUser('jurado3@demo.test', 'Jurado 3 de prueba', 'jurado', '90000005')->id,
        ]);
    }

    private function createCall(CarbonImmutable $today, string $code): RecruitmentCall
    {
        $template = NombramientoRubric::template();
        $openForPractice = $code === self::OPEN_CODE;
        $initialClose = $today->addDays($openForPractice ? 14 : 0)->endOfDay();

        return RecruitmentCall::firstOrCreate(['code' => $code], [
            'title' => $openForPractice
                ? 'Práctica de inscripción · Turismo y Hotelería'
                : 'Práctica de evaluación · Turismo y Hotelería',
            'description' => $openForPractice
                ? 'Convocatoria ficticia abierta para preparar y enviar un expediente de prueba.'
                : 'Convocatoria ficticia con un expediente recibido para practicar la revisión del jurado.',
            'modality' => 'NOMBRAMIENTO',
            'status' => 'PUBLICADA',
            'is_demo' => true,
            'opens_at' => $today->subDay(),
            'closes_at' => $initialClose,
            'schedule' => $this->schedule($today, $initialClose),
            'evaluation_template' => $template,
            'rubric' => array_map(
                fn (array $criterion): array => array_intersect_key($criterion, ['key' => true, 'name' => true, 'max' => true]),
                [...$template['external'], ...$template['internal']],
            ),
            'public_documents' => [
                ['name' => 'Reglamento de nombramiento de referencia', 'path' => '/documents/reglamento-nombramiento.pdf'],
                ['name' => 'Cuadro de plazas 2026-IV de referencia', 'path' => '/documents/cuadro-plazas-2026-iv.pdf'],
                ['name' => 'Cronograma histórico 2026-IV', 'path' => '/documents/cronograma-2026-iv.pdf'],
            ],
        ]);
    }

    private function schedule(CarbonImmutable $today, CarbonImmutable $closesAt): array
    {
        return [
            ['label' => 'Inscripción digital de prueba', 'date' => $today->subDay()->format('d/m/Y H:i').' al '.$closesAt->format('d/m/Y H:i:s')],
            ['label' => 'Aptitud y evaluación externa', 'date' => 'Después del cierre de inscripción'],
            ['label' => 'Evaluación interna', 'date' => 'Al superar el mínimo de la hoja externa'],
            ['label' => 'Resultados de prueba', 'date' => 'Después de consolidar las evaluaciones'],
        ];
    }

    private function createVacancy(RecruitmentCall $call): Vacancy
    {
        return $call->vacancies()->create([
            'airhsp' => '000206',
            'cap' => '200',
            'faculty' => 'Ciencias Administrativas y Turismo',
            'school' => 'Turismo y Hotelería',
            'category' => 'AUXILIAR',
            'dedication' => 'TIEMPO COMPLETO',
            'weekly_hours' => 40,
            'courses' => [
                ['code' => '1203', 'name' => 'Idioma Inglés', 'ht' => 3, 'hp' => 6],
                ['code' => '1207', 'name' => 'Taller de Expresiones Artísticas', 'ht' => 0, 'hp' => 4],
                ['code' => '3208', 'name' => 'Taller de Turismo Alternativo', 'ht' => 0, 'hp' => 4],
                ['code' => '4206', 'name' => 'Marketing Digital', 'ht' => 2, 'hp' => 2],
            ],
            'requirements' => [
                ['key' => 'solicitud', 'label' => 'Solicitud de inscripción de muestra', 'required' => true],
                ['key' => 'identidad', 'label' => 'Documento de identidad de muestra', 'required' => true],
                ['key' => 'declaraciones', 'label' => 'Declaraciones juradas de muestra', 'required' => true],
                ['key' => 'titulo', 'label' => 'Título de Licenciado en Administración Turística y Hotelera y/o Licenciado en Turismo y Hotelería', 'required' => true],
                ['key' => 'grado', 'label' => 'Grado de Maestro y/o Doctor', 'required' => true],
                ['key' => 'experiencia', 'label' => 'Cinco años de ejercicio profesional acreditados desde el bachiller', 'required' => true],
                ['key' => 'habilitacion', 'label' => 'Pertenecer al Colegio Profesional y estar habilitado', 'required' => true],
                ['key' => 'silabo', 'label' => 'Material de muestra para revisión de sílabo', 'required' => false],
            ],
            'screening_rules' => [
                'enabled' => true,
                'academic_credentials' => true,
                'allow_residency' => false,
                'minimum_degree' => 'maestro',
                'minimum_experience_months' => 60,
                'experience_type' => 'toda',
                'count_from_bachelor' => true,
                'minimum_training_hours' => 0,
                'degree_points' => ['titulo' => 2, 'bachiller' => 1, 'maestro' => 6, 'doctor' => 8, 'segunda_especialidad' => 6],
                'experience_points_per_year' => 0,
                'experience_cap' => 0,
                'training_hours_per_point' => 1,
                'training_cap' => 0,
                'fields' => [],
            ],
        ]);
    }

    private function formData(CarbonImmutable $today): array
    {
        $year = $today->startOfYear();

        return [
            'credentials' => [
                $this->credential('bachiller', 'Bachiller en Turismo y Hotelería', $year->subYears(11)),
                $this->credential('titulo', 'Licenciado en Turismo y Hotelería', $year->subYears(10)),
                $this->credential('maestro', 'Maestría en Gestión Turística', $year->subYears(8)),
                $this->credential('doctor', 'Doctorado en Gestión', $year->subYears(2)),
            ],
            'experience' => [[
                'id' => (string) Str::uuid(),
                'institution' => 'Universidad de Práctica (ficticia)',
                'position' => 'Docente universitario de turismo',
                'type' => 'docente',
                'start_date' => $year->subYears(6)->toDateString(),
                'end_date' => $year->subDay()->toDateString(),
            ]],
            'training' => [[
                'id' => (string) Str::uuid(),
                'name' => 'Diplomado de muestra en Gestión Turística',
                'institution' => 'Universidad de Práctica (ficticia)',
                'date' => $year->subYear()->toDateString(),
                'hours' => 160,
            ]],
            'answers' => [],
        ];
    }

    private function credential(string $kind, string $name, CarbonImmutable $date): array
    {
        return [
            'id' => (string) Str::uuid(),
            'kind' => $kind,
            'name' => $name,
            'institution' => 'Universidad de Práctica (ficticia)',
            'awarded_at' => $date->toDateString(),
        ];
    }

    private function demoUser(string $email, string $name, string $role, string $dni, ?array $credentials = null): User
    {
        $user = User::firstOrCreate(['email' => $email], [
            'name' => $name,
            'role' => $role,
            'password' => $dni,
            'document_number' => $dni,
            'phone' => self::DEMO_PHONE,
            'email_verified_at' => now(),
            ...($credentials === null ? [] : ['academic_credentials' => $credentials]),
        ]);
        if ($user->role !== $role || $user->document_number !== $dni) {
            throw new RuntimeException('La cuenta '.$email.' no coincide con la identidad de prueba reservada.');
        }

        return $user;
    }

    private function attachDocuments(Application $application, ApplicantScreening $screening, string $fixture, array &$paths): void
    {
        $disk = Storage::disk('local');
        foreach ($screening->documents($application) as $requirement) {
            $path = 'expedientes/'.$application->id.'/'.Str::uuid().'.pdf';
            $paths[] = $path;
            if (! $disk->put($path, $fixture)) {
                throw new RuntimeException('No se pudo guardar un sustento de muestra.');
            }
            $application->documents()->create([
                'requirement_key' => $requirement['key'],
                'name' => 'Muestra · '.Str::limit($requirement['label'], 180, '').'.pdf',
                'path' => $path,
                'mime' => 'application/pdf',
                'size' => strlen($fixture),
                'sha256' => hash('sha256', $fixture),
            ]);
        }
    }
}
