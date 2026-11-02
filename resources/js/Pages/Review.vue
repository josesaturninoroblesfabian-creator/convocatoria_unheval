<script setup>
import { ref, computed } from "vue";
import { useForm, Link } from "@inertiajs/vue3";
import AppLayout from "../Layouts/AppLayout.vue";
import Icon from "../Components/Icon.vue";
import PdfViewer from "../Components/PdfViewer.vue";
import ScreeningSummary from "../Components/ScreeningSummary.vue";
import Badge from "../Components/Badge.vue";
import Errors from "../Components/Errors.vue";
import { url, date, shortRef } from "../lib/ui";
const props = defineProps({
    application: Object,
    screening: Object,
    documentRequirements: Array,
    canReview: Boolean,
    ownEvaluation: Object,
    evaluationCount: Number,
    juryCount: Number,
});
const selected = ref(props.application.documents[0]?.id);
const eligibility = useForm({
    notes: "",
    documents: Object.fromEntries(
        props.application.documents.map((d) => [d.id, ""]),
    ),
});
const evaluation = useForm({
    scores: Object.fromEntries(
        props.application.recruitment_call.rubric.map((r) => [
            r.key,
            props.ownEvaluation?.scores[r.key] ?? "",
        ]),
    ),
    notes: props.ownEvaluation?.notes || "",
});
const confirming = ref(false);
const canWrite = computed(
    () =>
        props.canReview &&
        props.application.recruitment_call.status === "EN_EVALUACION",
);
const total = computed(() =>
    Object.values(evaluation.scores)
        .reduce((s, n) => s + Number(n || 0), 0)
        .toFixed(3),
);
const hasCalculatedCriteria = computed(() =>
    props.application.recruitment_call.rubric.some((r) =>
        ["formacion", "experiencia", "capacitacion"].includes(r.key),
    ),
);
function useCalculatedScores() {
    const values = {
        formacion: props.screening.academic_points,
        experiencia: props.screening.experience_points,
        capacitacion: props.screening.training_points,
    };
    const applicable = props.application.recruitment_call.rubric.filter((r) =>
        Object.hasOwn(values, r.key),
    );
    if (applicable.some((r) => Number(values[r.key]) > Number(r.max))) {
        evaluation.setError(
            "scores",
            "Los puntos calculados superan el máximo de algún rubro. Revisa la configuración y asigna la nota sustentada.",
        );
        return;
    }
    for (const criterion of applicable)
        evaluation.scores[criterion.key] = values[criterion.key];
    evaluation.clearErrors("scores");
}
function aptitude() {
    eligibility.post(url(`/expedientes/${props.application.id}/aptitud`), {
        preserveScroll: true,
    });
}
function grade() {
    evaluation.post(url(`/expedientes/${props.application.id}/evaluacion`), {
        preserveScroll: true,
        onSuccess: () => (confirming.value = false),
    });
}
</script>
<template>
    <AppLayout
        title="Revisión de expediente"
        :subtitle="'Expediente ' + shortRef(application.reference)"
        ><template #actions
            ><Badge :value="application.status" /><Link
                :href="url('/expedientes')"
                class="btn btn-outline btn-sm"
                ><Icon name="ArrowLeft" :size="16" />Volver</Link
            ></template
        >
        <div v-if="application.recruitment_call.is_demo" class="demo-notice">
            <Icon name="CircleAlert" :size="18" /> Evaluación de demostración.
            No genera resultados oficiales.
        </div>
        <section class="panel reviewer-profile">
            <div>
                <h2>{{ application.applicant_snapshot?.name }}</h2>
                <p>
                    {{ application.vacancy.school }} · AIRHSP
                    {{ application.vacancy.airhsp }}
                </p>
            </div>
            <div>
                <small>Recibido</small
                ><b>{{ date(application.submitted_at, true) }}</b>
            </div>
        </section>
        <ScreeningSummary :report="screening" />
        <section v-if="application.form_data" class="panel">
            <h3>Datos declarados y sustentos a comprobar</h3>
            <p>
                Título: {{ application.form_data.professional_title }} · Grado:
                {{ application.form_data.degree }} · Bachiller:
                {{ application.form_data.bachelor_date }}
            </p>
            <div
                v-for="period in application.form_data.experience"
                :key="period.id"
                class="schedule-row"
            >
                <span
                    >{{ period.institution }} · {{ period.position }} ({{
                        period.type
                    }})</span
                ><b
                    >{{ period.start_date }} —
                    {{ period.end_date || "Actualidad" }}</b
                >
            </div>
            <div
                v-for="course in application.form_data.training"
                :key="course.id"
                class="schedule-row"
            >
                <span>{{ course.name }} · {{ course.institution }}</span
                ><b>{{ course.hours }} horas · {{ course.date }}</b>
            </div>
            <div
                v-for="(answer, key) in application.form_data.answers"
                :key="key"
                class="schedule-row"
            >
                <span>{{
                    application.vacancy.screening_rules.fields.find(
                        (f) => f.key === key,
                    )?.label || key
                }}</span
                ><b>{{ answer }}</b>
            </div>
        </section>
        <div class="review-split">
            <section class="pdf-panel">
                <div class="pdf-toolbar">
                    <Icon name="FileText" /><select
                        v-model="selected"
                        aria-label="Documento a revisar"
                    >
                        <option
                            v-for="d in application.documents"
                            :key="d.id"
                            :value="d.id"
                        >
                            {{ d.name }}
                        </option></select
                    ><a
                        v-if="selected"
                        :href="url('/documentos/' + selected)"
                        target="_blank"
                        rel="noopener"
                        class="icon-btn"
                        aria-label="Abrir documento en otra pestaña"
                        ><Icon name="ExternalLink" :size="18"
                    /></a>
                </div>
                <PdfViewer
                    v-if="selected"
                    :key="selected"
                    :src="url('/documentos/' + selected)"
                />
                <div v-else class="empty-state">
                    El expediente no tiene archivos adjuntos.
                </div>
            </section>
            <aside class="panel evaluation-panel">
                <div class="panel-heading">
                    <Icon name="ClipboardCheck" />
                    <h2>
                        {{
                            application.status === "RECIBIDA"
                                ? "Verificación de requisitos"
                                : "Calificación por rubros"
                        }}
                    </h2>
                </div>
                <div v-if="!canWrite" class="notice">
                    {{
                        canReview
                            ? "La revisión está habilitada únicamente durante la etapa de evaluación."
                            : "Vista de seguimiento. La revisión corresponde a los jurados asignados."
                    }}
                </div>
                <Errors
                    :errors="{ ...eligibility.errors, ...evaluation.errors }"
                />
                <form
                    v-if="application.status === 'RECIBIDA'"
                    @submit.prevent="aptitude"
                >
                    <p class="muted">
                        Revisa cada archivo. Si un sustento obligatorio no
                        cumple, el expediente queda como no apto.
                    </p>
                    <div
                        v-for="r in documentRequirements"
                        :key="r.key"
                        class="eligibility-item"
                    >
                        <b>{{ r.label }}</b
                        ><small>{{
                            r.required ? "Obligatorio" : "Opcional"
                        }}</small
                        ><template
                            v-for="d in application.documents.filter(
                                (d) => d.requirement_key === r.key,
                            )"
                            :key="d.id"
                            ><button
                                type="button"
                                class="text-link"
                                @click="selected = d.id"
                            >
                                Ver sustento
                                <Icon name="Eye" :size="14" /></button
                            ><select
                                v-model="eligibility.documents[d.id]"
                                required
                                :disabled="!canWrite"
                                :aria-label="'Veredicto: ' + r.label"
                            >
                                <option value="" disabled>
                                    Selecciona el resultado
                                </option>
                                <option value="CUMPLE">Cumple</option>
                                <option value="NO_CUMPLE">No cumple</option>
                            </select></template
                        ><span
                            v-if="
                                !application.documents.some(
                                    (d) => d.requirement_key === r.key,
                                )
                            "
                            class="muted"
                            >Sin archivo adjunto</span
                        >
                    </div>
                    <label
                        >Motivación de la decisión<textarea
                            v-model="eligibility.notes"
                            required
                            minlength="5"
                            maxlength="3000"
                            rows="3"
                            :disabled="!canWrite"
                            placeholder="Explica el resultado de la revisión."
                        ></textarea></label
                    ><button
                        v-if="canWrite"
                        class="btn btn-primary full"
                        :disabled="eligibility.processing"
                    >
                        Cerrar revisión de aptitud
                    </button>
                </form>
                <div
                    v-else-if="application.status === 'NO_APTO'"
                    class="notice notice-error"
                >
                    <strong>Expediente no apto</strong>
                    <p>{{ application.review_notes }}</p>
                </div>
                <form v-else-if="canReview" @submit.prevent="confirming = true">
                    <div class="notice">
                        <strong
                            >{{ evaluationCount }} de {{ juryCount }} jurados
                            han cerrado su evaluación.</strong
                        >
                        <p>
                            Las calificaciones individuales de otros jurados se
                            mantienen privadas. Los resultados consolidados se
                            publican al finalizar el proceso.
                        </p>
                    </div>
                    <button
                        v-if="
                            canWrite &&
                            !ownEvaluation &&
                            screening?.enabled &&
                            hasCalculatedCriteria &&
                            application.status === 'APTO'
                        "
                        type="button"
                        class="btn btn-outline full"
                        @click="useCalculatedScores"
                    >
                        Usar cálculo de formación, experiencia y capacitación
                    </button>
                    <p class="footnote">
                        El cálculo solo rellena tu borrador de calificación.
                        Puedes corregirlo según los documentos; la clase y la
                        entrevista se califican por separado.
                    </p>
                    <label
                        v-for="r in application.recruitment_call.rubric"
                        :key="r.key"
                        class="score-input"
                        ><span
                            >{{ r.name
                            }}<small>Máximo {{ r.max }} puntos</small></span
                        ><input
                            v-model="evaluation.scores[r.key]"
                            type="number"
                            min="0"
                            :max="r.max"
                            step="0.001"
                            required
                            :disabled="
                                !canWrite ||
                                !!ownEvaluation ||
                                application.status === 'EVALUADA'
                            "
                    /></label>
                    <div class="score-total">
                        <span>Mi puntaje total</span><b>{{ total }}</b>
                    </div>
                    <label
                        >Fundamento de la calificación<textarea
                            v-model="evaluation.notes"
                            required
                            minlength="5"
                            rows="3"
                            maxlength="3000"
                            :disabled="
                                !canWrite ||
                                !!ownEvaluation ||
                                application.status === 'EVALUADA'
                            "
                        ></textarea></label
                    ><button
                        v-if="
                            canWrite &&
                            !ownEvaluation &&
                            application.status === 'APTO'
                        "
                        class="btn btn-primary full"
                        :disabled="evaluation.processing"
                    >
                        Revisar y cerrar calificación
                    </button>
                    <div v-if="ownEvaluation" class="notice notice-success">
                        Tu calificación quedó cerrada el
                        {{ date(ownEvaluation.closed_at, true) }}.
                    </div>
                </form>
                <div v-else class="notice">
                    <strong
                        >{{ evaluationCount }} de {{ juryCount }} jurados han
                        cerrado su evaluación.</strong
                    >
                    <p>
                        Las notas individuales son privadas. Puedes consultar el
                        resultado consolidado cuando se publique el cierre del
                        proceso.
                    </p>
                    <p v-if="application.review_notes">
                        Revisión de aptitud: {{ application.review_notes }}
                    </p>
                </div>
            </aside>
        </div>
        <div
            v-if="confirming"
            class="modal-backdrop"
            @click.self="confirming = false"
        >
            <section class="modal">
                <h2>Cerrar mi calificación</h2>
                <p>
                    Tu puntaje de <strong>{{ total }}</strong> se guardará y no
                    podrá ser editado en esta versión.
                </p>
                <Errors :errors="evaluation.errors" />
                <div class="row end">
                    <button class="btn btn-outline" @click="confirming = false">
                        Volver a revisar</button
                    ><button
                        class="btn btn-primary"
                        :disabled="evaluation.processing"
                        @click="grade"
                    >
                        Confirmar calificación
                    </button>
                </div>
            </section>
        </div></AppLayout
    >
</template>
