<script setup>
import { computed, ref } from "vue";
import { router, useForm } from "@inertiajs/vue3";
import Errors from "./Errors.vue";
import Icon from "./Icon.vue";
import { url } from "../lib/ui";

const props = defineProps({
    applicationId: Number,
    template: Object,
    progress: Object,
    ownEvaluation: Object,
    canWrite: Boolean,
});

const phase = computed(() => props.progress?.stage);
const proposal = computed(() => props.progress?.proposal);
const outcome = computed(() => props.progress?.outcome);
const minimums = computed(() => props.progress?.minimums);
const hasConfirmed = computed(() => props.progress?.ownExternal != null);
const adjustmentRevision = ref(null);
const adjusting = computed(() => adjustmentRevision.value !== null);
const pending = ref(null);

const groups = computed(() => [
    {
        stage: "EXTERNA",
        title: "Aspectos externos",
        criteria: props.template.external,
        saved: proposal.value?.scores ?? props.progress?.ownExternal,
    },
    {
        stage: "INTERNA",
        title: "Aspectos internos",
        criteria: props.template.internal,
        saved: props.ownEvaluation?.scores,
    },
]);

const action = computed(() => {
    if (!props.canWrite) return null;
    if (phase.value === "EXTERNA") {
        if (!proposal.value || adjusting.value) return "proponer";
        return hasConfirmed.value ? null : "confirmar";
    }
    if (phase.value === "INTERNA" && !props.ownEvaluation) return "interna";
    return null;
});

const activeCriteria = computed(() =>
    action.value === "proponer"
        ? props.template.external
        : action.value === "interna"
          ? props.template.internal
          : [],
);

const form = useForm({ scores: {}, itemScores: {}, notes: "" });
const points = new Intl.NumberFormat("es-PE", { maximumFractionDigits: 3 });
const itemKey = (criterion, index) => criterion.key + "-" + index;
function subtotal(criterion) {
    if (!criterion.items) return Number(form.scores[criterion.key] || 0);
    return criterion.items.reduce(
        (sum, _, index) =>
            sum + Number(form.itemScores[itemKey(criterion, index)] ?? 0),
        0,
    );
}
const draftTotal = computed(() =>
    activeCriteria.value.reduce(
        (sum, criterion) => sum + subtotal(criterion),
        0,
    ),
);
const buttonLabels = {
    proponer: "Revisar propuesta externa",
    confirmar: "Revisar mi conformidad",
    interna: "Revisar mi calificación interna",
};
const outcomeLabels = {
    NO_SUPERA_EXTERNA: "El expediente no alcanzó el mínimo externo",
    NO_SUPERA_INTERNA: "El expediente no alcanzó el mínimo interno",
    NO_SUPERA_TOTAL: "El expediente no alcanzó el puntaje total mínimo",
    SUPERA_MINIMOS: "El expediente alcanzó los mínimos de evaluación",
};

function isEditing(stage) {
    return (
        (stage === "EXTERNA" && action.value === "proponer") ||
        (stage === "INTERNA" && action.value === "interna")
    );
}

function startAdjustment() {
    form.scores = { ...proposal.value.scores };
    form.notes = "";
    form.clearErrors();
    adjustmentRevision.value = proposal.value.revision;
}

function cancelAdjustment() {
    adjustmentRevision.value = null;
    form.resetAndClearErrors();
}

function reviewSubmission() {
    if (!action.value) return;
    const stage = action.value === "interna" ? "interna" : "externa";
    const payload = { stage, notes: form.notes };

    if (stage === "externa") {
        payload.action = action.value;
        payload.revision =
            adjustmentRevision.value ?? proposal.value?.revision ?? 0;
    }
    if (stage === "interna" && activeCriteria.value[0]?.items) {
        payload.item_scores = Object.fromEntries(
            activeCriteria.value.map((criterion) => [
                criterion.key,
                criterion.items.map(
                    (_, index) => form.itemScores[itemKey(criterion, index)],
                ),
            ]),
        );
    } else if (action.value !== "confirmar") {
        payload.scores = Object.fromEntries(
            activeCriteria.value.map(({ key }) => [key, form.scores[key]]),
        );
    }

    pending.value = {
        payload,
        total:
            action.value === "confirmar"
                ? proposal.value.total
                : draftTotal.value,
        adjustsProposal: adjusting.value,
    };
}

function submit() {
    if (!pending.value || form.processing) return;
    const { payload } = pending.value;
    form.transform(() => payload).post(
        url(`/expedientes/${props.applicationId}/evaluacion`),
        {
            preserveScroll: true,
            onSuccess: () => {
                pending.value = null;
                cancelAdjustment();
            },
        },
    );
}

function refresh() {
    router.reload({
        only: [
            "application",
            "evaluationProgress",
            "ownEvaluation",
            "evaluationCount",
        ],
    });
}
</script>

<template>
    <div>
        <div class="notice">
            <strong>Nombramiento docente · rúbrica de referencia</strong>
            <p v-if="minimums">
                Mínimos para esta plaza:
                {{ points.format(minimums.external) }} externos,
                {{ points.format(minimums.internal) }} internos y
                {{ points.format(minimums.total) }} en total.
            </p>
            <p>
                La comisión acuerda el puntaje externo. Cada jurado registra su
                calificación interna y el sistema calcula el promedio.
            </p>
            <a
                v-if="template.source?.path"
                :href="url(template.source.path)"
                target="_blank"
                rel="noopener"
                class="text-link"
            >
                Consultar los anexos de evaluación
                <Icon name="ExternalLink" :size="14" />
            </a>
        </div>

        <div
            v-if="outcome"
            class="notice"
            :class="outcome.passed ? 'notice-success' : 'notice-error'"
        >
            <strong>{{ outcomeLabels[outcome.stage] }}</strong>
            <p>
                Externa {{ points.format(outcome.external) }}.
                <template v-if="outcome.internal != null">
                    Interna {{ points.format(outcome.internal) }}.
                </template>
                <template v-if="outcome.total != null">
                    Total {{ points.format(outcome.total) }}.
                </template>
            </p>
        </div>
        <div
            v-else-if="progress?.external != null"
            class="notice notice-success"
        >
            <strong>
                Evaluación externa cerrada ·
                {{ points.format(progress.external) }} puntos
            </strong>
        </div>

        <div v-if="phase === 'EXTERNA' && proposal" class="notice">
            <strong
                >Propuesta externa · revisión {{ proposal.revision }}</strong
            >
            <p>
                {{ points.format(proposal.total) }} puntos ·
                {{ proposal.confirmed }} de
                {{ proposal.required }} conformidades.
            </p>
            <p v-if="proposal.notes">{{ proposal.notes }}</p>
            <p v-if="hasConfirmed">Ya diste tu conformidad a esta revisión.</p>
            <button
                v-if="canWrite && !adjusting && !form.processing"
                type="button"
                class="btn btn-outline btn-sm"
                @click="startAdjustment"
            >
                Proponer ajuste
            </button>
        </div>
        <div v-if="adjusting" class="notice">
            Guardar una revisión nueva vuelve a solicitar la conformidad de la
            comisión.
            <button
                type="button"
                class="text-link"
                :disabled="form.processing"
                @click="cancelAdjustment"
            >
                Cancelar ajuste
            </button>
        </div>
        <div v-if="ownEvaluation" class="notice notice-success">
            <strong>Tu calificación interna quedó registrada.</strong>
            <p v-if="phase === 'INTERNA'">
                La evaluación se consolida cuando finalicen los jurados
                asignados.
            </p>
            <p v-if="ownEvaluation.notes">{{ ownEvaluation.notes }}</p>
        </div>

        <Errors :errors="form.errors" />
        <form @submit.prevent="reviewSubmission">
            <details
                v-for="group in groups"
                :key="`${group.stage}-${phase}`"
                :open="
                    group.stage === phase ||
                    phase === 'COMPLETA' ||
                    (phase === 'NO_SUPERA_EXTERNA' && group.stage === 'EXTERNA')
                "
            >
                <summary class="score-input rubric-summary">
                    <span class="row">
                        <Icon name="ChevronRight" :size="15" />
                        {{ group.title }} · {{ group.criteria.length }} rubros
                    </span>
                    <b>
                        Máx.
                        {{
                            points.format(
                                group.criteria.reduce(
                                    (sum, criterion) =>
                                        sum + Number(criterion.max),
                                    0,
                                ),
                            )
                        }}
                    </b>
                </summary>
                <div
                    v-for="criterion in group.criteria"
                    :key="criterion.key"
                    :class="criterion.items ? 'panel stack' : 'score-input'"
                >
                    <component
                        :is="
                            isEditing(group.stage) && !criterion.items
                                ? 'label'
                                : 'div'
                        "
                        :for="
                            isEditing(group.stage) && !criterion.items
                                ? 'nombramiento-score-' + criterion.key
                                : undefined
                        "
                    >
                        {{ criterion.name }}
                        <small
                            >Máximo
                            {{ points.format(criterion.max) }} puntos</small
                        >
                        <span
                            v-if="criterion.guidance"
                            class="rubric-guidance muted"
                        >
                            {{ criterion.guidance }}
                        </span>
                    </component>
                    <template v-if="criterion.items">
                        <div
                            v-if="
                                isEditing(group.stage) ||
                                group.saved?._items?.[criterion.key]
                            "
                        >
                            <label
                                v-for="(item, index) in criterion.items"
                                :key="index"
                                class="score-input"
                            >
                                <span>{{ index + 1 }}. {{ item }}</span>
                                <select
                                    v-if="isEditing(group.stage)"
                                    v-model="
                                        form.itemScores[
                                            itemKey(criterion, index)
                                        ]
                                    "
                                    required
                                    :disabled="form.processing"
                                >
                                    <option disabled :value="undefined">
                                        Seleccionar puntaje
                                    </option>
                                    <option
                                        v-for="value in criterion.scale"
                                        :key="value"
                                        :value="value"
                                    >
                                        {{ points.format(value) }} puntos
                                    </option>
                                </select>
                                <b v-else>{{
                                    points.format(
                                        group.saved._items[criterion.key][
                                            index
                                        ],
                                    )
                                }}</b>
                            </label>
                            <div class="score-total">
                                <span>Subtotal de {{ criterion.name }}</span>
                                <b>{{
                                    points.format(
                                        isEditing(group.stage)
                                            ? subtotal(criterion)
                                            : group.saved[criterion.key],
                                    )
                                }}</b>
                            </div>
                        </div>
                    </template>
                    <input
                        v-else-if="isEditing(group.stage)"
                        :id="'nombramiento-score-' + criterion.key"
                        v-model="form.scores[criterion.key]"
                        type="number"
                        min="0"
                        :max="criterion.max"
                        step="0.001"
                        required
                        :disabled="form.processing"
                    />
                    <b v-else-if="group.saved?.[criterion.key] != null">
                        {{ points.format(group.saved[criterion.key]) }}
                    </b>
                </div>
            </details>

            <div v-if="activeCriteria.length" class="score-total">
                <span>Subtotal de la etapa</span>
                <b>{{ points.format(draftTotal) }}</b>
            </div>
            <template v-if="action">
                <label>
                    {{
                        action === "confirmar"
                            ? "Fundamento de la conformidad"
                            : "Fundamento de los puntajes"
                    }}
                    <textarea
                        v-model="form.notes"
                        required
                        minlength="5"
                        maxlength="3000"
                        rows="3"
                        :disabled="form.processing"
                        placeholder="Indica qué sustentos respaldan la decisión."
                    ></textarea>
                </label>
                <button
                    class="btn btn-primary full"
                    :disabled="form.processing"
                >
                    {{ buttonLabels[action] }}
                </button>
            </template>
            <button
                v-else-if="canWrite && !outcome"
                type="button"
                class="btn btn-outline full"
                @click="refresh"
            >
                Actualizar estado de la evaluación
            </button>
        </form>

        <div
            v-if="pending"
            class="modal-backdrop"
            @click.self="!form.processing && (pending = null)"
        >
            <section
                class="modal"
                role="dialog"
                aria-modal="true"
                aria-labelledby="nombramiento-review-confirmation"
            >
                <h2 id="nombramiento-review-confirmation">
                    {{ buttonLabels[pending.payload.action ?? "interna"] }}
                </h2>
                <p>
                    Se registrarán
                    <strong>{{ points.format(pending.total) }} puntos</strong>
                    para la etapa {{ pending.payload.stage }}.
                    <template v-if="pending.payload.action === 'proponer'">
                        Al proponerla también das tu conformidad.
                    </template>
                    <template v-if="pending.adjustsProposal">
                        Los demás jurados deberán confirmar la nueva revisión.
                    </template>
                </p>
                <Errors :errors="form.errors" />
                <div class="row end">
                    <button
                        type="button"
                        class="btn btn-outline"
                        :disabled="form.processing"
                        @click="pending = null"
                    >
                        Volver a revisar
                    </button>
                    <button
                        type="button"
                        class="btn btn-primary"
                        :disabled="form.processing"
                        @click="submit"
                    >
                        Confirmar registro
                    </button>
                </div>
            </section>
        </div>
    </div>
</template>

<style scoped>
.rubric-summary {
    cursor: pointer;
}
details[open] > .rubric-summary svg {
    transform: rotate(90deg);
}
.rubric-guidance {
    display: block;
    margin-top: 4px;
    line-height: 1.5;
}
</style>
