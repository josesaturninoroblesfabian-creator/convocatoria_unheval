<?php

namespace App\Services;

class InternalEvaluationRubric
{
    public static function template(string $modality, string $category): ?array
    {
        $external = ExternalEvaluationRubric::template($modality, $category);
        if ($external === null) {
            return null;
        }

        $nombramiento = NombramientoRubric::template();
        $contract = $modality === 'CONTRATO';
        $minimum = $contract
            ? (str_starts_with($category, 'DC A') ? 26 : 24)
            : $nombramiento['minimums'][$category]['internal'];

        $descriptions = [
            ['Propósitos completos y muy bien definidos', 'Propósitos adecuados', 'Propósitos aceptables', 'Propósitos deficientes, incompletos o ausentes'],
            ['Relación óptima con el curso', 'Relación estrecha con el curso', 'Relación parcial con el curso', 'Relación deficiente con el curso'],
            ['Contenidos plenamente actualizados', 'Contenidos actualizados', 'Contenidos moderadamente actuales', 'Contenidos anticuados'],
            ['Calendario detallado y fechado', 'Calendario adecuado con fechas', 'Calendario general sin fechas', 'Sin calendario'],
            ['Bibliografía muy actual y pertinente', 'Bibliografía actual y pertinente', 'Mezcla de referencias actuales y antiguas', 'Bibliografía anticuada'],
        ];
        $rubrics = [];
        foreach ($nombramiento['internal'] as $section) {
            $rubric = array_intersect_key($section, array_flip(['key', 'name', 'max', 'guidance']));
            $rubric['items'] = [];
            foreach ($section['items'] as $index => $name) {
                $levels = [];
                foreach ($section['scale'] as $level => $points) {
                    $levels[] = ['points' => $points, 'description' => $section['key'] === 'silabo' ? $descriptions[$index][$level] : null];
                }
                $rubric['items'][] = ['key' => 'criterio_'.($index + 1), 'name' => $name, 'levels' => $levels];
            }
            $rubrics[$section['key']] = $rubric;
        }

        if ($contract) {
            $rubrics['silabo']['name'] = 'Sílabo o planeación didáctica';
            $rubrics['clase']['guidance'] = 'Exposición de hasta 30 minutos, seguida de preguntas. El tema se sortea con al menos 24 horas de anticipación. Si el postulante no presentó su sílabo, el sorteo se hace a partir de los otros sílabos o del de la escuela.';
            $rubrics['entrevista']['guidance'] = 'Entrevista virtual de hasta 15 minutos. Cada integrante califica por separado y entrega sus resultados a la presidencia de la comisión.';
        }

        return [
            'version' => $external['version'].'-anexo02',
            'modality' => $modality,
            'category' => $category,
            'minimum' => $minimum,
            'maximum' => 45,
            'source' => [
                'label' => $contract
                    ? 'R. C. U. 4480-2024-UNHEVAL · Anexo 02 y arts. 29–32'
                    : 'R. C. U. 2308-2024 y 3674-2024-UNHEVAL · Anexo 02 y arts. 32–37',
                'url' => str_replace($contract ? '#page=13' : '#page=17', $contract ? '#page=18' : '#page=21', $external['source']['url']),
            ],
            'progression' => 'La comisión evalúa los aspectos internos tras la aprobación de los aspectos externos.',
            'review' => $contract
                ? 'La presidencia de la comisión promedia las calificaciones individuales según el reglamento.'
                : 'El reglamento establece la evaluación presencial a cargo de la comisión.',
            'rubrics' => array_values($rubrics),
        ];
    }
}
