<script setup>
import { ref, computed, watch } from "vue";
import { Link, useForm } from "@inertiajs/vue3";
import AppLayout from "../Layouts/AppLayout.vue";
import Icon from "../Components/Icon.vue";
import StructuredApplicationForm from "../Components/StructuredApplicationForm.vue";
import Badge from "../Components/Badge.vue";
import Errors from "../Components/Errors.vue";
import { url, date, shortRef } from "../lib/ui";
import { useApplicationWindow } from "../lib/useApplicationWindow";
import { screeningRecordId } from "../lib/screening";
const isOpen = useApplicationWindow();
const props = defineProps({
    application: Object,
    screening: Object,
    documentRequirements: Array,
    profileCredentials: Array,
    meritCriteria: Object,
    evaluationStatus: Object,
});
const step = ref(props.application.submitted_at ? 3 : 1);
const sending = ref(false);
const form = useForm({
    statement: props.application.statement || "",
    consent: !!props.application.consented_at,
});
const upload = useForm({ requirement_key: "", file: null });
const send = useForm({ confirm: false });
const merits = useForm({
    merits: (props.application.form_data?.merits ?? []).map((entry) => ({
        ...entry,
    })),
});
watch(
    () => props.application.form_data?.merits,
    (updated) => {
        if (!merits.isDirty && Array.isArray(updated)) {
            merits.merits = updated.map((entry) => ({ ...entry }));
        }
    },
);
const groupedMeritCriteria = computed(() => {
    const groups = new Map();
    for (const [key, criterion] of Object.entries(props.meritCriteria ?? {})) {
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
const declaredExternalSummary = computed(() => {
    if (!props.evaluationStatus || !groupedMeritCriteria.value.length) {
        return null;
    }
    const byRubric = [];
    let total = 0;
    for (const group of groupedMeritCriteria.value) {
        let sum = 0;
        const exclusive = {};
        for (const entry of merits.merits) {
            const criterion = props.meritCriteria?.[entry.criterion];
            if (!criterion || criterion.rubric !== group.key) continue;
            const qty = Math.max(0, Number(entry.quantity || 0));
            if (
                [
                    "Grado más alto",
                    "Un solo título",
                    "Categoría más alta",
                ].includes(criterion.unit)
            ) {
                exclusive[criterion.unit] = Math.max(
                    exclusive[criterion.unit] ?? 0,
                    qty > 0 ? Number(criterion.points) : 0,
                );
            } else {
                sum += qty * Number(criterion.points);
            }
        }
        const subtotal = Math.min(
            Number(group.max ?? 55),
            Math.round(
                (sum +
                    Object.values(exclusive).reduce(
                        (acc, val) => acc + val,
                        0,
                    )) *
                    100,
            ) / 100,
        );
        if (subtotal > 0) {
            byRubric.push({
                key: group.key,
                name: group.name,
                subtotal,
                max: group.max,
            });
        }
        total += subtotal;
    }
    total = Math.round(total * 100) / 100;
    const minimum = Number(props.evaluationStatus.externalMinimum ?? 0);
    return {
        total,
        maximum: Number(props.evaluationStatus.externalMaximum ?? 55),
        minimum,
        passedMinimum: total >= minimum,
        byRubric,
    };
});
const canSyncFromStepOne = computed(() =>
    Boolean(
        props.application.form_data?.credentials?.length ||
            props.application.form_data?.experience?.length,
    ),
);
function syncFromStepOne() {
    const fd = props.application.form_data ?? {};
    const credMap = {
        doctor: "formacion:doctor",
        maestro: "formacion:maestro",
        bachiller: "formacion:bachiller",
        titulo: "formacion:titulo",
        segunda_especialidad: "formacion_servicio:especialidad",
    };
    const existingReuse = new Set(
        merits.merits.map((m) => m.reuse).filter(Boolean),
    );
    for (const credential of fd.credentials ?? []) {
        const criterion = credMap[credential.kind];
        const reuse = "acad_" + credential.id;
        if (
            criterion &&
            props.meritCriteria?.[criterion] &&
            !existingReuse.has(reuse)
        ) {
            merits.merits.push({
                id: screeningRecordId(),
                criterion,
                description:
                    `${credential.name} · ${credential.institution}`.trim(),
                quantity: 1,
                reuse,
            });
            existingReuse.add(reuse);
        }
    }
    for (const period of fd.experience ?? []) {
        const reuse = "exp_" + period.id;
        const criterion =
            period.type === "docente"
                ? "experiencia_universitaria:docente"
                : "experiencia_extrauniversitaria:anio";
        if (props.meritCriteria?.[criterion] && !existingReuse.has(reuse)) {
            merits.merits.push({
                id: screeningRecordId(),
                criterion,
                description:
                    `${period.position} · ${period.institution}`.trim(),
                quantity: 1,
                reuse,
            });
            existingReuse.add(reuse);
        }
    }
}
const aptitudeDocuments = computed(() =>
    props.documentRequirements.filter(
        (item) =>
            !item.key.startsWith("merito_") &&
            !["silabo_interno", "bonificacion_discapacidad"].includes(item.key),
    ),
);
function addMerit() {
    merits.merits.push({
        id: screeningRecordId(),
        criterion: "",
        description: "",
        quantity: 1,
        reuse: null,
    });
}
function saveMerits() {
    merits.put(url(`/mis-postulaciones/${props.application.id}/meritos`), {
        preserveScroll: true,
    });
}
const editable = computed(
    () =>
        props.application.status === "BORRADOR" &&
        isOpen(props.application.recruitment_call),
);
const usesAppointmentTemplate = computed(
    () =>
        props.application.recruitment_call.evaluation_mode ===
        "nombramiento_referencia",
);
const doc = (key) =>
    props.application.documents.find((d) => d.requirement_key === key);
const required = computed(() =>
    (
        props.documentRequirements || props.application.vacancy.requirements
    ).filter((r) => r.required),
);
const ready = computed(
    () =>
        required.value.every((r) => doc(r.key)) &&
        !merits.isDirty &&
        merits.merits.every((merit) => !merit.reuse || doc(merit.reuse)) &&
        !!props.application.consented_at &&
        (!props.screening?.enabled || props.screening.eligible),
);
function save() {
    form.put(url(`/mis-postulaciones/${props.application.id}`), {
        preserveScroll: true,
    });
}
function selectFile(event, key) {
    upload.requirement_key = key;
    upload.file = event.target.files[0];
    if (upload.file) {
        upload.post(
            url(`/mis-postulaciones/${props.application.id}/documentos`),
            { preserveScroll: true, onSuccess: () => upload.reset() },
        );
    }
    event.target.value = "";
}
function submit() {
    send.post(url(`/mis-postulaciones/${props.application.id}/enviar`), {
        preserveScroll: true,
        onSuccess: () => {
            sending.value = false;
            step.value = 3;
        },
    });
}
</script>
<template>
    <AppLayout
        :title="
            application.submitted_at ? 'Mi expediente' : 'Prepara tu expediente'
        "
        :subtitle="application.vacancy.school"
        ><template #actions><Badge :value="application.status" /></template
        ><Errors
            :errors="{ ...form.errors, ...upload.errors, ...send.errors }"
        />
        <div v-if="application.recruitment_call.is_demo" class="demo-notice">
            <Icon name="CircleAlert" :size="18" /> Expediente de demostración,
            sin validez oficial.
        </div>
        <div class="wizard-steps">
            <button
                v-for="(title, i) in [
                    'Información',
                    evaluationStatus ? 'Sustentos y méritos' : 'Documentos',
                    'Revisión y envío',
                ]"
                :key="title"
                :class="{ active: step === i + 1, complete: step > i + 1 }"
                @click="
                    (i === 0 || !screening?.enabled || screening.eligible) &&
                    (step = i + 1)
                "
            >
                <span>{{ i + 1 }}</span
                >{{ title }}
            </button>
        </div>
        <div class="detail-grid application-grid">
            <div>
                <section v-if="step === 1" class="panel">
                    <div class="panel-heading">
                        <Icon name="Users" />
                        <h2>Información de la postulación</h2>
                    </div>
                    <div class="info-grid">
                        <div>
                            <small>Postulante</small
                            ><b>{{
                                application.applicant_snapshot?.name ||
                                $page.props.auth.user.name
                            }}</b>
                        </div>
                        <div>
                            <small>DNI</small
                            ><b>{{
                                application.applicant_snapshot
                                    ?.document_number ||
                                $page.props.auth.user.document_number ||
                                "Completa tu perfil"
                            }}</b>
                        </div>
                        <div>
                            <small>Correo</small
                            ><b>{{
                                application.applicant_snapshot?.email ||
                                $page.props.auth.user.email
                            }}</b>
                        </div>
                        <div>
                            <small>Teléfono</small
                            ><b>{{
                                application.applicant_snapshot?.phone ||
                                $page.props.auth.user.phone ||
                                "Completa tu perfil"
                            }}</b>
                        </div>
                    </div>
                    <Link
                        v-if="editable"
                        :href="url('/perfil')"
                        class="text-link"
                        >Actualizar mis datos
                        <Icon name="ArrowUpRight" :size="15"
                    /></Link>
                    <StructuredApplicationForm
                        :application="application"
                        :report="screening"
                        :profile-credentials="profileCredentials"
                        :editable="editable"
                        @continue="step = 2"
                    />
                    <form
                        v-if="!screening?.enabled"
                        @submit.prevent="save"
                        class="form-section"
                    >
                        <label
                            >Presentación o información complementaria<textarea
                                v-model="form.statement"
                                :disabled="!editable"
                                rows="5"
                                maxlength="4000"
                                placeholder="Añade información relevante para la revisión de tu expediente."
                            ></textarea></label
                        ><label class="check-label consent"
                            ><input
                                type="checkbox"
                                v-model="form.consent"
                                :disabled="!editable"
                            /><span
                                >Declaro que la información y los documentos son
                                veraces. Autorizo su tratamiento para la gestión
                                de esta convocatoria y he revisado la
                                <Link :href="url('/ayuda#privacidad')"
                                    >información de privacidad</Link
                                >.</span
                            ></label
                        >
                        <div class="row between">
                            <button
                                v-if="editable"
                                class="btn btn-primary"
                                :disabled="form.processing"
                            >
                                {{
                                    form.processing
                                        ? "Guardando…"
                                        : "Guardar información"
                                }}</button
                            ><button
                                v-if="!screening?.enabled || screening.eligible"
                                type="button"
                                class="btn btn-outline"
                                @click="step = 2"
                            >
                                Continuar <Icon name="ArrowRight" :size="16" />
                            </button>
                        </div>
                    </form>
                </section>
                <section v-if="step === 2" class="panel">
                    <form
                        v-if="editable && evaluationStatus"
                        @submit.prevent="saveMerits"
                    >
                        <div class="row between wrap">
                            <h2>
                                Declara tus méritos (Anexo 01 · Aspectos
                                Externos)
                            </h2>
                            <button
                                v-if="canSyncFromStepOne"
                                type="button"
                                class="btn btn-outline btn-sm"
                                @click="syncFromStepOne"
                            >
                                Importar grados y experiencia del Paso 1
                            </button>
                        </div>
                        <p>
                            Selecciona el rubro del Anexo 01, describe el mérito
                            e indica la cantidad en la unidad solicitada. Guarda
                            la lista para adjuntar los PDF o reutiliza una
                            credencial ya cargada.
                        </p>
                        <div v-if="declaredExternalSummary" class="notice">
                            <div class="row between wrap">
                                <strong
                                    >Puntaje preliminar declarado (Anexo 01):
                                    {{
                                        declaredExternalSummary.total.toFixed(2)
                                    }}
                                    /
                                    {{ declaredExternalSummary.maximum }}
                                    pts</strong
                                >
                                <span
                                    v-if="declaredExternalSummary.minimum"
                                    class="badge"
                                    :class="
                                        declaredExternalSummary.passedMinimum
                                            ? 'badge-apto'
                                            : 'badge-no_apto'
                                    "
                                >
                                    {{
                                        declaredExternalSummary.passedMinimum
                                            ? `Alcanza el mínimo externo (${declaredExternalSummary.minimum} pts)`
                                            : `Mínimo externo requerido: ${declaredExternalSummary.minimum} pts`
                                    }}
                                </span>
                            </div>
                            <p
                                v-if="declaredExternalSummary.byRubric.length"
                                class="muted"
                            >
                                {{
                                    declaredExternalSummary.byRubric
                                        .map(
                                            (r) =>
                                                `${r.name}: ${r.subtotal} / ${r.max} pts`,
                                        )
                                        .join(" · ")
                                }}
                            </p>
                        </div>
                        <Errors :errors="merits.errors" />
                        <div
                            v-for="(merit, index) in merits.merits"
                            :key="merit.id"
                            class="entry-card"
                        >
                            <label
                                >Criterio del Anexo 01
                                <select v-model="merit.criterion" required>
                                    <option value="" disabled>
                                        Selecciona un criterio
                                    </option>
                                    <optgroup
                                        v-for="group in groupedMeritCriteria"
                                        :key="group.key"
                                        :label="`${group.name} (Máx. ${group.max} pts)`"
                                    >
                                        <option
                                            v-for="criterion in group.items"
                                            :key="criterion.key"
                                            :value="criterion.key"
                                        >
                                            {{ criterion.name }} ·
                                            {{ criterion.points }} pts ({{
                                                criterion.unit
                                            }})
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
                                        Adjuntar PDF para este mérito
                                    </option>
                                    <option
                                        v-for="item in aptitudeDocuments"
                                        :key="item.key"
                                        :value="item.key"
                                    >
                                        Reutilizar: {{ item.label }}
                                    </option>
                                </select>
                            </label>
                            <button
                                type="button"
                                class="text-link"
                                @click="merits.merits.splice(index, 1)"
                            >
                                Quitar mérito
                            </button>
                        </div>
                        <div class="row wrap">
                            <button
                                v-if="merits.merits.length < 150"
                                type="button"
                                class="btn btn-outline"
                                @click="addMerit"
                            >
                                Añadir mérito
                            </button>
                            <button
                                class="btn btn-primary"
                                :disabled="merits.processing || !merits.isDirty"
                            >
                                Guardar méritos
                            </button>
                        </div>
                    </form>
                    <div class="panel-heading">
                        <Icon name="Upload" />
                        <h2>Documentos de sustento</h2>
                    </div>
                    <p class="muted">
                        Carga un PDF por requisito. Cada archivo debe pesar como
                        máximo 5 MB. Puedes reemplazarlo mientras el expediente
                        sea borrador.
                    </p>
                    <div
                        v-for="r in documentRequirements"
                        :key="r.key"
                        class="upload-row"
                    >
                        <div class="row">
                            <span
                                class="document-state"
                                :class="{ done: doc(r.key) }"
                                ><Icon
                                    :name="doc(r.key) ? 'Check' : 'FileText'"
                                    :size="19"
                            /></span>
                            <div class="grow">
                                <b>{{ r.label }}</b
                                ><small>{{
                                    r.required ? "Obligatorio" : "Opcional"
                                }}</small
                                ><a
                                    v-if="doc(r.key)"
                                    :href="url('/documentos/' + doc(r.key).id)"
                                    target="_blank"
                                    rel="noopener"
                                    class="file-link"
                                    >{{ doc(r.key).name }}
                                    <Icon name="ExternalLink" :size="13"
                                /></a>
                            </div>
                        </div>
                        <label
                            v-if="editable"
                            class="btn btn-outline btn-sm file-upload"
                            :class="{ disabled: upload.processing }"
                            ><Icon name="Upload" :size="15" />{{
                                upload.processing &&
                                upload.requirement_key === r.key
                                    ? "Subiendo…"
                                    : doc(r.key)
                                      ? "Reemplazar"
                                      : "Adjuntar PDF"
                            }}<input
                                type="file"
                                accept="application/pdf,.pdf"
                                :disabled="upload.processing"
                                @change="selectFile($event, r.key)"
                        /></label>
                    </div>
                    <div class="row between form-section">
                        <button class="btn btn-outline" @click="step = 1">
                            Anterior</button
                        ><button class="btn btn-primary" @click="step = 3">
                            Revisar expediente
                            <Icon name="ArrowRight" :size="16" />
                        </button>
                    </div>
                </section>
                <section v-if="step === 3" class="panel">
                    <div
                        v-if="evaluationStatus && application.submitted_at"
                        class="notice"
                    >
                        <strong>Seguimiento de la evaluación</strong>
                        <p v-if="application.status === 'RECIBIDA'">
                            Tu expediente espera la revisión de aptitud.
                        </p>
                        <p v-else-if="evaluationStatus.result">
                            Resultado publicado. Puntaje final:
                            {{
                                evaluationStatus.result.final ??
                                "No alcanzó los mínimos"
                            }}.
                        </p>
                        <p v-else-if="evaluationStatus.advanced">
                            Aprobaste aspectos externos. Consulta la
                            programación de aspectos internos.
                        </p>
                        <p
                            v-else-if="
                                evaluationStatus.externalPublished &&
                                application.status === 'EVALUADA'
                            "
                        >
                            Tu expediente no alcanzó el mínimo de aspectos
                            externos.
                        </p>
                        <p v-else>
                            Consulta el cronograma. El resultado consolidado se
                            mostrará tras la publicación.
                        </p>
                        <div
                            v-for="event in evaluationStatus.calendar"
                            :key="event.stage"
                            class="schedule-row"
                        >
                            <span>{{ event.label }} · {{ event.detail }}</span>
                            <b
                                >{{ event.starts_at.replace("T", " ") }} al
                                {{ event.ends_at?.replace("T", " ") }}</b
                            >
                        </div>
                    </div>
                    <div
                        v-if="evaluationStatus && merits.merits.length"
                        class="form-section"
                    >
                        <h3>Méritos declarados</h3>
                        <div
                            v-for="entry in merits.merits"
                            :key="entry.id"
                            class="schedule-row"
                        >
                            <span>{{ entry.description }}</span
                            ><b>{{ entry.quantity }}</b>
                        </div>
                    </div>
                    <div class="panel-heading">
                        <Icon
                            :name="
                                application.submitted_at
                                    ? 'CheckCircle2'
                                    : 'ClipboardCheck'
                            "
                        />
                        <h2>
                            {{
                                application.submitted_at
                                    ? "Expediente recibido"
                                    : "Revisa antes de enviar"
                            }}
                        </h2>
                    </div>
                    <p v-if="usesAppointmentTemplate" class="notice">
                        La comisión verificará los sustentos y determinará la
                        aptitud y la calificación según la plantilla de
                        evaluación de la convocatoria.
                    </p>
                    <template v-if="application.submitted_at"
                        ><div class="submission-success">
                            <Icon name="CheckCircle2" :size="46" />
                            <h3>Tu postulación fue recibida</h3>
                            <p>
                                {{ date(application.submitted_at, true) }} ·
                                Hora de Perú
                            </p>
                            <p>
                                Código completo:
                                <span class="mono reference">{{
                                    application.reference
                                }}</span>
                            </p>
                            <a
                                :href="
                                    url(
                                        `/mis-postulaciones/${application.id}/cargo`,
                                    )
                                "
                                target="_blank"
                                rel="noopener"
                                class="btn btn-primary"
                                ><Icon name="Download" :size="17" /> Ver /
                                imprimir cargo</a
                            >
                        </div>
                        <div class="notice">
                            El expediente está cerrado para edición. Su
                            recepción no implica aptitud ni adjudicación de la
                            plaza.
                        </div>
                        <div
                            v-if="application.review_notes"
                            class="review-observation"
                        >
                            <h3>Observación de la comisión</h3>
                            <p>{{ application.review_notes }}</p>
                        </div></template
                    ><template v-else
                        ><div class="check-summary">
                            <div>
                                <Icon
                                    :name="
                                        ready ? 'CheckCircle2' : 'CircleAlert'
                                    "
                                /><span
                                    >{{ application.documents.length }}
                                    documentos adjuntos ·
                                    {{
                                        required.filter((r) => doc(r.key))
                                            .length
                                    }}
                                    de {{ required.length }} requisitos
                                    obligatorios</span
                                >
                            </div>
                            <div>
                                <Icon
                                    :name="
                                        application.consented_at
                                            ? 'CheckCircle2'
                                            : 'CircleAlert'
                                    "
                                /><span>{{
                                    application.consented_at
                                        ? "Declaración guardada"
                                        : "Guarda tu declaración en el paso Información"
                                }}</span>
                            </div>
                        </div>
                        <div class="notice">
                            Después del envío no podrás reemplazar archivos ni
                            modificar tu expediente. Verifica que los documentos
                            sean legibles.
                        </div>
                        <button
                            v-if="editable"
                            class="btn btn-primary full"
                            :disabled="!ready"
                            @click="sending = true"
                        >
                            Confirmar envío de mi expediente
                            <Icon name="ArrowRight" :size="17" />
                        </button>
                        <div v-else class="notice notice-error">
                            La inscripción está cerrada. Ya no es posible enviar
                            este borrador.
                        </div></template
                    >
                    <h3 class="form-section">Documentos del expediente</h3>
                    <a
                        v-for="d in application.documents"
                        :key="d.id"
                        :href="url('/documentos/' + d.id)"
                        target="_blank"
                        rel="noopener"
                        class="document-link"
                        ><Icon name="FileText" /><span
                            >{{ d.name
                            }}<small
                                >{{ (d.size / 1024).toFixed(1) }} KB ·
                                {{ d.review_status }}</small
                            ></span
                        ><Icon name="ExternalLink" :size="16"
                    /></a>
                </section>
            </div>
            <aside>
                <section class="panel sticky-card">
                    <span class="eyebrow">RESUMEN DE LA POSTULACIÓN</span>
                    <h3>{{ application.vacancy.school }}</h3>
                    <p>{{ application.vacancy.faculty }}</p>
                    <div class="summary-list">
                        <div>
                            <small>Convocatoria</small
                            ><b>{{ application.recruitment_call.code }}</b>
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
                            <small>Cierre de inscripción</small
                            ><b>{{
                                date(
                                    application.recruitment_call.closes_at,
                                    true,
                                )
                            }}</b>
                        </div>
                    </div>
                    <Link
                        :href="url('/plazas/' + application.vacancy_id)"
                        class="text-link"
                        >Consultar perfil de la plaza
                        <Icon name="ArrowUpRight" :size="15"
                    /></Link>
                </section>
            </aside>
        </div>
        <div
            v-if="sending"
            class="modal-backdrop"
            @click.self="sending = false"
        >
            <section class="modal">
                <Icon name="ClipboardCheck" :size="32" />
                <h2>¿Enviar tu expediente?</h2>
                <p>
                    Se registrarán tus documentos y la hora de recepción. Esta
                    acción cierra la edición de la postulación.
                </p>
                <label class="check-label consent"
                    ><input type="checkbox" v-model="send.confirm" /><span
                        >He revisado mis datos y documentos y deseo enviar mi
                        expediente.</span
                    ></label
                >
                <div class="row end">
                    <button class="btn btn-outline" @click="sending = false">
                        Volver a revisar</button
                    ><button
                        class="btn btn-primary"
                        :disabled="
                            !send.confirm || send.processing || !editable
                        "
                        @click="submit"
                    >
                        {{
                            send.processing
                                ? "Enviando…"
                                : "Sí, enviar expediente"
                        }}
                    </button>
                </div>
            </section>
        </div></AppLayout
    >
</template>
