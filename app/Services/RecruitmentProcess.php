<?php

namespace App\Services;

/**
 * Referencia del proceso de concurso público según los reglamentos UNHEVAL.
 *
 * Centraliza el cronograma estándar de 11 hitos que publica la universidad
 * (R. C. U. 2308-2024 / 3674-2024 para nombramiento y R. C. U. 4480-2024 para
 * contrato) para que el gestor pueda cargarlo como punto de partida.
 */
class RecruitmentProcess
{
    /**
     * Hitos estándar del cronograma oficial. `label` es la actividad y `date`
     * queda vacío para que el gestor consigne las fechas aprobadas por Consejo.
     *
     * @return list<array{label: string, date: string}>
     */
    public static function standardSchedule(): array
    {
        return [
            ['label' => 'Convocatoria y difusión en el portal web institucional', 'date' => ''],
            ['label' => 'Inscripción y presentación de expedientes', 'date' => ''],
            ['label' => 'Envío de expedientes al Decanato de la Facultad', 'date' => ''],
            ['label' => 'Derivación de expedientes a la Comisión de Concurso', 'date' => ''],
            ['label' => 'Evaluación por la Comisión de Concurso', 'date' => ''],
            ['label' => 'Publicación de resultados', 'date' => ''],
            ['label' => 'Presentación y resolución de recursos impugnatorios', 'date' => ''],
            ['label' => 'Informe final de la Comisión a la Facultad', 'date' => ''],
            ['label' => 'Aprobación del informe por el Consejo de Facultad', 'date' => ''],
            ['label' => 'Remisión del acuerdo al Consejo Universitario', 'date' => ''],
            ['label' => 'Acuerdo del Consejo Universitario', 'date' => ''],
        ];
    }
}
