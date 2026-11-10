<script setup>
import { ref } from "vue";
import { Link, useForm } from "@inertiajs/vue3";
import AppLayout from "../Layouts/AppLayout.vue";
import Icon from "../Components/Icon.vue";
import Badge from "../Components/Badge.vue";
import Errors from "../Components/Errors.vue";
import { url, date, label } from "../lib/ui";
const props = defineProps({
    call: Object,
    jurors: Array,
    configuredExternalVacancyIds: Array,
    configuredInternalVacancyIds: Array,
    publishedStages: Object,
    reviewed: [Object, Array],
});
const resultLabels = {
    NO_APTO: "No apto",
    NO_SUPERA_EXTERNA: "No alcanza el mínimo externo",
    NO_SUPERA_INTERNA: "No alcanza el mínimo interno",
    NO_SUPERA_TOTAL: "No alcanza el mínimo total",
    SUPERA_MINIMOS: "Supera los mínimos",
};
const next = {
    BORRADOR: { status: "PUBLICADA", label: "Publicar convocatoria" },
    PUBLICADA: { status: "EN_EVALUACION", label: "Iniciar evaluación" },
    EN_EVALUACION: {
        status: "FINALIZADA",
        label: "Finalizar y publicar resultados",
    },
    FINALIZADA: { status: "ARCHIVADA", label: "Archivar convocatoria" },
};
const closeDemoForm = useForm({});
const closingDemo = ref(false);
function closeDemo() {
    closeDemoForm.post(
        url(`/gestion/convocatorias/${props.call.id}/cerrar-prueba`),
        { onSuccess: () => (closingDemo.value = false) },
    );
}
const transition = useForm({ status: next[props.call.status]?.status });
const confirmation = ref(false);
const commission = useForm({ jurors: [] });
const selectedVacancy = ref(null);
const scheduleVacancy = ref(null);
const stagePublication = useForm({ phase: "" });
function publishStage(vacancy, phase) {
    stagePublication.phase = phase;
    stagePublication.post(url(`/gestion/plazas/${vacancy.id}/publicar-etapa`), {
        preserveScroll: true,
    });
}
const calendar = useForm({ schedule: [], reason: "" });
function editCalendar(vacancy) {
    scheduleVacancy.value = vacancy;
    calendar.clearErrors();
    calendar.reason = "";
    calendar.schedule = (vacancy.schedule ?? []).map((event) => ({ ...event }));
}
function saveCalendar() {
    calendar.put(
        url(`/gestion/plazas/${scheduleVacancy.value.id}/cronograma`),
        {
            onSuccess: () => (scheduleVacancy.value = null),
        },
    );
}
function move() {
    transition.status = next[props.call.status].status;
    transition.post(url(`/gestion/convocatorias/${props.call.id}/etapa`), {
        onSuccess: () => (confirmation.value = false),
    });
}
function editJury(v) {
    selectedVacancy.value = v;
    commission.jurors = v.jurors.map((j) => j.id);
}
function saveJury() {
    commission.put(url(`/gestion/plazas/${selectedVacancy.value.id}/jurados`), {
        onSuccess: () => (selectedVacancy.value = null),
    });
}
</script>
<template>
    <AppLayout title="Detalle de convocatoria" :subtitle="call.code"
        ><template #actions
            ><Badge :value="call.status" /><Link
                v-if="call.status === 'BORRADOR'"
                :href="url('/gestion/convocatorias/' + call.id + '/editar')"
                class="btn btn-outline"
                ><Icon name="Settings2" :size="17" />Editar configuración</Link
            ><Link
                v-else-if="['PUBLICADA', 'EN_EVALUACION'].includes(call.status)"
                :href="url('/expedientes?call=' + call.id + '&sort=recent')"
                class="btn btn-outline"
                ><Icon name="FolderOpen" :size="17" />Ver expedientes de esta
                convocatoria</Link
            ></template
        ><Errors
            :errors="{
                ...transition.errors,
                ...commission.errors,
                ...stagePublication.errors,
            }"
        />
        <div
            v-if="
                $page.props.demo && call.is_demo && call.status === 'PUBLICADA'
            "
            class="notice row between wrap"
        >
            <span
                >Para la presentación puedes cerrar la inscripción de este
                proceso de prueba e iniciar la revisión.</span
            ><button
                type="button"
                class="btn btn-outline btn-sm"
                @click="closingDemo = true"
            >
                Cerrar inscripción de prueba
            </button>
        </div>
        <section class="panel call-overview">
            <div>
                <span class="eyebrow">{{
                    call.is_demo
                        ? "PROCESO DE DEMOSTRACIÓN"
                        : "REFERENCIA INSTITUCIONAL"
                }}</span>
                <h2>{{ call.title }}</h2>
                <p>{{ call.description }}</p>
                <div class="card-tags">
                    <span>{{ label(call.modality) }}</span
                    ><span>{{ call.vacancies.length }} plazas</span
                    ><span>{{
                        call.resolution || "Sin resolución registrada"
                    }}</span>
                </div>
            </div>
            <div class="call-dates">
                <small>Inicio de inscripción</small
                ><b>{{ date(call.opens_at, true) }}</b
                ><small>Cierre de inscripción</small
                ><b>{{ date(call.closes_at, true) }}</b>
            </div>
        </section>
        <div class="process-track">
            <div
                v-for="(s, i) in [
                    'BORRADOR',
                    'PUBLICADA',
                    'EN_EVALUACION',
                    'FINALIZADA',
                    'ARCHIVADA',
                ]"
                :key="s"
                :class="{ current: call.status === s }"
            >
                <span>{{ i + 1 }}</span
                >{{ label(s) }}
            </div>
        </div>
        <div v-if="next[call.status]" class="notice row between wrap">
            <span>{{
                call.status === "PUBLICADA"
                    ? "La evaluación puede comenzar después del cierre de inscripción."
                    : call.status === "EN_EVALUACION"
                      ? "Finalizar hace públicos los códigos y puntajes de los expedientes evaluados."
                      : "Revisa las rúbricas, los sustentos, el cronograma y la comisión antes de publicar."
            }}</span
            ><button
                class="btn btn-primary btn-sm"
                @click="confirmation = true"
            >
                {{ next[call.status].label
                }}<Icon name="ArrowRight" :size="16" />
            </button>
        </div>
        <section class="panel">
            <div class="panel-heading between">
                <div class="row">
                    <Icon name="GraduationCap" />
                    <h2>Plazas docentes</h2>
                </div>
                <Link
                    v-if="call.status === 'BORRADOR'"
                    :href="
                        url(
                            '/gestion/convocatorias/' +
                                call.id +
                                '/plazas/nueva',
                        )
                    "
                    class="btn btn-primary btn-sm"
                >
                    <Icon name="Plus" :size="17" /> Crear plaza
                </Link>
            </div>
            <div
                v-for="v in call.vacancies"
                :key="v.id"
                class="managed-vacancy"
            >
                <div class="row between wrap">
                    <div>
                        <small class="eyebrow"
                            >AIRHSP {{ v.airhsp }} · CAP {{ v.cap }}</small
                        >
                        <h3>{{ v.school }}</h3>
                        <p>
                            {{ v.faculty }} · {{ label(v.category) }} ·
                            {{ v.weekly_hours }} horas
                        </p>
                    </div>
                    <Link
                        v-if="call.status !== 'BORRADOR'"
                        :href="url('/plazas/' + v.id)"
                        class="btn btn-outline btn-sm"
                        >Ver ficha <Icon name="ArrowUpRight" :size="15"
                    /></Link>
                </div>
                <div class="row wrap screening-plaza-actions">
                    <template v-if="call.status === 'BORRADOR'">
                        <Link
                            :href="url('/gestion/plazas/' + v.id + '/editar')"
                            class="btn btn-outline btn-sm"
                            >Editar ficha y PDF</Link
                        >
                        <Link
                            :href="
                                url(
                                    '/gestion/plazas/' +
                                        v.id +
                                        '/aspectos-externos',
                                )
                            "
                            class="btn btn-outline btn-sm"
                        >
                            {{
                                configuredExternalVacancyIds.includes(v.id)
                                    ? "Editar aspectos externos"
                                    : "Configurar aspectos externos"
                            }}
                        </Link>
                        <Link
                            :href="
                                url(
                                    '/gestion/plazas/' +
                                        v.id +
                                        '/aspectos-internos',
                                )
                            "
                            class="btn btn-outline btn-sm"
                        >
                            {{
                                configuredInternalVacancyIds.includes(v.id)
                                    ? "Editar aspectos internos"
                                    : "Configurar aspectos internos"
                            }}
                        </Link>
                    </template>
                </div>
                <div class="jury-box">
                    <Icon name="Users" :size="18" /><span class="grow">{{
                        v.jurors.length
                            ? v.jurors.map((j) => j.name).join(" · ")
                            : "Sin jurados asignados"
                    }}</span
                    ><button
                        v-if="['BORRADOR', 'PUBLICADA'].includes(call.status)"
                        @click="editJury(v)"
                        class="text-link"
                    >
                        Asignar comisión
                    </button>
                </div>
                <details v-if="reviewed[v.id]?.length">
                    <summary>
                        Revisar resultados consolidados antes de publicar
                    </summary>
                    <div
                        class="schedule-row"
                        v-for="row in reviewed[v.id]"
                        :key="row.id"
                    >
                        <Link
                            :href="url('/expedientes/' + row.id)"
                            class="text-link"
                            >{{ row.reference }}</Link
                        >
                        <span
                            >{{ resultLabels[row.result] }} ·
                            {{ row.score ?? "Sin puntaje aprobatorio" }}</span
                        >
                    </div>
                </details>
                <button
                    v-if="
                        ['PUBLICADA', 'EN_EVALUACION'].includes(call.status) &&
                        configuredInternalVacancyIds.includes(v.id) &&
                        configuredExternalVacancyIds.includes(v.id)
                    "
                    type="button"
                    class="text-link"
                    @click="editCalendar(v)"
                >
                    Reprogramar evaluación
                </button>
                <div
                    v-if="
                        call.status === 'EN_EVALUACION' &&
                        configuredInternalVacancyIds.includes(v.id) &&
                        configuredExternalVacancyIds.includes(v.id)
                    "
                    class="row wrap"
                >
                    <button
                        v-if="!publishedStages[v.id].includes('aptitude')"
                        type="button"
                        class="btn btn-outline btn-sm"
                        :disabled="stagePublication.processing"
                        @click="publishStage(v, 'aptitude')"
                    >
                        Publicar aptitud
                    </button>
                    <span v-else class="muted">Aptitud publicada</span>
                    <button
                        v-if="
                            publishedStages[v.id].includes('aptitude') &&
                            !publishedStages[v.id].includes('external')
                        "
                        type="button"
                        class="btn btn-outline btn-sm"
                        :disabled="stagePublication.processing"
                        @click="publishStage(v, 'external')"
                    >
                        Publicar aspectos externos
                    </button>
                    <span
                        v-if="publishedStages[v.id].includes('external')"
                        class="muted"
                        >Aspectos externos publicados</span
                    >
                </div>
            </div>
            <div v-if="!call.vacancies.length" class="empty-state compact">
                <Icon name="GraduationCap" :size="34" />
                <h3>Añade las plazas del concurso</h3>
                <p>
                    Cada plaza debe contar con su perfil y sus documentos
                    requeridos.
                </p>
            </div>
        </section>
        <div class="dashboard-columns">
            <section class="panel">
                <div class="panel-heading">
                    <Icon name="CalendarDays" />
                    <h2>Cronograma</h2>
                </div>
                <div
                    v-for="(s, i) in call.schedule"
                    :key="i"
                    class="schedule-row"
                >
                    <strong>{{ s.label }}</strong
                    ><span>{{ s.date }}</span>
                </div>
            </section>
            <section v-if="call.rubric.length" class="panel">
                <div class="panel-heading">
                    <Icon name="ClipboardCheck" />
                    <h2>Rúbrica general de demostración</h2>
                </div>
                <div v-for="r in call.rubric" :key="r.key" class="schedule-row">
                    <span>{{ r.name }}</span
                    ><b>{{ r.max }} pts.</b>
                </div>
                <p v-if="call.rubric.length" class="footnote">
                    Puntajes manuales de demostración. No reemplazan los anexos
                    normativos oficiales.
                </p>
            </section>
        </div>
        <div
            v-if="scheduleVacancy"
            class="modal-backdrop"
            @click.self="scheduleVacancy = null"
        >
            <form class="modal wide-modal" @submit.prevent="saveCalendar">
                <h2>Reprogramar evaluación</h2>
                <p>{{ scheduleVacancy.school }} · Hora de Perú</p>
                <Errors :errors="calendar.errors" />
                <div
                    v-for="event in calendar.schedule.filter(
                        (item) => item.stage,
                    )"
                    :key="event.stage"
                    class="entry-card"
                >
                    <strong>{{ event.label }}</strong>
                    <div class="form-grid">
                        <label
                            >Inicio<input
                                v-model="event.starts_at"
                                type="datetime-local"
                                required
                        /></label>
                        <label
                            >Cierre<input
                                v-model="event.ends_at"
                                type="datetime-local"
                                required
                        /></label>
                    </div>
                    <label
                        >Indicaciones<input
                            v-model="event.detail"
                            maxlength="300"
                    /></label>
                </div>
                <label
                    >Motivo de la reprogramación<textarea
                        v-model="calendar.reason"
                        required
                        minlength="5"
                        maxlength="500"
                    />
                </label>
                <div class="row end">
                    <button
                        type="button"
                        class="btn btn-outline"
                        @click="scheduleVacancy = null"
                    >
                        Cancelar
                    </button>
                    <button
                        class="btn btn-primary"
                        :disabled="calendar.processing"
                    >
                        Guardar cronograma
                    </button>
                </div>
            </form>
        </div>
        <div
            v-if="closingDemo"
            class="modal-backdrop"
            @click.self="closingDemo = false"
        >
            <section class="modal">
                <h2>Cerrar inscripción de prueba</h2>
                <p>
                    Ya no se aceptarán nuevas postulaciones en esta
                    convocatoria. Los expedientes enviados pasarán a estar
                    disponibles para revisión. Esta herramienta solo existe para
                    el entorno local de demostración.
                </p>
                <Errors :errors="closeDemoForm.errors" />
                <div class="row end">
                    <button
                        class="btn btn-outline"
                        @click="closingDemo = false"
                    >
                        Cancelar</button
                    ><button
                        class="btn btn-primary"
                        :disabled="closeDemoForm.processing"
                        @click="closeDemo"
                    >
                        Cerrar e iniciar revisión
                    </button>
                </div>
            </section>
        </div>
        <div
            v-if="selectedVacancy"
            class="modal-backdrop"
            @click.self="selectedVacancy = null"
        >
            <form class="modal" @submit.prevent="saveJury">
                <h2>Asignar comisión</h2>
                <p>{{ selectedVacancy.school }}</p>
                <div v-if="!jurors.length" class="notice">
                    Crea primero los usuarios con rol jurado.
                </div>
                <label
                    v-for="j in jurors"
                    :key="j.id"
                    class="check-label jury-option"
                    ><input
                        type="checkbox"
                        :value="j.id"
                        v-model="commission.jurors"
                    /><span
                        >{{ j.name }}<small>{{ j.email }}</small></span
                    ></label
                ><Errors :errors="commission.errors" />
                <div class="row end">
                    <button
                        type="button"
                        class="btn btn-outline"
                        @click="selectedVacancy = null"
                    >
                        Cancelar</button
                    ><button
                        class="btn btn-primary"
                        :disabled="
                            commission.processing || !commission.jurors.length
                        "
                    >
                        Guardar comisión
                    </button>
                </div>
            </form>
        </div>
        <div
            v-if="confirmation"
            class="modal-backdrop"
            @click.self="confirmation = false"
        >
            <section class="modal">
                <h2>{{ next[call.status].label }}</h2>
                <p>
                    La transición quedará en el registro de actividad. La
                    configuración y las plazas se bloquean después de publicar.
                </p>
                <Errors :errors="transition.errors" />
                <div class="row end">
                    <button
                        class="btn btn-outline"
                        @click="confirmation = false"
                    >
                        Cancelar</button
                    ><button
                        class="btn btn-primary"
                        :disabled="transition.processing"
                        @click="move"
                    >
                        Confirmar cambio
                    </button>
                </div>
            </section>
        </div></AppLayout
    >
</template>
