<script setup>
import { computed } from "vue";
import { useForm } from "@inertiajs/vue3";
import AcademicCredentialFields from "./AcademicCredentialFields.vue";
import Errors from "./Errors.vue";
import Icon from "./Icon.vue";
import ScreeningSummary from "./ScreeningSummary.vue";
import { url } from "../lib/ui";
import {
    academicCredentialLabels,
    academicCredentialLimit,
    screeningRecordId,
} from "../lib/screening";
const props = defineProps({
    application: Object,
    report: Object,
    editable: Boolean,
    profileCredentials: { type: Array, default: () => [] },
});
const emit = defineEmits(["continue"]);
const initial = props.application.form_data || {};
const usesCredentials = computed(
    () => !!props.report?.rules?.academic_credentials,
);
const usesAppointmentTemplate = computed(
    () =>
        props.application.recruitment_call.evaluation_mode ===
        "nombramiento_referencia",
);
const form = useForm({
    statement: props.application.statement || "",
    consent: !!props.application.consented_at,
    ...(usesCredentials.value
        ? { credentials: initial.credentials || [] }
        : {
              degree: initial.degree || "",
              professional_title: initial.professional_title || "",
              bachelor_date: initial.bachelor_date || "",
          }),
    experience: initial.experience || [],
    training: initial.training || [],
    answers: initial.answers || {},
});
function credentialKey(credential) {
    return JSON.stringify(
        [
            credential.kind,
            credential.name,
            credential.institution,
            credential.awarded_at,
        ].map((value) => value.trim().toLowerCase()),
    );
}
const availableCredentials = computed(() => {
    const existing = new Set((form.credentials ?? []).map(credentialKey));
    return props.profileCredentials.filter(
        (credential) =>
            credential.awarded_at <= props.report.cutoff &&
            !existing.has(credentialKey(credential)),
    );
});
function copyCredential(credential) {
    form.credentials.push({ ...credential, id: screeningRecordId() });
}
function experience() {
    form.experience.push({
        id: screeningRecordId(),
        institution: "",
        position: "",
        type: "profesional",
        start_date: "",
        end_date: "",
    });
}
function training() {
    form.training.push({
        id: screeningRecordId(),
        name: "",
        institution: "",
        date: "",
        hours: 0,
    });
}
function save(advance = false) {
    if (advance && !form.consent) {
        form.setError(
            "consent",
            "Acepta la declaración de veracidad antes de continuar.",
        );
        return;
    }
    form.put(url(`/mis-postulaciones/${props.application.id}/formulario`), {
        preserveScroll: true,
        onSuccess: (page) => {
            if (advance && page.props.screening?.eligible) emit("continue");
        },
    });
}
function example(years, topDegree = "maestro") {
    const end = props.report.cutoff;
    const start = new Date(end + "T12:00:00Z");
    start.setUTCFullYear(start.getUTCFullYear() - years);
    const professionalTitle =
        props.application.vacancy.airhsp === "000206"
            ? "Licenciado en Turismo y Hotelería"
            : "Licenciado en Educación, especialidad Biología y Química";
    if (usesCredentials.value) {
        form.credentials = [
            {
                kind: "bachiller",
                name: "Bachiller de ejemplo",
                awarded_at: "2015-01-01",
            },
            {
                kind: "titulo",
                name: professionalTitle,
                awarded_at: "2016-01-01",
            },
            {
                kind: topDegree,
                name:
                    topDegree === "doctor"
                        ? "Doctorado de ejemplo"
                        : "Maestría de ejemplo",
                awarded_at: "2020-01-01",
            },
        ].map((credential) => ({
            ...credential,
            id: screeningRecordId(),
            institution: "Universidad de ejemplo",
        }));
    } else {
        form.degree = topDegree;
        form.professional_title = professionalTitle;
        form.bachelor_date = "2015-01-01";
    }
    form.experience = [
        {
            id: screeningRecordId(),
            institution: "Institución de ejemplo",
            position: "Docente de prueba",
            type: "docente",
            start_date: start.toISOString().slice(0, 10),
            end_date: end,
        },
    ];
    form.training = [
        {
            id: screeningRecordId(),
            name: "Capacitación de ejemplo",
            institution: "Centro de formación de prueba",
            date: end,
            hours: 80,
        },
    ];
    for (const f of props.application.vacancy.screening_rules?.fields ?? []) {
        form.answers[f.key] =
            f.expected_value || f.options?.[0] || "Cumple requisito";
    }
}
</script>
<template>
    <section v-if="report?.enabled" class="structured-form">
        <div class="panel-heading">
            <Icon name="ClipboardCheck" />
            <h2>Formulario de requisitos y méritos</h2>
        </div>
        <p class="muted">
            Registra información verificable. Primero comprobaremos los mínimos;
            después adjuntarás el sustento de lo declarado.
        </p>
        <div v-if="$page.props.demo && editable" class="demo-notice row wrap">
            <span>Casos ficticios para la demostración:</span
            ><button
                type="button"
                class="btn btn-outline btn-sm"
                @click="example(2)"
            >
                Ejemplo de 2 años</button
            ><button
                type="button"
                class="btn btn-outline btn-sm"
                @click="example(6)"
            >
                Ejemplo de 6 años</button
            ><button
                type="button"
                class="btn btn-outline btn-sm"
                @click="example(6, 'doctor')"
            >
                Ejemplo con Doctorado (8 pts)
            </button>
        </div>
        <Errors :errors="form.errors" />
        <form @submit.prevent="save($event.submitter?.value === 'documents')">
            <fieldset :disabled="!editable || form.processing">
                <template v-if="usesCredentials">
                    <h3>Formación profesional</h3>
                    <p class="footnote">
                        Registra por separado tus títulos, grados y segundas
                        especialidades. Para el puntaje se considera un título
                        profesional y el mayor puntaje de un grado admitido por
                        las bases. Adjuntarás un PDF por credencial en el
                        siguiente paso.
                    </p>
                    <details
                        v-if="profileCredentials.length"
                        class="entry-card"
                    >
                        <summary>Copiar credenciales de mi perfil</summary>
                        <p class="muted">
                            Elige las credenciales que quieras declarar en esta
                            plaza. Después deberás adjuntar sus sustentos.
                        </p>
                        <p v-if="!availableCredentials.length" class="muted">
                            No hay credenciales adicionales disponibles para
                            copiar.
                        </p>
                        <div
                            v-for="credential in availableCredentials"
                            :key="credential.id"
                            class="row between wrap"
                        >
                            <div>
                                <b>{{ credential.name }}</b>
                                <p class="muted">
                                    {{
                                        academicCredentialLabels[
                                            credential.kind
                                        ]
                                    }}
                                    · {{ credential.institution }}
                                </p>
                            </div>
                            <button
                                type="button"
                                class="btn btn-outline btn-sm"
                                :disabled="
                                    form.credentials.length >=
                                    academicCredentialLimit
                                "
                                :aria-label="'Copiar ' + credential.name"
                                @click="copyCredential(credential)"
                            >
                                Copiar
                            </button>
                        </div>
                    </details>
                    <AcademicCredentialFields
                        v-model="form.credentials"
                        :date-limit="report.cutoff"
                    />
                </template>
                <div v-else class="form-grid">
                    <label
                        >Título profesional que posees<input
                            v-model="form.professional_title"
                            maxlength="250"
                            placeholder="Denominación de tu título profesional" /></label
                    ><label
                        >Mayor grado académico<select v-model="form.degree">
                            <option value="">Seleccionar</option>
                            <option value="titulo">Título profesional</option>
                            <option value="maestro">Maestro / Magíster</option>
                            <option value="doctor">Doctor</option>
                        </select></label
                    ><label
                        >Fecha de obtención del bachiller<input
                            v-model="form.bachelor_date"
                            type="date"
                            :max="report.cutoff"
                    /></label>
                </div>
                <h3 class="form-section">Experiencia profesional y docente</h3>
                <p class="footnote">
                    Los periodos superpuestos se cuentan una sola vez. Deja la
                    fecha de fin vacía si continúas en el puesto. El cálculo se
                    limita a la fecha de corte.
                </p>
                <div
                    v-for="(e, i) in form.experience"
                    :key="e.id"
                    class="entry-card"
                >
                    <div class="row between">
                        <b>Periodo {{ i + 1 }}</b
                        ><button
                            type="button"
                            class="text-link"
                            @click="form.experience.splice(i, 1)"
                        >
                            Quitar
                        </button>
                    </div>
                    <div class="form-grid">
                        <label
                            >Institución<input
                                v-model="e.institution"
                                required
                                maxlength="200" /></label
                        ><label
                            >Cargo o función<input
                                v-model="e.position"
                                required
                                maxlength="200" /></label
                        ><label
                            >Tipo<select v-model="e.type">
                                <option value="profesional">Profesional</option>
                                <option value="docente">Docente</option>
                            </select></label
                        ><label
                            >Desde<input
                                v-model="e.start_date"
                                type="date"
                                required
                                :max="report.cutoff" /></label
                        ><label
                            >Hasta (vacío si continúa)<input
                                v-model="e.end_date"
                                type="date"
                                :max="report.cutoff"
                                :min="e.start_date"
                        /></label>
                    </div>
                </div>
                <button
                    type="button"
                    class="btn btn-outline btn-sm"
                    @click="experience"
                >
                    <Icon name="Plus" :size="15" />Añadir periodo de servicio
                </button>
                <h3 class="form-section">Capacitaciones</h3>
                <div
                    v-for="(c, i) in form.training"
                    :key="c.id"
                    class="entry-card"
                >
                    <div class="row between">
                        <b>Capacitación {{ i + 1 }}</b
                        ><button
                            type="button"
                            class="text-link"
                            @click="form.training.splice(i, 1)"
                        >
                            Quitar
                        </button>
                    </div>
                    <div class="form-grid">
                        <label
                            >Curso o capacitación<input
                                v-model="c.name"
                                required
                                maxlength="250" /></label
                        ><label
                            >Institución emisora<input
                                v-model="c.institution"
                                required
                                maxlength="200" /></label
                        ><label
                            >Fecha del certificado<input
                                v-model="c.date"
                                type="date"
                                required
                                :max="report.cutoff" /></label
                        ><label
                            >Horas acreditadas<input
                                v-model.number="c.hours"
                                type="number"
                                min="1"
                                max="5000"
                                required
                        /></label>
                    </div>
                </div>
                <button
                    type="button"
                    class="btn btn-outline btn-sm"
                    @click="training"
                >
                    <Icon name="Plus" :size="15" />Añadir capacitación
                </button>
                <div
                    v-if="application.vacancy.screening_rules.fields?.length"
                    class="form-section"
                >
                    <h3>Requisitos específicos de la plaza</h3>
                    <label
                        v-for="f in application.vacancy.screening_rules.fields"
                        :key="f.key"
                        >{{ f.label }}
                        <small>{{
                            f.required ? "Obligatorio" : "Opcional"
                        }}</small
                        ><select
                            v-if="f.type === 'select'"
                            v-model="form.answers[f.key]"
                        >
                            <option value="">Seleccionar</option>
                            <option v-for="option in f.options" :key="option">
                                {{ option }}
                            </option></select
                        ><input
                            v-else
                            v-model="form.answers[f.key]"
                            :type="f.type === 'text' ? 'text' : f.type"
                            :maxlength="f.type === 'text' ? 1000 : undefined"
                    /></label>
                </div>
                <div class="form-section">
                    <label
                        >Información complementaria<textarea
                            v-model="form.statement"
                            rows="3"
                            maxlength="4000"
                        ></textarea></label
                    ><label class="check-label consent"
                        ><input v-model="form.consent" type="checkbox" /><span
                            >Declaro que lo registrado es veraz y autorizo el
                            tratamiento de mis datos para esta postulación. He
                            revisado la
                            <a
                                :href="url('/ayuda#privacidad')"
                                target="_blank"
                                rel="noopener"
                                >información de privacidad</a
                            >.</span
                        ></label
                    >
                </div>
            </fieldset>
            <div v-if="editable" class="row wrap form-section">
                <button class="btn btn-outline" :disabled="form.processing">
                    Guardar y calcular</button
                ><button
                    type="submit"
                    value="documents"
                    class="btn btn-primary"
                    :disabled="form.processing"
                >
                    Comprobar y pasar a documentos<Icon
                        name="ArrowRight"
                        :size="16"
                    />
                </button>
            </div>
        </form>
        <ScreeningSummary
            :report="report"
            :show-points="!usesAppointmentTemplate"
        />
        <p v-if="usesAppointmentTemplate" class="footnote">
            La comisión revisará los sustentos y calificará los rubros externos
            e internos según la plantilla de evaluación de esta convocatoria.
        </p>
        <p v-if="form.isDirty" class="notice">
            Hay cambios sin calcular. Guarda el formulario para actualizar el
            resultado antes de continuar.
        </p>
    </section>
</template>
