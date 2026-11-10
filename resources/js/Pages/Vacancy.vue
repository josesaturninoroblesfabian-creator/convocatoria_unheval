<script setup>
import { computed, ref } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import PublicLayout from "../Layouts/PublicLayout.vue";
import Icon from "../Components/Icon.vue";
import Badge from "../Components/Badge.vue";
import Errors from "../Components/Errors.vue";
import { url, date, label } from "../lib/ui";
import { useApplicationWindow } from "../lib/useApplicationWindow";
const isOpen = useApplicationWindow();
const props = defineProps({
    vacancy: Object,
    preview: Boolean,
    stagePublications: [Object, Array],
});
const stageResults = {
    APTO: "Apto",
    NO_APTO: "No apto",
    SUPERA_EXTERNA: "Pasa a aspectos internos",
    NO_SUPERA_EXTERNA: "No alcanza el mínimo externo",
};
const documents = computed(() => [
    ...(props.vacancy.public_documents ?? []),
    ...(props.vacancy.recruitment_call.public_documents ?? []),
]);
const timeline = computed(() =>
    [...(props.vacancy.schedule ?? [])].sort((left, right) =>
        left.starts_at.localeCompare(right.starts_at),
    ),
);
const localDate = (value) => date(value + "-05:00", true);
const page = usePage();
const busy = ref(false);
function apply() {
    if (!page.props.auth.user) {
        router.visit(url("/ingresar"));
        return;
    }
    busy.value = true;
    router.post(
        url(`/plazas/${props.vacancy.id}/postular`),
        {},
        { onFinish: () => (busy.value = false) },
    );
}
</script>
<template>
    <PublicLayout :title="vacancy.school"
        ><div class="detail-heading">
            <div class="container">
                <Link
                    :href="
                        url(
                            preview
                                ? '/gestion/plazas/' + vacancy.id + '/editar'
                                : '/',
                        )
                    "
                    class="back-link"
                    ><Icon name="ArrowLeft" :size="17" />
                    {{
                        preview
                            ? "Volver a editar la ficha"
                            : "Todas las convocatorias"
                    }}</Link
                >
                <div class="row wrap gap-12">
                    <Badge :value="vacancy.recruitment_call.status" /><span
                        v-if="vacancy.recruitment_call.is_demo"
                        class="pill"
                        >Demostración</span
                    ><span class="muted">{{
                        vacancy.recruitment_call.code
                    }}</span>
                </div>
                <h1>{{ vacancy.school }}</h1>
                <p>Facultad de {{ vacancy.faculty }}</p>
            </div>
        </div>
        <div class="container detail-grid">
            <div>
                <Errors :errors="$page.props.errors" />
                <section
                    v-for="(publication, stage) in stagePublications"
                    :key="stage"
                    class="panel"
                >
                    <h2>
                        {{
                            stage === "aptitude"
                                ? "Resultados de aptitud"
                                : "Resultados de aspectos externos"
                        }}
                    </h2>
                    <p class="muted">
                        Publicado el {{ date(publication.published_at, true) }}.
                        Usa el código de tu cargo de recepción.
                    </p>
                    <div class="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Código de expediente</th>
                                    <th>Resultado</th>
                                    <th v-if="stage === 'external'">Puntaje</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="row in publication.rows"
                                    :key="row.reference"
                                >
                                    <td class="mono">{{ row.reference }}</td>
                                    <td>{{ stageResults[row.result] }}</td>
                                    <td v-if="stage === 'external'">
                                        {{ row.score }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-if="!publication.rows.length">
                        Esta etapa no registra expedientes.
                    </p>
                </section>
                <div v-if="preview" class="notice">
                    Vista previa del gestor. Los cambios guardados aparecerán al
                    publicar.
                </div>
                <div
                    v-else-if="vacancy.recruitment_call.is_demo"
                    class="notice"
                >
                    Proceso de demostración con fechas y calificaciones de
                    prueba.
                </div>
                <section class="panel">
                    <div class="panel-heading">
                        <Icon name="BriefcaseBusiness" />
                        <h2>Sobre la plaza</h2>
                    </div>
                    <p
                        v-if="
                            vacancy.description || !vacancy.professional_profile
                        "
                    >
                        {{
                            vacancy.description ||
                            vacancy.recruitment_call.description
                        }}
                    </p>
                    <div class="info-grid">
                        <div>
                            <small>Registro AIRHSP</small
                            ><b>{{ vacancy.airhsp }}</b>
                        </div>
                        <div>
                            <small>Código CAP</small><b>{{ vacancy.cap }}</b>
                        </div>
                        <div>
                            <small>Categoría</small
                            ><b>{{ label(vacancy.category) }}</b>
                        </div>
                        <div v-if="vacancy.contract_hours">
                            <small>Jornada del contrato</small>
                            <b
                                >{{ vacancy.contract_hours.weekly }} h
                                semanales</b
                            >
                            <small
                                >{{ vacancy.contract_hours.teaching }} lectivas
                                · {{ vacancy.contract_hours.other }} no
                                lectivas</small
                            >
                        </div>
                        <div v-else>
                            <small>Régimen de dedicación</small
                            ><b
                                >{{ label(vacancy.dedication) }} ·
                                {{ vacancy.weekly_hours }} h</b
                            >
                        </div>
                    </div>
                </section>
                <section v-if="vacancy.professional_profile" class="panel">
                    <div class="panel-heading">
                        <Icon name="GraduationCap" />
                        <h2>Perfil profesional</h2>
                    </div>
                    <p style="white-space: pre-line">
                        {{ vacancy.professional_profile }}
                    </p>
                </section>
                <section v-if="vacancy.requirements.length" class="panel">
                    <div class="panel-heading">
                        <Icon name="GraduationCap" />
                        <h2>
                            {{
                                vacancy.professional_profile
                                    ? "Documentos requeridos para postular"
                                    : "Perfil y documentos requeridos"
                            }}
                        </h2>
                    </div>
                    <div
                        v-for="(r, i) in vacancy.requirements"
                        :key="r.key"
                        class="requirement"
                    >
                        <span>{{ String(i + 1).padStart(2, "0") }}</span>
                        <p>
                            {{ r.label }}
                            <small>{{
                                r.required ? "Obligatorio" : "Opcional"
                            }}</small>
                        </p>
                    </div>
                </section>
                <section class="panel">
                    <div class="panel-heading">
                        <Icon name="BookOpen" />
                        <h2>Carga académica</h2>
                    </div>
                    <div class="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Curso</th>
                                    <th>HT</th>
                                    <th>HP</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="c in vacancy.courses" :key="c.code">
                                    <td class="mono">{{ c.code }}</td>
                                    <td>{{ c.name }}</td>
                                    <td>{{ c.ht }}</td>
                                    <td>{{ c.hp }}</td>
                                    <td>
                                        <b>{{ Number(c.ht) + Number(c.hp) }}</b>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="footnote">
                        HT: horas teóricas · HP: horas prácticas. La carga
                        publicada es independiente de la jornada semanal de la
                        plaza.
                    </p>
                </section>
                <section class="panel">
                    <div class="panel-heading">
                        <Icon name="CalendarDays" />
                        <h2>Cronograma</h2>
                    </div>
                    <div v-if="timeline.length" class="timeline">
                        <div>
                            <span></span>
                            <div>
                                <strong>Inscripción digital</strong>
                                <p>
                                    Del
                                    {{
                                        date(
                                            vacancy.recruitment_call.opens_at,
                                            true,
                                        )
                                    }}
                                    al
                                    {{
                                        date(
                                            vacancy.recruitment_call.closes_at,
                                            true,
                                        )
                                    }}
                                </p>
                            </div>
                        </div>
                        <div v-for="(event, index) in timeline" :key="index">
                            <span></span>
                            <div>
                                <strong>{{ event.label }}</strong>
                                <p>
                                    {{ localDate(event.starts_at)
                                    }}<template v-if="event.ends_at">
                                        al
                                        {{ localDate(event.ends_at) }}</template
                                    >
                                </p>
                                <p v-if="event.detail">{{ event.detail }}</p>
                            </div>
                        </div>
                    </div>
                    <div v-else class="timeline">
                        <div
                            v-for="(s, i) in vacancy.recruitment_call.schedule"
                            :key="i"
                        >
                            <span></span>
                            <div>
                                <strong>{{ s.label }}</strong>
                                <p>{{ s.date }}</p>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
            <aside>
                <div class="sticky-card">
                    <div class="panel">
                        <span class="eyebrow">TU PRÓXIMA OPORTUNIDAD</span>
                        <h3>Forma parte de la UNHEVAL</h3>
                        <div class="apply-date">
                            <Icon name="Clock3" />
                            <div>
                                <small>Inscripción hasta</small
                                ><b>{{
                                    date(
                                        vacancy.recruitment_call.closes_at,
                                        true,
                                    )
                                }}</b
                                ><small>Hora de Perú (UTC−5)</small>
                            </div>
                        </div>
                        <button
                            v-if="
                                !preview &&
                                vacancy.recruitment_call
                                    .accepting_applications &&
                                (!$page.props.auth.user ||
                                    $page.props.auth.user.role === 'postulante')
                            "
                            class="btn btn-primary full"
                            :disabled="busy"
                            @click="apply"
                        >
                            {{ busy ? "Preparando…" : "Preparar mi postulación"
                            }}<Icon name="ArrowRight" :size="17" />
                        </button>
                        <div v-else-if="!preview" class="notice">
                            {{
                                isOpen(vacancy.recruitment_call)
                                    ? "Ingresa con una cuenta de postulante."
                                    : "La inscripción de esta convocatoria está cerrada."
                            }}
                        </div>
                        <p v-if="!preview" class="footnote">
                            Al iniciar creas un borrador. Tu postulación se
                            registra cuando confirmas el envío del expediente.
                        </p>
                    </div>
                    <div class="panel">
                        <h3>Documentos del proceso</h3>
                        <a
                            v-for="doc in documents"
                            :key="doc.path"
                            :href="url(doc.path)"
                            target="_blank"
                            rel="noopener"
                            class="document-link"
                            ><Icon name="FileText" /><span
                                >{{ doc.name
                                }}<small
                                    >PDF ·
                                    {{
                                        doc.id
                                            ? "De esta plaza"
                                            : "De la convocatoria"
                                    }}</small
                                ></span
                            ><Icon name="ArrowUpRight" :size="17" /></a
                        ><a
                            href="https://unheval.edu.pe/portal/"
                            target="_blank"
                            rel="noopener"
                            class="text-link"
                            >Ver publicación oficial
                            <Icon name="ExternalLink" :size="15"
                        /></a>
                    </div>
                </div>
            </aside></div
    ></PublicLayout>
</template>
