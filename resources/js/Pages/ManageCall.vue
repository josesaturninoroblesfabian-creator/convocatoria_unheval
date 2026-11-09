<script setup>
import { ref } from "vue";
import { Link, useForm } from "@inertiajs/vue3";
import AppLayout from "../Layouts/AppLayout.vue";
import Icon from "../Components/Icon.vue";
import ScreeningEditor from "../Components/ScreeningEditor.vue";
import { defaultScreeningRules, screeningRecordId } from "../lib/screening";
import Badge from "../Components/Badge.vue";
import Errors from "../Components/Errors.vue";
import { url, date, label } from "../lib/ui";
const props = defineProps({ call: Object, jurors: Array });
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
const editingScreening = ref(null);
const screeningForm = useForm({
    screening_rules: defaultScreeningRules(),
    requirements: [],
});
function editScreening(v) {
    editingScreening.value = v;
    screeningForm.requirements = (v.requirements ?? []).map((requirement) => ({
        ...requirement,
    }));
    screeningForm.clearErrors();
    screeningForm.screening_rules = JSON.parse(
        JSON.stringify(v.screening_rules || defaultScreeningRules()),
    );
}
function saveScreening() {
    screeningForm.put(
        url(`/gestion/plazas/${editingScreening.value.id}/formulario`),
        { onSuccess: () => (editingScreening.value = null) },
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
                      : "Revisa toda la configuración antes de avanzar de etapa."
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
                    <span class="muted">{{
                        v.screening_rules?.enabled
                            ? "Formulario estructurado · mínimo " +
                              v.screening_rules.minimum_experience_months +
                              " meses de experiencia"
                            : v.requirements?.length
                              ? "Revisión de documentos"
                              : "Requisitos de postulación pendientes"
                    }}</span>
                    <template v-if="call.status === 'BORRADOR'">
                        <Link
                            :href="url('/gestion/plazas/' + v.id + '/editar')"
                            class="btn btn-outline btn-sm"
                            >Editar ficha y PDF</Link
                        >
                        <button
                            type="button"
                            class="text-link"
                            @click="editScreening(v)"
                        >
                            Configurar requisitos y filtros
                        </button>
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
            <section class="panel">
                <div class="panel-heading">
                    <Icon name="ClipboardCheck" />
                    <h2>Ficha de evaluación de prueba</h2>
                </div>
                <div v-for="r in call.rubric" :key="r.key" class="schedule-row">
                    <span>{{ r.name }}</span
                    ><b>{{ r.max }} pts.</b>
                </div>
                <p class="footnote">
                    Puntajes manuales de demostración. No reemplazan los anexos
                    normativos oficiales.
                </p>
            </section>
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
            v-if="editingScreening"
            class="modal-backdrop"
            @click.self="editingScreening = null"
        >
            <form class="modal wide-modal" @submit.prevent="saveScreening">
                <h2>Requisitos y filtros de {{ editingScreening.school }}</h2>
                <Errors :errors="screeningForm.errors" /><ScreeningEditor
                    v-model="screeningForm.screening_rules"
                />
                <h3>Documentos que debe presentar el postulante</h3>
                <p class="muted">
                    Añade los sustentos solicitados por las bases. Los sustentos
                    generados por el formulario estructurado se solicitan por
                    separado.
                </p>
                <div
                    v-for="(requirement, index) in screeningForm.requirements"
                    :key="requirement.key"
                    class="entry-card"
                >
                    <label
                        >Documento
                        <input
                            v-model="requirement.label"
                            required
                            maxlength="1000"
                    /></label>
                    <label class="check-label"
                        ><input
                            v-model="requirement.required"
                            type="checkbox"
                        />
                        Obligatorio</label
                    >
                    <button
                        type="button"
                        class="text-link"
                        @click="screeningForm.requirements.splice(index, 1)"
                    >
                        Quitar
                    </button>
                </div>
                <button
                    v-if="screeningForm.requirements.length < 30"
                    type="button"
                    class="btn btn-outline btn-sm"
                    @click="
                        screeningForm.requirements.push({
                            key:
                                'req_' +
                                screeningRecordId().replaceAll('-', ''),
                            label: '',
                            required: true,
                        })
                    "
                >
                    Añadir documento requerido
                </button>
                <div class="row end">
                    <button
                        type="button"
                        class="btn btn-outline"
                        @click="editingScreening = null"
                    >
                        Cancelar</button
                    ><button
                        class="btn btn-primary"
                        :disabled="screeningForm.processing"
                    >
                        Guardar reglas
                    </button>
                </div>
            </form>
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
