<script setup>
import { useForm } from "@inertiajs/vue3";
import Errors from "./Errors.vue";
import Icon from "./Icon.vue";
import ScreeningSummary from "./ScreeningSummary.vue";
import { url } from "../lib/ui";
const props = defineProps({
    application: Object,
    report: Object,
    editable: Boolean,
});
const emit = defineEmits(["continue"]);
const initial = props.application.form_data || {};
const form = useForm({
    statement: props.application.statement || "",
    consent: !!props.application.consented_at,
    degree: initial.degree || "",
    professional_title: initial.professional_title || "",
    bachelor_date: initial.bachelor_date || "",
    experience: initial.experience || [],
    training: initial.training || [],
    answers: initial.answers || {},
});
const uid = () => crypto.randomUUID();
function experience() {
    form.experience.push({
        id: uid(),
        institution: "",
        position: "",
        type: "profesional",
        start_date: "",
        end_date: "",
    });
}
function training() {
    form.training.push({
        id: uid(),
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
function example(years) {
    const end = props.report.cutoff;
    const start = new Date(end + "T12:00:00Z");
    start.setUTCFullYear(start.getUTCFullYear() - years);
    form.degree = "maestro";
    form.professional_title =
        props.application.vacancy.airhsp === "000206"
            ? "Licenciado en Turismo y Hotelería"
            : "Licenciado en Educación, especialidad Biología y Química";
    form.bachelor_date = "2015-01-01";
    form.experience = [
        {
            id: uid(),
            institution: "Institución de ejemplo",
            position: "Docente de prueba",
            type: "docente",
            start_date: start.toISOString().slice(0, 10),
            end_date: end,
        },
    ];
    form.training = [
        {
            id: uid(),
            name: "Capacitación de ejemplo",
            institution: "Centro de formación de prueba",
            date: end,
            hours: 80,
        },
    ];
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
            después adjuntarás el sustento de cada experiencia y capacitación.
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
                Ejemplo de 6 años
            </button>
        </div>
        <Errors :errors="form.errors" />
        <form @submit.prevent="save()">
            <fieldset :disabled="!editable || form.processing">
                <div class="form-grid">
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
                <h3>Experiencia profesional y docente</h3>
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
                    type="button"
                    class="btn btn-primary"
                    :disabled="form.processing"
                    @click="save(true)"
                >
                    Comprobar y pasar a documentos<Icon
                        name="ArrowRight"
                        :size="16"
                    />
                </button>
            </div>
        </form>
        <ScreeningSummary :report="report" />
        <p v-if="form.isDirty" class="notice">
            Hay cambios sin calcular. Guarda el formulario para actualizar el
            resultado antes de continuar.
        </p>
    </section>
</template>
