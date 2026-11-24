<script setup>
import { computed, nextTick, ref, watch } from "vue";
import { Link, useForm, usePage } from "@inertiajs/vue3";
import AppLayout from "../Layouts/AppLayout.vue";
import ApplicationDocuments from "../Components/ApplicationDocuments.vue";
import Badge from "../Components/Badge.vue";
import Errors from "../Components/Errors.vue";
import Icon from "../Components/Icon.vue";
import StructuredApplicationForm from "../Components/StructuredApplicationForm.vue";
import { date, label, shortRef, url } from "../lib/ui";
import { useApplicationWindow } from "../lib/useApplicationWindow";
import { screeningRecordId } from "../lib/screening";

const props = defineProps({
    application: Object,
    screening: Object,
    documentRequirements: Array,
    curriculumIndex: Array,
    profileCredentials: Array,
    meritCriteria: Object,
    evaluationStatus: Object,
    appeals: Array,
    appealEligibility: Object,
});
const page = usePage();
const isOpen = useApplicationWindow();
const path = computed(() => "/mis-postulaciones/" + props.application.id);
const profileDirty = ref(false);
const applicant = computed(
    () => props.application.applicant_snapshot ?? page.props.auth.user,
);
const contactComplete = computed(() =>
    Boolean(applicant.value.document_number && applicant.value.phone),
);
const profileReady = computed(
    () =>
        contactComplete.value &&
        props.screening.eligible &&
        !profileDirty.value,
);
const editable = computed(
    () =>
        props.application.status === "BORRADOR" &&
        isOpen(props.application.recruitment_call),
);
const documents = computed(() =>
    Object.fromEntries(
        props.application.documents.map((item) => [item.requirement_key, item]),
    ),
);
const sections = {
    administrativos: {
        title: "Fólder A",
        description: "Solicitud y documentos administrativos",
        step: 2,
    },
    curriculo: {
        title: "Fólder B",
        description: "Sustentos del perfil y currículo",
        step: 3,
    },
    meritos: {
        title: "Fólder B",
        description: "Méritos y aspectos externos",
        step: 3,
    },
    internos: {
        title: "Fólder B",
        description: "Sílabos para aspectos internos",
        step: 3,
    },
};
const groupedDocuments = computed(() =>
    Object.fromEntries(
        Object.keys(sections).map((section) => [
            section,
            props.documentRequirements.filter(
                (requirement) => requirement.section === section,
            ),
        ]),
    ),
);
const missingFolderA = computed(() =>
    groupedDocuments.value.administrativos.filter(
        (requirement) =>
            requirement.required && !documents.value[requirement.key],
    ),
);
const missingRequirements = computed(() =>
    props.documentRequirements.filter(
        (requirement) =>
            requirement.required && !documents.value[requirement.key],
    ),
);
const unresolvedMerits = computed(() =>
    props.curriculumIndex.filter(
        (entry) =>
            !documents.value[entry.document_key] &&
            !props.documentRequirements.some(
                (requirement) =>
                    requirement.key === entry.document_key &&
                    requirement.required,
            ),
    ),
);
const folderBReady = computed(
    () =>
        missingRequirements.value.length === 0 &&
        unresolvedMerits.value.length === 0,
);
const savedMerits = () =>
    (props.application.form_data?.merits ?? []).map((entry) => ({ ...entry }));
const merits = useForm({ merits: savedMerits() });
const appealForm = useForm({ type: "", stage: "", grounds: "", evidence: "" });
const appealLabels = {
    reconsideracion: "Reconsideración",
    apelacion: "Apelación",
};
const stageLabels = {
    aptitude: "Resultados de aptitud",
    external: "Aspectos externos",
};
const appealStatusLabels = { PENDIENTE: "Pendiente", RESUELTA: "Resuelta" };
function fileAppeal() {
    appealForm.post(url(path.value + "/impugnaciones"), {
        preserveScroll: true,
        onSuccess: () => appealForm.reset(),
    });
}
const canFileReconsideracion = computed(
    () =>
        props.appealEligibility?.reconsideracion.aptitude ||
        props.appealEligibility?.reconsideracion.external ||
        false,
);
const canFileApelacion = computed(
    () =>
        props.appealEligibility?.apelacion.aptitude ||
        props.appealEligibility?.apelacion.external ||
        false,
);
const availableStages = computed(() => {
    if (!appealForm.type) return [];
    return Object.entries(props.appealEligibility?.[appealForm.type] ?? {})
        .filter(([, ok]) => ok)
        .map(([stage]) => stage);
});
const groupedMeritCriteria = computed(() => {
    const groups = new Map();
    for (const [key, criterion] of Object.entries(props.meritCriteria)) {
        if (!groups.has(criterion.rubric)) {
            groups.set(criterion.rubric, {
                key: criterion.rubric,
                name: criterion.rubric_name,
                max: criterion.rubric_max,
                items: [],
            });
        }
        groups.get(criterion.rubric).items.push({ key, ...criterion });
    }
    return Array.from(groups.values());
});
const reusableDocuments = computed(() =>
    [
        ...groupedDocuments.value.curriculo,
        ...groupedDocuments.value.administrativos,
    ].filter((item) => item.key !== "bonificacion_discapacidad"),
);
const reusableMerits = computed(() => {
    const data = props.application.form_data ?? {};
    const degreeCriteria = {
        doctor: "formacion:doctor",
        maestro: "formacion:maestro",
        bachiller: "formacion:bachiller",
        titulo: "formacion:titulo",
        segunda_especialidad: "formacion_servicio:especialidad",
    };
    const usedKeys = new Set(merits.merits.map((entry) => entry.reuse));
    return [
        ...(data.credentials ?? []).map((item) => ({
            criterion: degreeCriteria[item.kind],
            description: item.name + " · " + item.institution,
            reuse: "acad_" + item.id,
            quantity: 1,
        })),
        ...(data.experience ?? []).map((item) => ({
            criterion:
                item.type === "docente"
                    ? "experiencia_universitaria:docente"
                    : "experiencia_extrauniversitaria:anio",
            description: item.position + " · " + item.institution,
            reuse: "exp_" + item.id,
            quantity: null,
        })),
    ].filter(
        (entry) =>
            props.meritCriteria[entry.criterion] && !usedKeys.has(entry.reuse),
    );
});
watch(
    () => props.application.form_data?.merits,
    () => {
        if (!merits.isDirty) {
            merits.merits = savedMerits();
            merits.defaults();
        }
    },
);
const upload = useForm({ requirement_key: "", file: null });
const send = useForm({
    statement: props.application.statement ?? "",
    consent: Boolean(props.application.consented_at),
    confirm: false,
});
const processing = computed(
    () => upload.processing || merits.processing || send.processing,
);
const ready = computed(
    () =>
        editable.value &&
        profileReady.value &&
        folderBReady.value &&
        !merits.isDirty &&
        !processing.value,
);
const pending = computed(
    () => missingRequirements.value.length + unresolvedMerits.value.length,
);
const steps = ["Datos y perfil", "Fólder A", "Fólder B", "Revisión y envío"];
const maxStep = computed(() => {
    if (!editable.value) return 4;
    if (!profileReady.value) return 1;
    if (missingFolderA.value.length) return 2;
    return 4;
});
const step = ref(1);
if (
    props.application.form_data ||
    props.application.documents.length ||
    props.application.consented_at
) {
    if (profileReady.value) {
        step.value = missingFolderA.value.length
            ? 2
            : folderBReady.value
              ? 4
              : 3;
    }
}
const stepHeading = ref(null);
const focusStep = () => nextTick(() => stepHeading.value?.focus());
function navigate(target) {
    if (target > maxStep.value || processing.value) return;
    step.value = target;
    focusStep();
}
function addMerit() {
    merits.merits.push({
        id: screeningRecordId(),
        criterion: "",
        description: "",
        quantity: 1,
        reuse: null,
    });
}
function importMerits() {
    for (const entry of reusableMerits.value.slice(
        0,
        150 - merits.merits.length,
    )) {
        merits.merits.push({ id: screeningRecordId(), ...entry });
    }
}
function saveMerits() {
    merits.put(url(path.value + "/meritos"), { preserveScroll: true });
}
function selectFile(key, file) {
    if (processing.value) return;
    upload.requirement_key = key;
    upload.file = file;
    upload.post(url(path.value + "/documentos"), {
        preserveScroll: true,
        onSuccess: () => upload.reset(),
    });
}
function submit() {
    if (!ready.value || !send.confirm || !send.consent) return;
    send.post(url(path.value + "/enviar"), {
        preserveScroll: true,
        onSuccess: focusStep,
    });
}
const localDate = (value) => date(value + "-05:00", true);
const events = computed(() =>
    [...(props.application.vacancy.schedule ?? [])].sort((left, right) =>
        left.starts_at.localeCompare(right.starts_at),
    ),
);
const notices = computed(() =>
    events.value.filter((event) => {
        if (event.purpose === "citacion")
            return props.evaluationStatus?.advanced;
        if (event.purpose === "reclamo")
            return props.application.status !== "RECIBIDA";
        return false;
    }),
);
const generalEvents = computed(() =>
    events.value.filter((event) => !event.purpose),
);
const finalStatus = {
    NO_SUPERA_EXTERNA: "No alcanzaste el mínimo de aspectos externos",
    NO_SUPERA_INTERNA: "No alcanzaste el mínimo de aspectos internos",
    NO_SUPERA_TOTAL: "No alcanzaste el puntaje total mínimo",
    SUPERA_MINIMOS: "Alcanzaste los mínimos de evaluación",
};
</script>

<template>
    <AppLayout
        :title="
            application.submitted_at
                ? 'Mi expediente'
                : 'Prepara tu postulación'
        "
        :subtitle="application.vacancy.school"
    >
        <template #actions><Badge :value="application.status" /></template>

        <div v-if="application.recruitment_call.is_demo" class="demo-notice">
            <Icon name="CircleAlert" :size="18" /> Expediente de demostración.
        </div>

        <nav
            v-if="!application.submitted_at"
            aria-label="Pasos de la postulación"
            class="mb-5 grid grid-cols-2 gap-2 sm:grid-cols-4"
        >
            <button
                v-for="(title, index) in steps"
                :key="title"
                type="button"
                class="btn min-w-0 justify-start text-left"
                :class="step === index + 1 ? 'btn-primary' : 'btn-outline'"
                :aria-current="step === index + 1 ? 'step' : undefined"
                :disabled="index + 1 > maxStep || processing"
                @click="navigate(index + 1)"
            >
                <span aria-hidden="true">{{ index + 1 }}</span> {{ title }}
            </button>
        </nav>

        <div class="detail-grid application-grid">
            <div class="min-w-0">
                <Errors :errors="{ ...upload.errors, ...send.errors }" />

                <section v-if="application.submitted_at" class="panel">
                    <div class="panel-heading">
                        <Icon name="CheckCircle2" />
                        <h2 ref="stepHeading" tabindex="-1">
                            Expediente recibido
                        </h2>
                    </div>
                    <p>
                        {{ date(application.submitted_at, true) }} · Hora de
                        Perú
                    </p>
                    <p class="mono break-all">{{ application.reference }}</p>
                    <div class="flex flex-wrap gap-2 py-3">
                        <a
                            :href="url(path + '/cargo')"
                            target="_blank"
                            rel="noopener"
                            class="btn btn-primary"
                        >
                            <Icon name="Download" :size="17" /> Ver cargo de
                            recepción
                        </a>
                        <a
                            :href="url(path + '/curriculo')"
                            target="_blank"
                            rel="noopener"
                            class="btn btn-outline"
                        >
                            <Icon name="FileText" :size="17" /> Ver CV e índice
                        </a>
                    </div>
                    <section class="form-section">
                        <h3 class="mb-3">Seguimiento</h3>
                        <div class="timeline">
                            <div>
                                <span></span>
                                <div>
                                    <strong>Aptitud</strong>
                                    <p v-if="application.status === 'RECIBIDA'">
                                        Pendiente de publicación.
                                    </p>
                                    <p v-else>
                                        {{
                                            application.status === "NO_APTO"
                                                ? "No apto"
                                                : "Apto"
                                        }}
                                    </p>
                                    <p
                                        v-if="application.review_notes"
                                        class="whitespace-pre-line"
                                    >
                                        {{ application.review_notes }}
                                    </p>
                                </div>
                            </div>
                            <div
                                v-if="
                                    evaluationStatus &&
                                    application.status !== 'NO_APTO'
                                "
                            >
                                <span></span>
                                <div>
                                    <strong>Aspectos externos</strong>
                                    <p
                                        v-if="
                                            evaluationStatus.externalScore !==
                                            null
                                        "
                                    >
                                        {{ evaluationStatus.externalScore }} /
                                        {{ evaluationStatus.externalMaximum }}
                                        pts ·
                                        {{
                                            evaluationStatus.advanced
                                                ? "Pasa a aspectos internos"
                                                : "No alcanzó el mínimo externo"
                                        }}
                                    </p>
                                    <p v-else>Pendiente de publicación.</p>
                                    <p
                                        v-if="evaluationStatus.externalNotes"
                                        class="whitespace-pre-line"
                                    >
                                        {{ evaluationStatus.externalNotes }}
                                    </p>
                                    <details
                                        v-if="
                                            evaluationStatus.externalPublished &&
                                            curriculumIndex.length
                                        "
                                        class="mt-2"
                                    >
                                        <summary>Méritos acreditados</summary>
                                        <div
                                            v-for="entry in curriculumIndex"
                                            :key="entry.id"
                                            class="schedule-row"
                                        >
                                            <span>{{ entry.description }}</span>
                                            <b
                                                >{{
                                                    evaluationStatus
                                                        .accreditedMerits[
                                                        entry.id
                                                    ] ?? "Sin dato"
                                                }}
                                                de {{ entry.quantity }}</b
                                            >
                                        </div>
                                    </details>
                                </div>
                            </div>
                            <div v-if="evaluationStatus?.advanced">
                                <span></span>
                                <div>
                                    <strong>Aspectos internos</strong>
                                    <p
                                        v-if="
                                            evaluationStatus.result?.internal !=
                                            null
                                        "
                                    >
                                        {{ evaluationStatus.result.internal }}
                                        pts
                                    </p>
                                    <template
                                        v-else-if="
                                            evaluationStatus.calendar.internal
                                        "
                                    >
                                        <p>
                                            Del
                                            {{
                                                localDate(
                                                    evaluationStatus.calendar
                                                        .internal.starts_at,
                                                )
                                            }}
                                            al
                                            {{
                                                localDate(
                                                    evaluationStatus.calendar
                                                        .internal.ends_at,
                                                )
                                            }}.
                                        </p>
                                        <p class="whitespace-pre-line">
                                            {{
                                                evaluationStatus.calendar
                                                    .internal.detail
                                            }}
                                        </p>
                                    </template>
                                </div>
                            </div>
                            <div
                                v-if="
                                    evaluationStatus?.result ||
                                    application.recruitment_call
                                        .results_published_at
                                "
                            >
                                <span></span>
                                <div>
                                    <strong>Resultado final</strong>
                                    <p v-if="evaluationStatus?.result">
                                        {{
                                            finalStatus[
                                                evaluationStatus.result.stage
                                            ]
                                        }}
                                        <template
                                            v-if="
                                                evaluationStatus.result.final !=
                                                null
                                            "
                                        >
                                            ·
                                            {{ evaluationStatus.result.final }}
                                            pts</template
                                        >
                                    </p>
                                    <Link
                                        :href="url('/resultados')"
                                        class="text-link"
                                        >Ver resultados publicados</Link
                                    >
                                </div>
                            </div>
                        </div>
                        <div
                            v-for="notice in notices"
                            :key="notice.label + notice.starts_at"
                            class="notice"
                        >
                            <strong>{{ notice.label }}</strong>
                            <p>
                                {{ localDate(notice.starts_at)
                                }}<template v-if="notice.ends_at">
                                    al {{ localDate(notice.ends_at) }}</template
                                >
                            </p>
                            <p class="whitespace-pre-line break-words">
                                {{ notice.detail }}
                            </p>
                        </div>
                        <details
                            v-if="
                                generalEvents.length ||
                                application.recruitment_call.schedule?.length
                            "
                        >
                            <summary>Cronograma de la plaza</summary>
                            <div
                                v-for="event in generalEvents"
                                :key="event.label + event.starts_at"
                                class="schedule-row"
                            >
                                <div>
                                    <b>{{ event.label }}</b>
                                    <p
                                        v-if="event.detail"
                                        class="whitespace-pre-line"
                                    >
                                        {{ event.detail }}
                                    </p>
                                </div>
                                <span
                                    >{{ localDate(event.starts_at)
                                    }}<template v-if="event.ends_at">
                                        al
                                        {{ localDate(event.ends_at) }}</template
                                    ></span
                                >
                            </div>
                            <template v-if="!generalEvents.length">
                                <div
                                    v-for="event in application.recruitment_call
                                        .schedule"
                                    :key="event.label"
                                    class="schedule-row"
                                >
                                    <span>{{ event.label }}</span
                                    ><b>{{ event.date }}</b>
                                </div>
                            </template>
                        </details>
                    </section>
                    <section class="form-section">
                        <h3>Documentos del expediente</h3>
                        <details
                            v-for="(group, key) in groupedDocuments"
                            v-show="group.length"
                            :key="key"
                        >
                            <summary>
                                {{ sections[key].title }} ·
                                {{ sections[key].description }}
                            </summary>
                            <ApplicationDocuments
                                :requirements="group"
                                :documents="documents"
                                :show-verdicts="
                                    application.status !== 'RECIBIDA'
                                "
                            />
                        </details>
                    </section>
                    <section
                        v-if="
                            appeals?.length ||
                            canFileReconsideracion ||
                            canFileApelacion
                        "
                        class="form-section"
                    >
                        <h3>Recursos impugnatorios</h3>
                        <p class="muted">
                            Puedes presentar una reconsideración ante la
                            comisión o una apelación ante el consejo dentro del
                            plazo desde la publicación de resultados.
                        </p>
                        <div
                            v-for="appeal in appeals"
                            :key="appeal.id"
                            class="schedule-row"
                        >
                            <div>
                                <b
                                    >{{ appealLabels[appeal.type] }} ·
                                    {{ stageLabels[appeal.stage] }}</b
                                >
                                <small class="block muted">{{
                                    appeal.grounds
                                }}</small>
                                <small v-if="appeal.decision" class="block"
                                    >Resolución: {{ appeal.decision }}</small
                                >
                            </div>
                            <Badge :value="appeal.status" />
                        </div>
                        <form
                            v-if="canFileReconsideracion || canFileApelacion"
                            class="appeal-form"
                            @submit.prevent="fileAppeal"
                        >
                            <Errors :errors="appealForm.errors" />
                            <div class="form-grid">
                                <label
                                    >Tipo de recurso
                                    <select
                                        v-model="appealForm.type"
                                        required
                                        @change="appealForm.stage = ''"
                                    >
                                        <option value="" disabled>
                                            Selecciona el recurso
                                        </option>
                                        <option
                                            v-if="canFileReconsideracion"
                                            value="reconsideracion"
                                        >
                                            Reconsideración (ante la comisión)
                                        </option>
                                        <option
                                            v-if="canFileApelacion"
                                            value="apelacion"
                                        >
                                            Apelación (ante el consejo)
                                        </option>
                                    </select>
                                </label>
                                <label
                                    >Etapa que impugnas
                                    <select v-model="appealForm.stage" required>
                                        <option value="" disabled>
                                            Selecciona la etapa
                                        </option>
                                        <option
                                            v-for="stage in availableStages"
                                            :key="stage"
                                            :value="stage"
                                        >
                                            {{ stageLabels[stage] }}
                                        </option>
                                    </select>
                                </label>
                            </div>
                            <label
                                >Fundamentos de la impugnación
                                <textarea
                                    v-model="appealForm.grounds"
                                    required
                                    minlength="10"
                                    maxlength="3000"
                                    rows="4"
                                ></textarea>
                            </label>
                            <label
                                >Pruebas nuevas o documentación adicional
                                <small>Opcional</small>
                                <textarea
                                    v-model="appealForm.evidence"
                                    maxlength="3000"
                                    rows="2"
                                ></textarea>
                            </label>
                            <button
                                class="btn btn-primary"
                                :disabled="appealForm.processing"
                            >
                                Presentar recurso
                            </button>
                        </form>
                    </section>
                </section>

                <section v-else-if="step === 1" class="panel">
                    <div class="panel-heading">
                        <Icon name="Users" />
                        <h2 ref="stepHeading" tabindex="-1">
                            Datos y perfil de la plaza
                        </h2>
                    </div>
                    <div class="info-grid">
                        <div>
                            <small>Postulante</small><b>{{ applicant.name }}</b>
                        </div>
                        <div>
                            <small>DNI</small
                            ><b>{{
                                applicant.document_number || "Pendiente"
                            }}</b>
                        </div>
                        <div>
                            <small>Correo</small><b>{{ applicant.email }}</b>
                        </div>
                        <div>
                            <small>Teléfono</small
                            ><b>{{ applicant.phone || "Pendiente" }}</b>
                        </div>
                    </div>
                    <p v-if="!contactComplete" class="notice">
                        Completa tu DNI y teléfono para continuar.
                    </p>
                    <Link
                        v-if="editable"
                        :href="url('/perfil')"
                        class="text-link"
                    >
                        Actualizar mis datos
                        <Icon name="ArrowUpRight" :size="15" />
                    </Link>
                    <StructuredApplicationForm
                        v-if="screening.enabled"
                        v-show="contactComplete || !editable"
                        :application="application"
                        :report="screening"
                        :profile-credentials="profileCredentials"
                        :editable="editable"
                        @dirty="profileDirty = $event"
                        @continue="navigate(2)"
                    />
                    <div v-else class="row end form-section">
                        <button
                            type="button"
                            class="btn btn-primary"
                            :disabled="!contactComplete && editable"
                            @click="navigate(2)"
                        >
                            Continuar al Fólder A
                            <Icon name="ArrowRight" :size="16" />
                        </button>
                    </div>
                </section>

                <section v-else-if="step === 2" class="panel">
                    <div class="panel-heading">
                        <Icon name="FolderOpen" />
                        <h2 ref="stepHeading" tabindex="-1">
                            Fólder A · Solicitud y declaraciones
                        </h2>
                    </div>
                    <p v-if="groupedDocuments.administrativos.length">
                        Adjunta un PDF de hasta 5 MB por documento. Usa los
                        formatos de las bases y firma donde se indique.
                    </p>
                    <p v-else>
                        Esta plaza no ha configurado documentos administrativos
                        adicionales.
                    </p>
                    <ApplicationDocuments
                        :requirements="groupedDocuments.administrativos"
                        :documents="documents"
                        :editable="editable"
                        :uploading="
                            upload.processing ? upload.requirement_key : ''
                        "
                        :request-path="path + '/solicitud'"
                        @select-file="selectFile"
                    />
                    <div class="row between wrap form-section">
                        <button
                            type="button"
                            class="btn btn-outline"
                            @click="navigate(1)"
                        >
                            Anterior
                        </button>
                        <div class="flex flex-wrap items-center gap-3">
                            <small
                                v-if="missingFolderA.length && editable"
                                aria-live="polite"
                                >{{
                                    missingFolderA.length === 1
                                        ? "Falta un documento obligatorio."
                                        : `Faltan ${missingFolderA.length} documentos obligatorios.`
                                }}</small
                            >
                            <button
                                type="button"
                                class="btn btn-primary"
                                :disabled="maxStep < 3 || processing"
                                @click="navigate(3)"
                            >
                                Continuar al Fólder B
                                <Icon name="ArrowRight" :size="16" />
                            </button>
                        </div>
                    </div>
                </section>

                <section v-else-if="step === 3" class="panel">
                    <div class="panel-heading">
                        <Icon name="BookOpen" />
                        <h2 ref="stepHeading" tabindex="-1">
                            Fólder B · Currículo documentado
                        </h2>
                    </div>
                    <p
                        v-if="
                            !documentRequirements.some(
                                (item) => item.section !== 'administrativos',
                            ) && !groupedMeritCriteria.length
                        "
                    >
                        Esta plaza no ha configurado sustentos adicionales para
                        el currículo.
                    </p>
                    <section
                        v-if="groupedDocuments.curriculo.length"
                        class="form-section"
                    >
                        <h3>Sustentos del perfil declarado</h3>
                        <p>
                            Adjunta un PDF de hasta 5 MB por sustento. Puedes
                            reutilizarlo al registrar un mérito.
                        </p>
                        <ApplicationDocuments
                            :requirements="groupedDocuments.curriculo"
                            :documents="documents"
                            :editable="editable"
                            :uploading="
                                upload.processing ? upload.requirement_key : ''
                            "
                            @select-file="selectFile"
                        />
                    </section>
                    <section
                        v-if="groupedMeritCriteria.length"
                        class="form-section"
                    >
                        <h3>Aspectos externos · Méritos</h3>
                        <form v-if="editable" @submit.prevent="saveMerits">
                            <p>
                                Elige el criterio y guarda cada mérito en la
                                lista. Si necesita un archivo propio, adjúntalo
                                en el índice que aparece después de guardar.
                            </p>
                            <Errors :errors="merits.errors" />
                            <fieldset :disabled="processing">
                                <div
                                    v-for="(merit, index) in merits.merits"
                                    :key="merit.id"
                                    class="entry-card"
                                >
                                    <label
                                        >Criterio del anexo
                                        <select
                                            v-model="merit.criterion"
                                            required
                                        >
                                            <option value="" disabled>
                                                Selecciona un criterio
                                            </option>
                                            <optgroup
                                                v-for="group in groupedMeritCriteria"
                                                :key="group.key"
                                                :label="group.name"
                                            >
                                                <option
                                                    v-for="criterion in group.items"
                                                    :key="criterion.key"
                                                    :value="criterion.key"
                                                >
                                                    {{ criterion.name }} ·
                                                    {{ criterion.points }} pts
                                                    ({{ criterion.unit }})
                                                </option>
                                            </optgroup>
                                        </select>
                                    </label>
                                    <label
                                        >Descripción del mérito<input
                                            v-model="merit.description"
                                            required
                                            maxlength="500"
                                    /></label>
                                    <div class="form-grid">
                                        <label
                                            >Cantidad<input
                                                v-model.number="merit.quantity"
                                                type="number"
                                                min="1"
                                                max="1000"
                                                required
                                        /></label>
                                        <label
                                            >Sustento
                                            <select v-model="merit.reuse">
                                                <option :value="null">
                                                    Adjuntar PDF propio
                                                </option>
                                                <option
                                                    v-for="item in reusableDocuments"
                                                    :key="item.key"
                                                    :value="item.key"
                                                >
                                                    Reutilizar {{ item.label }}
                                                </option>
                                            </select>
                                        </label>
                                    </div>
                                    <button
                                        type="button"
                                        class="text-link"
                                        @click="merits.merits.splice(index, 1)"
                                    >
                                        Quitar mérito
                                    </button>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-if="merits.merits.length < 150"
                                        type="button"
                                        class="btn btn-outline"
                                        @click="addMerit"
                                    >
                                        Añadir mérito
                                    </button>
                                    <button
                                        v-if="
                                            reusableMerits.length &&
                                            merits.merits.length < 150
                                        "
                                        type="button"
                                        class="btn btn-outline"
                                        @click="importMerits"
                                    >
                                        Traer formación y experiencia del perfil
                                    </button>
                                    <button
                                        type="submit"
                                        class="btn btn-primary"
                                        :disabled="!merits.isDirty"
                                    >
                                        Guardar méritos
                                    </button>
                                </div>
                            </fieldset>
                        </form>
                        <p class="notice">
                            Puntaje preliminar de los méritos guardados
                            <strong
                                >{{
                                    Number(
                                        application.declared_score ?? 0,
                                    ).toFixed(2)
                                }}
                                / {{ evaluationStatus.externalMaximum }}</strong
                            >. La comisión verifica los sustentos y determina el
                            puntaje.
                        </p>
                        <template v-if="curriculumIndex.length">
                            <h4>Índice del CV</h4>
                            <div
                                v-for="entry in curriculumIndex"
                                :key="entry.id"
                                class="entry-card"
                            >
                                <small
                                    >{{ entry.rubric }} ·
                                    {{ entry.criterion }}</small
                                >
                                <p>
                                    <b>{{ entry.description }}</b> · Cantidad
                                    {{ entry.quantity }}
                                </p>
                                <ApplicationDocuments
                                    v-if="
                                        groupedDocuments.meritos.some(
                                            (item) =>
                                                item.key === entry.document_key,
                                        )
                                    "
                                    :requirements="
                                        groupedDocuments.meritos.filter(
                                            (item) =>
                                                item.key === entry.document_key,
                                        )
                                    "
                                    :documents="documents"
                                    :editable="editable && !merits.isDirty"
                                    :uploading="
                                        upload.processing
                                            ? upload.requirement_key
                                            : ''
                                    "
                                    @select-file="selectFile"
                                />
                                <a
                                    v-else-if="documents[entry.document_key]"
                                    :href="
                                        url(
                                            '/documentos/' +
                                                documents[entry.document_key]
                                                    .id,
                                        )
                                    "
                                    target="_blank"
                                    rel="noopener"
                                    class="text-link break-all"
                                >
                                    {{ documents[entry.document_key].name }}
                                    <Icon name="ExternalLink" :size="13" />
                                </a>
                                <p v-else class="muted">
                                    Sustento vinculado pendiente. Cárgalo en su
                                    sección o selecciona un PDF propio.
                                </p>
                            </div>
                        </template>
                    </section>
                    <section
                        class="form-section"
                        v-if="
                            groupedDocuments.internos.length || evaluationStatus
                        "
                    >
                        <h3>Aspectos internos · Sílabos</h3>
                        <p v-if="groupedDocuments.internos.length">
                            Adjunta los sílabos solicitados. La clase modelo y
                            la entrevista se realizan según la programación de
                            la plaza.
                        </p>
                        <p v-else>
                            La plaza no solicita adjuntar sílabos durante la
                            inscripción. Consulta la programación de la clase
                            modelo y la entrevista en el cronograma.
                        </p>
                        <ApplicationDocuments
                            :requirements="groupedDocuments.internos"
                            :documents="documents"
                            :editable="editable"
                            :uploading="
                                upload.processing ? upload.requirement_key : ''
                            "
                            @select-file="selectFile"
                        />
                    </section>
                    <div class="row between wrap form-section">
                        <button
                            type="button"
                            class="btn btn-outline"
                            @click="navigate(2)"
                        >
                            Anterior
                        </button>
                        <div class="flex flex-wrap gap-2">
                            <a
                                :href="url(path + '/curriculo')"
                                target="_blank"
                                rel="noopener"
                                class="btn btn-outline"
                                :class="{ disabled: merits.isDirty }"
                                v-if="!merits.isDirty"
                            >
                                <Icon name="FileText" :size="16" /> Ver CV e
                                índice
                            </a>
                            <button
                                type="button"
                                class="btn btn-primary"
                                :disabled="merits.isDirty || processing"
                                @click="navigate(4)"
                            >
                                Revisar expediente
                                <Icon name="ArrowRight" :size="16" />
                            </button>
                        </div>
                    </div>
                    <p v-if="merits.isDirty" class="notice">
                        Guarda los cambios de los méritos para continuar.
                    </p>
                </section>

                <section v-else class="panel">
                    <div class="panel-heading">
                        <Icon name="ClipboardCheck" />
                        <h2 ref="stepHeading" tabindex="-1">
                            Revisión y envío
                        </h2>
                    </div>
                    <p v-if="!editable" class="notice notice-error">
                        La inscripción está cerrada. El borrador quedó sin
                        enviar.
                    </p>
                    <div class="form-section">
                        <h3>Documentos por fólder</h3>
                        <p v-if="pending" class="notice" aria-live="polite">
                            {{
                                pending === 1
                                    ? "Queda un sustento obligatorio pendiente."
                                    : `Quedan ${pending} sustentos obligatorios pendientes.`
                            }}
                        </p>
                        <details
                            v-for="(group, key) in groupedDocuments"
                            v-show="group.length"
                            :key="key"
                            :open="
                                group.some(
                                    (item) =>
                                        item.required && !documents[item.key],
                                )
                            "
                        >
                            <summary>
                                {{ sections[key].title }} ·
                                {{ sections[key].description }}
                            </summary>
                            <ApplicationDocuments
                                :requirements="group"
                                :documents="documents"
                            />
                            <button
                                type="button"
                                class="text-link"
                                @click="navigate(sections[key].step)"
                            >
                                Ir a {{ sections[key].title }}
                            </button>
                        </details>
                        <p v-for="entry in unresolvedMerits" :key="entry.id">
                            Sustento pendiente para {{ entry.description }}.
                        </p>
                        <button
                            v-if="merits.isDirty"
                            type="button"
                            class="text-link"
                            @click="navigate(3)"
                        >
                            Regresa al Fólder B para guardar los cambios de los
                            méritos
                        </button>
                    </div>
                    <form v-if="editable" @submit.prevent="submit">
                        <fieldset :disabled="processing">
                            <label
                                >Información complementaria (opcional)<textarea
                                    v-model="send.statement"
                                    rows="3"
                                    maxlength="4000"
                                ></textarea>
                            </label>
                            <label class="check-label consent">
                                <input v-model="send.consent" type="checkbox" />
                                <span
                                    >Declaro que la información y los documentos
                                    son veraces. Autorizo el tratamiento de mis
                                    datos para esta postulación y he leído la
                                    <a
                                        :href="url('/ayuda#privacidad')"
                                        target="_blank"
                                        rel="noopener"
                                        class="text-link"
                                        >información de privacidad</a
                                    >.</span
                                >
                            </label>
                            <label class="check-label">
                                <input v-model="send.confirm" type="checkbox" />
                                <span
                                    >Confirmo el envío de ambos fólderes. Una
                                    vez recibido el expediente, se cerrará la
                                    edición.</span
                                >
                            </label>
                        </fieldset>
                        <div class="row between wrap form-section">
                            <button
                                type="button"
                                class="btn btn-outline"
                                :disabled="processing"
                                @click="navigate(3)"
                            >
                                Volver al Fólder B
                            </button>
                            <button
                                type="submit"
                                class="btn btn-primary"
                                :disabled="
                                    !ready || !send.consent || !send.confirm
                                "
                            >
                                {{
                                    send.processing
                                        ? "Enviando…"
                                        : "Enviar expediente"
                                }}
                                <Icon name="ArrowRight" :size="16" />
                            </button>
                        </div>
                    </form>
                </section>
            </div>

            <aside>
                <section class="panel sticky-card">
                    <h3>{{ application.vacancy.school }}</h3>
                    <p>{{ application.vacancy.faculty }}</p>
                    <div class="summary-list">
                        <div>
                            <small>Modalidad y categoría</small
                            ><b
                                >{{
                                    label(application.recruitment_call.modality)
                                }}
                                · {{ label(application.vacancy.category) }}</b
                            >
                        </div>
                        <div>
                            <small>Registro AIRHSP</small
                            ><b>{{ application.vacancy.airhsp }}</b>
                        </div>
                        <div>
                            <small>Expediente</small
                            ><b>{{ shortRef(application.reference) }}</b>
                        </div>
                        <div>
                            <small>Cierre de inscripción · hora de Perú</small
                            ><b>{{
                                date(
                                    application.recruitment_call.closes_at,
                                    true,
                                )
                            }}</b>
                        </div>
                    </div>
                    <p v-if="editable" class="footnote">
                        El borrador queda guardado a medida que completas los
                        pasos. Tu postulación se registra al enviar el
                        expediente.
                    </p>
                    <Link
                        :href="url('/plazas/' + application.vacancy_id)"
                        class="text-link"
                        >Consultar la plaza
                        <Icon name="ArrowUpRight" :size="15"
                    /></Link>
                </section>
            </aside>
        </div>
    </AppLayout>
</template>
