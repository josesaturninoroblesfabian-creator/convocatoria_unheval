<?php

return [
    'demo' => (bool) env('RECRUITMENT_DEMO', false),
    // Habilita la publicación y calificación de convocatorias oficiales
    // (no de demostración). Requiere validar la normativa con la universidad.
    'allow_official' => (bool) env('RECRUITMENT_ALLOW_OFFICIAL', false),
    'max_file_kb' => 5120,
];
