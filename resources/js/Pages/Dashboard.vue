<script setup>
import { computed } from "vue";
import { Link, usePage } from "@inertiajs/vue3";
import AppLayout from "../Layouts/AppLayout.vue";
import Icon from "../Components/Icon.vue";
import Badge from "../Components/Badge.vue";
import { url, date, shortRef } from "../lib/ui";
defineProps({
    stats: Object,
    applications: Array,
    events: Array,
    calls: Array,
});
const user = usePage().props.auth.user;
const applicant = user.role === "postulante";
const greeting = computed(() => user.name.split(" ")[0]);
</script>
<template>
    <AppLayout
        title="Resumen"
        subtitle="Toda la información que necesitas, en un solo lugar."
        ><template #actions
            ><Link
                v-if="['administrador', 'gestor'].includes(user.role)"
                :href="url('/gestion/convocatorias/nueva')"
                class="btn btn-primary"
                ><Icon name="Plus" :size="18" />Nueva convocatoria</Link
            ><Link
                v-else-if="applicant"
                :href="url('/')"
                class="btn btn-primary"
                >Explorar plazas <Icon name="ArrowUpRight" :size="18" /></Link
        ></template>
        <section class="welcome-card">
            <div>
                <span class="eyebrow">{{
                    applicant
                        ? "TU VOCACIÓN TIENE UN LUGAR AQUÍ"
                        : "VICERRECTORADO ACADÉMICO"
                }}</span>
                <h2>
                    Hola, {{ greeting }} <span class="greeting-dot">.</span>
                </h2>
                <p>
                    {{
                        applicant
                            ? "Prepara tu expediente con calma. Puedes guardar tu avance y continuar después."
                            : "Acompaña cada etapa del concurso con información organizada y trazable."
                    }}
                </p>
                <Link
                    :href="
                        url(
                            applicant
                                ? '/mis-postulaciones'
                                : user.role === 'jurado'
                                  ? '/expedientes'
                                  : '/gestion/convocatorias',
                        )
                    "
                    >{{
                        applicant
                            ? "Ver mis postulaciones"
                            : user.role === "jurado"
                              ? "Revisar expedientes"
                              : "Gestionar convocatorias"
                    }}<Icon name="ArrowRight" :size="17"
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
                        label: 'Convocatorias registradas',
                        value: stats.calls,
                        icon: 'BriefcaseBusiness',
                    },
                    {
                        label: 'Plazas en el sistema',
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
                                ? "Explora las plazas y elige la que se ajuste a tu perfil."
                                : "Los expedientes aparecerán aquí cuando los postulantes confirmen su envío."
                        }}
                    </p>
                    <Link
                        v-if="applicant"
                        :href="url('/')"
                        class="btn btn-outline btn-sm"
                        >Buscar una plaza</Link
                    >
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
                    <Icon name="ShieldCheck" />
                    <h2>
                        {{
                            applicant
                                ? "Antes de enviar"
                                : "Un proceso trazable"
                        }}
                    </h2>
                </div>
                <template v-if="applicant"
                    ><p>
                        Verifica los requisitos de la plaza y adjunta tus
                        documentos en PDF.
                    </p>
                    <ul class="tips-list">
                        <li>
                            <Icon name="CheckCircle2" :size="17" /> Archivos de
                            hasta 5 MB
                        </li>
                        <li>
                            <Icon name="CheckCircle2" :size="17" /> Una plaza
                            por convocatoria
                        </li>
                        <li>
                            <Icon name="CheckCircle2" :size="17" /> Cargo
                            disponible tras el envío
                        </li>
                    </ul></template
                ><template v-else
                    ><p>
                        Las acciones importantes quedan registradas con su fecha
                        y responsable.
                    </p>
                    <div
                        v-for="event in events?.slice(0, 4)"
                        :key="event.id"
                        class="activity-item"
                    >
                        <span></span>
                        <div>
                            <b>{{
                                event.action.replaceAll("_", " ").toLowerCase()
                            }}</b
                            ><small>{{ date(event.created_at, true) }}</small>
                        </div>
                    </div>
                    <p v-if="user.role === 'jurado'">
                        Solo puedes consultar y calificar los expedientes de las
                        plazas que tienes asignadas.
                    </p></template
                ><Link :href="url('/ayuda')" class="text-link"
                    >Consultar guía <Icon name="ArrowUpRight" :size="16"
                /></Link>
            </aside>
        </div>
        <section v-if="calls?.length" class="panel">
            <div class="panel-heading">
                <Icon name="CalendarDays" />
                <h2>Procesos registrados</h2>
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
