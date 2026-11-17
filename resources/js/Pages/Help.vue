<script setup>
import { Link } from "@inertiajs/vue3";
import PublicLayout from "../Layouts/PublicLayout.vue";
import Icon from "../Components/Icon.vue";
import { url } from "../lib/ui";
</script>
<template>
    <PublicLayout title="Guía del postulante"
        ><div class="detail-heading">
            <div class="container">
                <span class="eyebrow">TE ACOMPAÑAMOS EN EL PROCESO</span>
                <h1>Guía del postulante</h1>
                <p>Conoce cada paso para preparar y enviar tu expediente.</p>
            </div>
        </div>
        <div class="container help-content">
            <div class="help-guide">
                <section
                    v-for="(s, i) in [
                        {
                            title: 'Revisa la convocatoria',
                            text: 'Consulta la escuela profesional, el perfil, las bases, la carga académica y el cronograma. Las fechas se muestran en hora de Perú.',
                        },
                        {
                            title: 'Regístrate y completa tus datos',
                            text: 'Crea una cuenta con tu DNI, un correo al que tengas acceso y tu teléfono. Verifica tu información antes de comenzar.',
                        },
                        {
                            title: 'Inicia el expediente y completa el perfil',
                            text: 'Selecciona la plaza y confirma tus datos de contacto. Cuando la plaza solicite el formulario de perfil, guarda la formación, experiencia y los demás requisitos declarados antes de pasar al Fólder A.',
                        },
                        {
                            title: 'Fólder A · Solicitud y declaraciones',
                            text: 'Adjunta un PDF de hasta 5 MB por documento administrativo. Usa la solicitud prellenada si aparece y presenta cada declaración en la casilla indicada por las bases.',
                        },
                        {
                            title: 'Fólder B · Currículo documentado',
                            text: 'Adjunta los sustentos del perfil. En plazas con rúbrica, registra tus méritos, vincula los PDF y comprueba el CV e índice. Adjunta los sílabos en aspectos internos cuando la plaza los solicite.',
                        },
                        {
                            title: 'Revisa y confirma el envío',
                            text: 'Revisa los pendientes de ambos fólderes, acepta la declaración de veracidad y envía un solo expediente dentro del plazo. Un borrador aún está pendiente de envío.',
                        },
                        {
                            title: 'Conserva tu cargo y realiza el seguimiento',
                            text: 'Imprime o guarda el cargo como PDF. Abre Mis postulaciones para consultar la aptitud, las observaciones, los puntajes publicados y los avisos de la plaza.',
                        },
                    ]"
                    :key="s.title"
                    class="guide-step"
                >
                    <span>0{{ i + 1 }}</span>
                    <div>
                        <h2>{{ s.title }}</h2>
                        <p>{{ s.text }}</p>
                    </div>
                </section>
            </div>
            <section class="panel">
                <div class="panel-heading">
                    <Icon name="HelpCircle" />
                    <h2>Preguntas frecuentes</h2>
                </div>
                <details>
                    <summary>¿Puedo postular a más de una plaza?</summary>
                    <p>
                        Esta versión permite un expediente por persona y
                        convocatoria. Puedes participar en convocatorias
                        distintas. Revisa siempre las restricciones de las bases
                        aplicables.
                    </p>
                </details>
                <details>
                    <summary>
                        ¿Puedo cambiar un documento después de enviar?
                    </summary>
                    <p>
                        El envío cierra el expediente. Antes de confirmarlo
                        puedes reemplazar tus archivos mientras la inscripción
                        siga abierta.
                    </p>
                </details>
                <details>
                    <summary>
                        ¿Qué pasa si la inscripción cierra mientras estoy
                        trabajando?
                    </summary>
                    <p>
                        El servidor verifica el plazo al recibir cada solicitud.
                        Después de la hora de cierre ya no acepta archivos ni
                        envíos, aunque mantengas la pantalla abierta.
                    </p>
                </details>
                <details>
                    <summary>
                        ¿El cargo significa que he sido declarado apto?
                    </summary>
                    <p>
                        No. El cargo acredita la recepción. La comisión debe
                        revisar los requisitos y registrar su decisión.
                    </p>
                </details>
                <details>
                    <summary>
                        ¿Los resultados de demostración tienen validez?
                    </summary>
                    <p>
                        No. Las cuentas, las fechas y las calificaciones de
                        prueba sirven para revisar el funcionamiento del
                        sistema. La cuarta convocatoria oficial de 2026 exigió
                        expedientes físicos; esta plataforma en desarrollo no
                        sustituye ese trámite.
                    </p>
                </details>
            </section>
            <section class="panel" id="privacidad">
                <div class="panel-heading">
                    <Icon name="ShieldCheck" />
                    <h2>Información sobre tus datos</h2>
                </div>
                <p>
                    El sistema utiliza tus datos de identificación, contacto y
                    documentos para gestionar tu cuenta, registrar tus
                    postulaciones y permitir su evaluación. El expediente solo
                    es accesible para su titular, los jurados asignados y el
                    personal de gestión autorizado.
                </p>
                <p>
                    La publicación de prueba muestra códigos de expediente y
                    puntajes; no publica DNI, correo ni documentos privados. Los
                    registros de actividad permiten identificar las acciones
                    realizadas sobre el proceso.
                </p>
                <div class="notice">
                    Información provisional para el entorno de desarrollo. Antes
                    de operar con datos reales, la UNHEVAL debe aprobar la
                    política de privacidad, el responsable del tratamiento, los
                    canales para ejercer derechos y los plazos de conservación.
                </div>
                <p>
                    No cargues datos personales reales en las cuentas de
                    demostración. Utiliza archivos preparados para las pruebas.
                </p>
            </section>
            <section class="panel">
                <h2>Fuentes institucionales</h2>
                <p>
                    El diseño de los perfiles toma como referencia el cuadro de
                    plazas de la cuarta convocatoria de nombramiento 2026. Se
                    conservan el reglamento y el cronograma publicados por la
                    universidad.
                </p>
                <div class="source-links">
                    <a
                        :href="url('/documents/cuadro-plazas-2026-iv.pdf')"
                        target="_blank"
                        rel="noopener"
                        ><Icon name="FileText" />Cuadro de plazas 2026-IV<Icon
                            name="ArrowUpRight"
                            :size="16" /></a
                    ><a
                        :href="url('/documents/reglamento-nombramiento.pdf')"
                        target="_blank"
                        rel="noopener"
                        ><Icon name="FileText" />Reglamento de nombramiento<Icon
                            name="ArrowUpRight"
                            :size="16" /></a
                    ><a
                        :href="url('/documents/cronograma-2026-iv.pdf')"
                        target="_blank"
                        rel="noopener"
                        ><Icon name="FileText" />Cronograma 2026-IV<Icon
                            name="ArrowUpRight"
                            :size="16" /></a
                    ><a
                        href="https://unheval.edu.pe/portal/"
                        target="_blank"
                        rel="noopener"
                        ><Icon name="ExternalLink" />Portal oficial de la
                        UNHEVAL<Icon name="ArrowUpRight" :size="16"
                    /></a>
                </div>
            </section>
            <div class="row end">
                <Link :href="url('/')" class="btn btn-primary"
                    >Explorar convocatorias <Icon name="ArrowRight" :size="17"
                /></Link>
            </div></div
    ></PublicLayout>
</template>
