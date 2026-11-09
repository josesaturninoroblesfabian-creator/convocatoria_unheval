<?php

namespace App\Services;

class NombramientoRubric
{
    /**
     * @return array{
     *     source: array{name: string, path: string, approval: string, annexes: string},
     *     external: list<array{key: string, name: string, max: int|float, guidance: string}>,
     *     internal: list<array{key: string, name: string, max: int|float, guidance: string, scale: list<int|float>, items: list<string>}>,
     *     minimums: array<string, array{external: int, internal: int, total: int}>
     * }
     */
    public static function template(): array
    {
        return [
            'source' => [
                'name' => 'Reglamento de Concurso Público de Plazas Docentes para Nombramiento de la UNHEVAL',
                'path' => '/documents/reglamento-nombramiento.pdf',
                'approval' => 'Resolución de Consejo Universitario N.° 2308-2024-UNHEVAL y modificatorias',
                'annexes' => 'Anexos 01 y 02. Arts. 36 a 38.',
            ],
            'external' => [
                [
                    'key' => 'formacion',
                    'name' => 'Formación profesional',
                    'max' => 10,
                    'guidance' => 'Cuenta un título profesional (2 puntos) y el grado de mayor puntaje acreditado. Doctor vale 8, maestro o residentado admitido vale 6 y bachiller vale 1. Revisa el reconocimiento exigido para grados y títulos extranjeros.',
                ],
                [
                    'key' => 'formacion_servicio',
                    'name' => 'Formación en servicio',
                    'max' => 6,
                    'guidance' => 'Verifica segunda especialidad afín o posdoctorado (2 puntos), diplomado (1) y pasantía (0,5), según las condiciones del anexo. Excluye el residentado que ya contó en formación profesional en reemplazo del grado de maestro.',
                ],
                [
                    'key' => 'eventos',
                    'name' => 'Participación en eventos afines a la profesión',
                    'max' => 4,
                    'guidance' => 'Comprueba la afinidad, el emisor y la antigüedad máxima de cinco años. Aplica la tabla por tipo y lugar del evento o por horas acreditadas.',
                ],
                [
                    'key' => 'experiencia_universitaria',
                    'name' => 'Experiencia profesional universitaria',
                    'max' => 5,
                    'guidance' => 'Comprueba los semestres académicos acreditados. La docencia universitaria vale 0,5 puntos por semestre y la jefatura de práctica vale 0,2.',
                ],
                [
                    'key' => 'categoria',
                    'name' => 'Categoría académica universitaria',
                    'max' => 4,
                    'guidance' => 'Puntúa únicamente la categoría más alta acreditada. Principal vale 4, asociado 3, auxiliar 2 y jefe de práctica 1.',
                ],
                [
                    'key' => 'dedicacion',
                    'name' => 'Régimen de dedicación',
                    'max' => 2.5,
                    'guidance' => 'Comprueba el régimen y los semestres académicos acreditados. Por semestre, dedicación exclusiva vale 0,5, tiempo completo 0,3 y tiempo parcial 0,2. Aplica la nota del anexo sobre la categoría más alta alcanzada.',
                ],
                [
                    'key' => 'experiencia_extrauniversitaria',
                    'name' => 'Experiencia profesional extrauniversitaria',
                    'max' => 2.5,
                    'guidance' => 'Aplica 0,5 puntos por año o 0,25 por semestre acreditado. Excluye cargos políticos y prioriza la experiencia universitaria si coinciden las fechas.',
                ],
                [
                    'key' => 'idiomas',
                    'name' => 'Dominio de idiomas',
                    'max' => 3,
                    'guidance' => 'Verifica las certificaciones de instituciones reconocidas. Aplica 0,5 puntos para nivel básico, 1 para intermedio y 1,5 para avanzado.',
                ],
                [
                    'key' => 'publicaciones',
                    'name' => 'Publicaciones',
                    'max' => 8,
                    'guidance' => 'Contrasta cada publicación con la tabla de artículos, libros y compilaciones. Revisa afinidad, indización, ISSN, ISBN o inscripción en la Biblioteca Nacional según el tipo.',
                ],
                [
                    'key' => 'ponencias',
                    'name' => 'Ponencias en eventos afines a la profesión',
                    'max' => 5,
                    'guidance' => 'Comprueba que el sustento acredite la ponencia y que tenga hasta cinco años de antigüedad. Aplica el valor del anexo según el lugar del evento.',
                ],
                [
                    'key' => 'reconocimientos_universidades',
                    'name' => 'Reconocimientos de universidades',
                    'max' => 2.5,
                    'guidance' => 'Revisa el sustento universitario y aplica el valor previsto para el cargo, evento o comisión. Para comisiones internas y cargos académico-administrativos, revisa la constancia de cumplimiento de las labores.',
                ],
                [
                    'key' => 'reconocimientos_otras',
                    'name' => 'Reconocimientos de otras instituciones',
                    'max' => 2.5,
                    'guidance' => 'Verifica la institución emisora y el reconocimiento acreditado. Aplica el valor correspondiente de la tabla del anexo.',
                ],
            ],
            'internal' => [
                [
                    'key' => 'silabo',
                    'name' => 'Sílabo',
                    'max' => 7.5,
                    'guidance' => 'Revisa los cinco criterios del Anexo 02 sobre propósitos, metodología y evaluación, contenidos, calendarización y bibliografía. Cada integrante califica por separado.',
                    'scale' => [1.5, 1, 0.5, 0.2],
                    'items' => [
                        'Claridad y precisión de los propósitos formativos',
                        'Correspondencia de la metodología y la evaluación con el curso',
                        'Actualización de los contenidos de la disciplina',
                        'Calendarización de los contenidos por unidad',
                        'Actualidad y pertinencia de la bibliografía',
                    ],
                ],
                [
                    'key' => 'clase',
                    'name' => 'Clase modelo o clase magistral',
                    'max' => 25,
                    'guidance' => 'Califica los diez criterios del Anexo 02. El reglamento fija hasta veinte minutos de exposición, seguidos de preguntas. Cada integrante califica por separado.',
                    'scale' => [2.5, 2, 0.5, 0],
                    'items' => [
                        'Dominio y orden al desarrollar la clase',
                        'Conocimiento de los avances de la especialidad',
                        'Integración de la investigación en la clase',
                        'Seguridad, serenidad y autocontrol',
                        'Agilidad intelectual y lucidez',
                        'Capacidad de mantener el interés de los estudiantes',
                        'Uso del material didáctico',
                        'Evaluación continua del aprendizaje',
                        'Logro del propósito de la clase',
                        'Uso del tiempo según la planificación',
                    ],
                ],
                [
                    'key' => 'entrevista',
                    'name' => 'Entrevista personal',
                    'max' => 12.5,
                    'guidance' => 'Califica los cinco criterios del Anexo 02. El reglamento fija hasta quince minutos de entrevista. Cada integrante califica por separado.',
                    'scale' => [2.5, 1.75, 1, 0.25],
                    'items' => [
                        'Serenidad y autocontrol emocional',
                        'Agilidad mental y capacidad de persuasión',
                        'Conocimiento de los avances recientes',
                        'Conocimiento de la problemática regional, nacional y mundial',
                        'Idoneidad y capacidad para la docencia universitaria',
                    ],
                ],
            ],
            'minimums' => [
                'AUXILIAR' => ['external' => 16, 'internal' => 24, 'total' => 40],
                'ASOCIADO' => ['external' => 26, 'internal' => 34, 'total' => 60],
                'PRINCIPAL' => ['external' => 36, 'internal' => 34, 'total' => 70],
            ],
        ];
    }
}
