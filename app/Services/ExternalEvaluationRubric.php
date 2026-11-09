<?php

namespace App\Services;

class ExternalEvaluationRubric
{
    /**
     * @return array{
     *     version: string, modality: string, category: string, minimum: int, maximum: int,
     *     source: array{label: string, url: string}, progression: string, review: string,
     *     disability_bonus: array{percent: int, stage: string, evidence: string},
     *     rubrics: list<array{key: string, name: string, max: int|float, guidance: string, items: list<array{key: string, name: string, points: int|float, unit: string}>}>
     * }|null
     */
    public static function template(string $modality, string $category): ?array
    {
        $nombramiento = NombramientoRubric::template();
        $contract = $modality === 'CONTRATO';
        $minimum = match ($modality) {
            'NOMBRAMIENTO' => $nombramiento['minimums'][$category]['external'] ?? null,
            'CONTRATO' => match ($category) {
                'DC A1', 'DC A2', 'DC A3' => 18,
                'DC B1', 'DC B2', 'DC B3' => 16,
                default => null,
            },
            default => null,
        };

        if ($minimum === null) {
            return null;
        }

        $items = self::items($contract);
        $guidance = [
            'formacion' => $contract
                ? 'Califica el grado afín de mayor puntaje y un solo título profesional afín. Para estudios en el extranjero, comprueba el reconocimiento indicado en el anexo (ANR o SUNEDU).'
                : 'Califica el grado de mayor puntaje y un solo título profesional. Para estudios en el extranjero, comprueba el reconocimiento de la SUNEDU.',
            'formacion_servicio' => ($contract
                ? 'La segunda especialidad por residentado de médicos y odontólogos puntúa aquí si también tienen el grado de magíster.'
                : 'Excluye el residentado que ya contó como grado en formación profesional. Si se acredita maestría y segunda especialidad, pueden corresponder a rubros distintos.')
                .' El anexo señala cinco años de antigüedad para pasantías. Acumula sustentos distintos hasta el tope del rubro.',
            'eventos' => 'Verifica afinidad, emisor reconocido y antigüedad de hasta cinco años. Acumula eventos distintos hasta el tope del rubro.'
                .($contract ? '' : ' Los tramos por horas de seminarios y cursos de especialización corresponden a eventos nacionales o locales.'),
            'experiencia_universitaria' => $contract
                ? 'Cuenta los semestres acreditados como docente o jefe de práctica. Verifica que el sustento provenga de la oficina de personal de la universidad.'
                : 'Cuenta los semestres acreditados como docente o jefe de práctica con resoluciones, certificaciones u otros sustentos de la universidad.',
            'categoria' => 'Califica únicamente la categoría más alta acreditada con resoluciones u otros documentos equivalentes.',
            'dedicacion' => 'Cuenta los semestres de la categoría más alta alcanzada. Revisa la resolución que acredita el régimen.',
            'experiencia_extrauniversitaria' => 'Cuenta cada período una sola vez en años y semestres. Si coincide con experiencia universitaria, prima la universitaria. Excluye cargos políticos.'
                .($contract ? ' Excluye también las distinciones ajenas a logros académicos o profesionales.' : ''),
            'idiomas' => 'Verifica el nivel acreditado por idioma (quechua, inglés, italiano, portugués, alemán, japonés u otros) con una certificación de una institución académica reconocida.',
            'publicaciones' => 'Revisa el ejemplar y la afinidad, la indización, el ISSN, el ISBN o la inscripción en la Biblioteca Nacional cuando corresponda. Acumula publicaciones distintas hasta el tope.',
            'ponencias' => 'Verifica afinidad, lugar del evento, emisor reconocido y antigüedad de hasta cinco años. Cuenta las ponencias acreditadas hasta el tope.',
            'reconocimientos_universidades' => 'Para comisiones internas y cargos académico-administrativos internos de facultad, exige constancia de cumplimiento de labores para asignar puntaje.',
        ];
        $rubrics = array_map(static function (array $rubric) use ($items, $guidance): array {
            $key = $rubric['key'];
            $rubric['guidance'] = $guidance[$key] ?? $rubric['guidance'];
            $rubric['items'] = [];
            foreach ($items[$key] as $itemKey => [$name, $points, $unit]) {
                $rubric['items'][] = ['key' => $itemKey, 'name' => $name, 'points' => $points, 'unit' => $unit];
            }

            return $rubric;
        }, $nombramiento['external']);

        return [
            'version' => $contract ? 'contrato-4480-2024-v1' : 'nombramiento-2308-3674-2024-v1',
            'modality' => $modality,
            'category' => $category,
            'minimum' => $minimum,
            'maximum' => (int) array_sum(array_column($rubrics, 'max')),
            'source' => $contract
                ? [
                    'label' => 'R. C. U. 4480-2024-UNHEVAL · Anexo 01 y arts. 27, 31–32',
                    'url' => 'https://transparencia.unheval.edu.pe/inicio/descargar?file=36bb91a09ee3ba229ae558dd155efe35d2f001a0904bf4beedea27367b1b6ae89c151656c54bf3dbc23ef8a14e34dc6cb2b5b19254d18f45a5515bb2d4d32ecdcf013d3cd17eccd1facdfc64eeaabcc5740a21579e#page=13',
                ]
                : [
                    'label' => 'R. C. U. 2308-2024 y 3674-2024-UNHEVAL · Anexo 01 y arts. 32, 36–37',
                    'url' => '/documents/reglamento-nombramiento.pdf#page=17',
                ],
            'progression' => 'Solo quien alcanza el mínimo de aspectos externos puede pasar a aspectos internos.',
            'review' => $contract
                ? 'La comisión califica. El reglamento prevé citar por escrito al postulante con al menos 12 horas de anticipación y publicar la citación. Si el postulante no se presenta, la comisión procede a calificar.'
                : 'La comisión califica en forma presencial. El reglamento prevé la presencia del postulante y su firma en la hoja de calificación. Si no se presenta, la comisión procede a calificar según el artículo 32.',
            'disability_bonus' => ['percent' => 15, 'stage' => 'resultado final aprobatorio', 'evidence' => 'documento sustentatorio en el Fólder A'],
            'rubrics' => $rubrics,
        ];
    }

    /**
     * @return array<string, array<string, array{string, int|float, string}>>
     */
    private static function items(bool $contract): array
    {
        return [
            'formacion' => [
                'doctor' => ['Doctorado', 8, 'Grado más alto'],
                'maestro' => ['Maestría o residentado médico u odontológico admitido como equivalente', 6, 'Grado más alto'],
                'bachiller' => ['Bachiller', 1, 'Grado más alto'],
                'titulo' => ['Título profesional', 2, 'Un solo título'],
            ],
            'formacion_servicio' => [
                'especialidad' => ['Segunda especialidad afín o certificado de posdoctorado', 2, 'Por sustento'],
                'diplomado' => ['Diplomado', 1, 'Por sustento'],
                'pasantia' => ['Pasantía', 0.5, 'Por sustento'],
            ],
            'eventos' => [
                'extranjero' => ['Congreso, simposio u otro evento académico en el extranjero', 1.5, 'Por evento'],
                'congreso_peru' => ['Congreso en el Perú', 0.7, 'Por evento'],
                'evento_5_40' => ['Seminario, encuentro, foro, taller u otro evento de 5 a 40 horas', 0.2, 'Por evento'],
                'evento_41_80' => ['Seminario, encuentro, foro, taller u otro evento de 41 a 80 horas', 0.3, 'Por evento'],
                'evento_81_120' => ['Seminario, encuentro, foro, taller u otro evento de 81 a 120 horas', 0.4, 'Por evento'],
                'evento_121' => ['Seminario, encuentro, foro, taller u otro evento de más de 120 horas', 0.5, 'Por evento'],
                'curso_120_200' => ['Curso de especialización de 120 a 200 horas', 0.6, 'Por curso'],
                'curso_201' => ['Curso de especialización de más de 200 horas', 0.8, 'Por curso'],
            ],
            'experiencia_universitaria' => [
                'docente' => ['Docencia universitaria', 0.5, 'Por semestre académico'],
                'jefe_practica' => ['Jefatura de práctica', 0.2, 'Por semestre académico'],
            ],
            'categoria' => [
                'principal' => [$contract ? 'Profesor principal o DC A' : 'Profesor principal', 4, 'Categoría más alta'],
                'asociado' => ['Profesor asociado', 3, 'Categoría más alta'],
                'auxiliar' => [$contract ? 'Profesor auxiliar o DC B' : 'Profesor auxiliar', 2, 'Categoría más alta'],
                'jefe_practica' => ['Jefe de práctica', 1, 'Categoría más alta'],
            ],
            'dedicacion' => [
                'exclusiva' => ['Dedicación exclusiva', 0.5, 'Por semestre académico'],
                'completo' => ['Tiempo completo', 0.3, 'Por semestre académico'],
                'parcial' => ['Tiempo parcial', 0.2, 'Por semestre académico'],
            ],
            'experiencia_extrauniversitaria' => [
                'anio' => ['Año de experiencia profesional', 0.5, 'Por año'],
                'semestre' => ['Semestre de experiencia profesional', 0.25, 'Por semestre'],
            ],
            'idiomas' => [
                'basico' => ['Nivel básico', 0.5, 'Por idioma'],
                'intermedio' => ['Nivel intermedio', 1, 'Por idioma'],
                'avanzado' => ['Nivel avanzado', 1.5, 'Por idioma'],
            ],
            'publicaciones' => [
                'articulo_indizado' => ['Artículo en revista indizada', 2.5, 'Por publicación'],
                'articulo_issn' => ['Artículo en revista no indizada con ISSN', 0.5, 'Por publicación'],
                'articulo_sin_issn' => ['Artículo de la especialidad en periódico de prestigio o revista no indizada sin ISSN', 0.3, 'Por publicación'],
                'libro_isbn' => ['Libro afín con ISBN', 2.5, 'Por publicación'],
                'articulo_academico' => ['Artículo publicado en institución académica', 1, 'Por publicación'],
                'libro_bnp' => ['Libro afín sin ISBN e inscrito en la Biblioteca Nacional', 1.5, 'Por publicación'],
                'libro_sin_registro' => ['Libro sin ISBN ni inscripción en la Biblioteca Nacional', 0.5, 'Por publicación'],
                'compilacion_isbn' => ['Compilación afín con ISBN', 0.4, 'Por publicación'],
                'compilacion_bnp' => ['Compilación afín sin ISBN e inscrita en la Biblioteca Nacional', 0.3, 'Por publicación'],
                'compilacion_sin_registro' => ['Compilación afín sin ISBN ni inscripción en la Biblioteca Nacional', 0.2, 'Por publicación'],
            ],
            'ponencias' => [
                'extranjero' => ['Evento académico en el extranjero', 2, 'Por ponencia'],
                'peru' => ['Evento académico en el Perú fuera de la región Huánuco', 1, 'Por ponencia'],
                'local' => ['Evento local o regional', 0.25, 'Por ponencia'],
            ],
            'reconocimientos_universidades' => [
                'rector' => ['Rector', 1, 'Por semestre'],
                'vicerrector' => ['Vicerrector', 0.8, 'Por semestre'],
                'decanato' => ['Decano, director de Posgrado o secretario general', 0.7, 'Por semestre'],
                'direccion' => [$contract
                    ? 'Dirección u oficina de segundo nivel, dirección de departamento o escuela, o coordinación de sección'
                    : 'Dirección universitaria, dirección de departamento o escuela, o coordinación de sección', 0.5, 'Por semestre'],
                'secretaria' => [$contract
                    ? 'Secretario docente o miembro de Asamblea Universitaria, Consejo de Facultad o Consejo Universitario'
                    : 'Secretario docente o miembro de Asamblea Universitaria o Consejo de Facultad', 0.4, 'Por semestre'],
                'evento' => ['Participación en evento cívico, de proyección social o académico', 0.2, 'Por semestre'],
                'comite_interno' => ['Miembro de comité interno', 0.4, 'Por semestre'],
                'otra_comision' => ['Miembro de otra comisión', 0.3, 'Por semestre'],
            ],
            'reconocimientos_otras' => [
                'honor' => ['Honor de entidad pública o privada reconocida, nacional o extranjera', 1, 'Por reconocimiento'],
                'extraordinario' => ['Profesor extraordinario', 0.8, 'Por reconocimiento'],
                'academico' => ['Reconocimiento o agradecimiento de entidad académica', 0.5, 'Por reconocimiento'],
                'publico' => ['Reconocimiento de institución pública', 0.3, 'Por reconocimiento'],
                'membresia' => ['Miembro de colegio profesional o institución', 0.2, 'Por sustento'],
            ],
        ];
    }
}
