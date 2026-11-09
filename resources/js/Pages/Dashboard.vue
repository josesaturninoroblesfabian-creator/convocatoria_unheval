<script setup>
import { computed } from "vue";
import { Link, usePage } from "@inertiajs/vue3";
import AppLayout from "../Layouts/AppLayout.vue";
import Icon from "../Components/Icon.vue";
import Badge from "../Components/Badge.vue";
import { url, date, shortRef } from "../lib/ui";
import { useApplicationWindow } from "../lib/useApplicationWindow";
const props = defineProps({
    stats: Object,
    applications: Array,
    calls: Array,
});
const user = usePage().props.auth.user;
const applicant = user.role === "postulante";
const juror = user.role === "jurado";
const isOpen = useApplicationWindow();
const title = applicant ? "Inicio del postulante" : juror ? "Panel del jurado" : "Panel de gestión";
const nextAction = computed(() => {
    if (applicant) {
        const draft = props.applications?.find(
            (item) => item.status === "BORRADOR" && isOpen(item.recruitment_call),
        );
        return draft
            ? {
                  text: `Tienes un borrador para ${draft.vacancy.school}. Revisa tus datos y adjunta los sustentos antes de enviarlo.`,
                  label: "Continuar borrador",
                  path: `/mis-postulaciones/${draft.id}`,
              }
            : {
                  text: "Consulta el perfil y el cronograma de las plazas. Puedes guardar tus datos y méritos para reutilizarlos al postular.",
                  label: "Buscar plazas",
                  path: "/",
              };
    }

    if (juror) {
        const pending = props.applications?.find(
            (item) =>
                item.status === "RECIBIDA" &&
                item.recruitment_call.status === "EN_EVALUACION",
        );
        return pending
            ? {
                  text: `Hay un expediente por revisar para ${pending.vacancy.school}. Empieza por comprobar los requisitos y los PDF.`,
                  label: "Revisar expediente",
                  path: `/expedientes/${pending.id}`,
              }
            : {
                  text: "Consulta los expedientes de tus plazas asignadas y comprueba en qué etapa se encuentran.",
                  label: "Ver expedientes asignados",
                  path: "/expedientes?sort=recent",
              };
    }

    const draft = props.calls?.find((call) => call.status === "BORRADOR");
    if (draft) {
        return {
            text: `Tienes una convocatoria en borrador: ${draft.title}. Revisa las plazas, las fechas y la comisión antes de publicar.`,
            label: "Continuar convocatoria",
            path: `/gestion/convocatorias/${draft.id}`,
        };
    }
    const inReview = props.calls?.find((call) => call.status === "EN_EVALUACION");
    if (inReview) {
        return {
            text: `${inReview.title} está en evaluación. Consulta sus expedientes y el avance de la comisión.`,
            label: "Seguir evaluación",
            path: `/expedientes?call=${inReview.id}&sort=recent`,
        };
    }
    return {
        text: "Define el cronograma, añade las plazas y asigna la comisión que revisará los expedientes.",
        label: "Nueva convocatoria",
        path: "/gestion/convocatorias/nueva",
    };
});
const steps = applicant
    ? ["Consulta la plaza y su cronograma.", "Completa el formulario y adjunta los sustentos.", "Confirma el envío y descarga el cargo."]
    : juror
      ? ["Verifica los requisitos y registra la aptitud.", "Revisa los méritos y confirma la hoja externa si el concurso usa etapas.", "Registra tu calificación cuando se habilite."]
      : ["Configura las plazas y el cronograma.", "Asigna jurados y publica la convocatoria.", "Da seguimiento a la revisión y publica los resultados."];
</script>
<template>
    <AppLayout :title="title">
        <section class="welcome-card">
            <div>
                <span class="eyebrow">PRÓXIMO PASO</span>
                <h2>Hola, {{ user.name.split(" ")[0] }}</h2>
                <p>{{ nextAction.text }}</p>
                <Link :href="url(nextAction.path)"
                    >{{ nextAction.label }} <Icon name="ArrowRight" :size="17"
                /></Link>
            </div>
            <div class="welcome-illustration">
                <Icon name="GraduationCap" :size="108" /><span>UNHEVAL</span>
            </div>
        </section>
        <div v-if="stats" class="metric-grid">
            <article
                v-for="(metric, i) in [
                    {
                        label: juror ? 'Convocatorias asignadas' : 'Convocatorias registradas',
                        value: stats.calls,
                        icon: 'BriefcaseBusiness',
                    },
                    {
                        label: juror ? 'Plazas asignadas' : 'Plazas registradas',
                        value: stats.vacancies,
                        icon: 'GraduationCap',
                    },
                    {
                        label: 'Expedientes recibidos',
                        value: stats.applications,
                        icon: 'FolderOpen',
                    },
                    {
                        label: 'Pendientes de aptitud',
                        value: stats.pending,
                        icon: 'ClipboardCheck',
                    },
                ]"
                :key="metric.label"
                class="metric-card"
            >
                <div class="row between">
                    <span>{{ metric.label }}</span
                    ><Icon :name="metric.icon" :size="21" />
                </div>
                <strong>{{ String(metric.value).padStart(2, "0") }}</strong
                ><small>{{
                    i === 3 ? "Por revisar" : "Registros actuales"
                }}</small>
            </article>
        </div>
        <div class="dashboard-columns">
            <section class="panel">
                <div class="panel-heading between">
                    <div class="row">
                        <Icon name="FolderOpen" />
                        <h2>
                            {{
                                applicant
                                    ? "Mis expedientes"
                                    : "Expedientes recientes"
                            }}
                        </h2>
                    </div>
                    <Link
                        :href="
                            url(
                                applicant
                                    ? '/mis-postulaciones'
                                    : '/expedientes',
                            )
                        "
                        class="text-link"
                        >Ver todos <Icon name="ArrowRight" :size="15"
                    /></Link>
                </div>
                <div v-if="!applications?.length" class="empty-state compact">
                    <Icon name="FolderOpen" :size="35" />
                    <h3>
                        {{
                            applicant
                                ? "Todavía no has iniciado una postulación"
                                : "Aún no hay expedientes recibidos"
                        }}
                    </h3>
                    <p>
                        {{
                            applicant
                                ? "Tus borradores y expedientes enviados aparecerán aquí."
                                : "Los expedientes aparecerán aquí cuando los postulantes confirmen su envío."
                        }}
                    </p>
                </div>
                <Link
                    v-for="a in applications"
                    :key="a.id"
                    :href="
                        url(
                            (applicant
                                ? '/mis-postulaciones/'
                                : '/expedientes/') + a.id,
                        )
                    "
                    class="application-row"
                    ><div class="file-symbol"><Icon name="FileText" /></div>
                    <div class="grow">
                        <b>{{
                            applicant
                                ? a.vacancy.school
                                : a.applicant_snapshot?.name || a.user?.name
                        }}</b
                        ><small
                            >{{ shortRef(a.reference) }} ·
                            {{
                                applicant
                                    ? a.recruitment_call.code
                                    : a.vacancy.school
                            }}</small
                        >
                    </div>
                    <Badge :value="a.status" /><Icon
                        name="ChevronRight"
                        :size="16"
                /></Link>
            </section>
            <aside class="panel tips-panel">
                <div class="panel-heading">
                    <Icon name="ClipboardCheck" />
                    <h2>Tu recorrido</h2>
                </div>
                <ol class="tips-list">
                    <li v-for="(step, index) in steps" :key="step">
                        <span class="badge">{{ index + 1 }}</span> {{ step }}
                    </li>
                </ol>
                <Link v-if="applicant" :href="url('/perfil')" class="text-link">
                    Abrir mi perfil y méritos <Icon name="ArrowRight" :size="16" />
                </Link>
                <Link v-else :href="url('/ayuda')" class="text-link">
                    Consultar guía <Icon name="ArrowUpRight" :size="16" />
                </Link>
            </aside>
        </div>
        <section v-if="calls?.length" class="panel">
            <div class="panel-heading">
                <Icon name="CalendarDays" />
                <h2>{{ juror ? "Convocatorias asignadas" : "Convocatorias recientes" }}</h2>
            </div>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Convocatoria</th>
                            <th>Plazas</th>
                            <th>Expedientes</th>
                            <th>Cierre</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="c in calls" :key="c.id">
                            <td>
                                <Link
                                    v-if="user.role !== 'jurado'"
                                    :href="
                                        url('/gestion/convocatorias/' + c.id)
                                    "
                                    class="table-title"
                                    >{{ c.title }}</Link
                                ><Link
                                    v-else-if="c.status !== 'BORRADOR'"
                                    :href="url('/expedientes?call=' + c.id + '&sort=recent')"
                                    class="table-title"
                                    >{{ c.title }}</Link
                                ><b v-else>{{ c.title }}</b
                                ><small class="block muted"
                                    >{{ c.code }}
                                    {{
                                        c.is_demo ? "· Demostración" : ""
                                    }}</small
                                >
                            </td>
                            <td>{{ c.vacancies_count }}</td>
                            <td>{{ c.applications_count }}</td>
                            <td>{{ date(c.closes_at) }}</td>
                            <td><Badge :value="c.status" /></td>
                        </tr>
                    </tbody>
                </table>
            </div></section
    ></AppLayout>
</template>
