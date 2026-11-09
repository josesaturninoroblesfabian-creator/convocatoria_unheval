<script setup>
import { computed, ref } from "vue";
import { Link, useForm } from "@inertiajs/vue3";
import AppLayout from "../Layouts/AppLayout.vue";
import Errors from "../Components/Errors.vue";
import Icon from "../Components/Icon.vue";
import { date, label, url } from "../lib/ui";

const props = defineProps({
    call: Object,
    vacancy: Object,
    template: Object,
    configuration: Object,
    links: Object,
});
const rubric = computed(() => props.template ?? props.configuration);
const canEdit = computed(
    () => props.call.status === "BORRADOR" && props.template !== null,
);
const savedRubrics = new Map(
    (props.configuration?.rubrics ?? []).map((section) => [
        section.key,
        section,
    ]),
);
const form = useForm({
    category: rubric.value?.category,
    version: rubric.value?.version,
    reference: props.configuration?.reference ?? "",
    rubrics: Object.fromEntries(
        (rubric.value?.rubrics ?? []).map((section) => {
            const saved = savedRubrics.get(section.key);
            const points = new Map(
                (saved?.items ?? []).map((item) => [item.key, item.points]),
            );
            return [
                section.key,
                {
                    points: Object.fromEntries(
                        section.items.map((item) => [
                            item.key,
                            points.get(item.key) ?? item.points,
                        ]),
                    ),
                    note: saved?.note ?? "",
                },
            ];
        }),
    ),
});
const hasAdjustments = computed(() =>
    (props.template?.rubrics ?? []).some((section) =>
        section.items.some(
            (item) =>
                Number(form.rubrics[section.key].points[item.key]) !==
                item.points,
        ),
    ),
);
const editPoints = ref(hasAdjustments.value);
const formatter = new Intl.NumberFormat("es-PE", {
    maximumFractionDigits: 2,
});

function sectionHasErrors(key) {
    return Object.keys(form.errors).some(
        (path) =>
            path === `rubrics.${key}` || path.startsWith(`rubrics.${key}.`),
    );
}

function save() {
    form.put(url(props.links.save), { preserveScroll: true });
}
</script>

<template>
    <AppLayout title="Aspectos externos" :subtitle="vacancy.school">
        <template #actions>
            <Link :href="url(links.call)" class="btn btn-outline">
                <Icon name="ArrowLeft" :size="17" /> Volver a la convocatoria
            </Link>
        </template>

        <section class="panel">
            <div class="row between wrap">
                <div>
                    <p class="eyebrow">Evaluación curricular · Anexo 01</p>
                    <h2>{{ vacancy.school }}</h2>
                    <p class="muted">
                        {{ vacancy.faculty }} · AIRHSP {{ vacancy.airhsp }}
                    </p>
                    <div class="card-tags">
                        <span>{{ label(call.modality) }}</span>
                        <span>{{ label(vacancy.category) }}</span>
                        <span v-if="call.modality !== 'CONTRATO'">{{
                            label(vacancy.dedication)
                        }}</span>
                        <span>{{ vacancy.weekly_hours }} horas</span>
                    </div>
                </div>
                <Link
                    v-if="call.status === 'BORRADOR'"
                    :href="url(links.vacancy)"
                    class="btn btn-outline btn-sm"
                >
                    Ver ficha de la plaza
                </Link>
            </div>
        </section>

        <div v-if="!rubric" class="notice notice-error">
            La categoría guardada no corresponde a esta modalidad. Corrige la
            ficha de la plaza para cargar la tabla.
        </div>

        <form v-else class="external-rubric" novalidate @submit.prevent="save">
            <section class="panel">
                <div class="panel-heading">
                    <Icon name="ClipboardCheck" />
                    <h2>Reglas de la etapa</h2>
                </div>
                <div class="card-tags">
                    <span>{{ rubric.rubrics.length }} rubros</span>
                    <span>Máximo {{ rubric.maximum }} puntos</span>
                    <span>Mínimo {{ rubric.minimum }} puntos</span>
                </div>
                <p>{{ rubric.progression }}</p>
                <p class="muted">
                    {{ rubric.review }}
                </p>
                <p class="muted">
                    La bonificación del {{ rubric.disability_bonus.percent }} %
                    por discapacidad acreditada se aplica al
                    {{ rubric.disability_bonus.stage }}. Se acredita con el
                    {{ rubric.disability_bonus.evidence }}.
                </p>
                <a
                    :href="
                        rubric.source.url.startsWith('/')
                            ? url(rubric.source.url)
                            : rubric.source.url
                    "
                    target="_blank"
                    rel="noopener"
                    class="text-link"
                >
                    {{ rubric.source.label }}
                    <Icon name="ArrowUpRight" :size="15" />
                </a>
                <div
                    v-if="vacancy.public_documents.length"
                    class="attached-bases"
                >
                    <p class="muted">Documentos adjuntos de la plaza</p>
                    <div class="row wrap">
                        <a
                            v-for="document in vacancy.public_documents"
                            :key="document.id"
                            :href="url(document.path)"
                            target="_blank"
                            rel="noopener"
                            class="btn btn-outline btn-sm"
                        >
                            {{ document.name }}
                            <Icon name="ArrowUpRight" :size="15" />
                        </a>
                    </div>
                </div>
            </section>

            <div class="notice row between wrap">
                <span v-if="configuration">
                    Borrador guardado el
                    {{ date(configuration.configured_at, true) }}. Su aplicación
                    a los expedientes se habilitará al completar el flujo de
                    evaluación.
                </span>
                <span v-else
                    >Tabla precargada. Revisa los rubros y guarda la
                    configuración de esta plaza.</span
                >
                <Link
                    v-if="canEdit"
                    :href="url(links.requirements)"
                    class="text-link"
                >
                    Ver requisitos de aptitud
                </Link>
            </div>

            <Errors :errors="form.errors" />

            <section v-if="canEdit" class="panel">
                <label class="check-label">
                    <input v-model="editPoints" type="checkbox" />
                    Registrar puntajes distintos de la tabla de referencia
                </label>
                <p class="muted">
                    Si las bases aprobadas cambian algún valor, indica el
                    documento y el numeral que lo respaldan.
                </p>
                <label v-if="editPoints || hasAdjustments">
                    Documento que respalda los cambios
                    <input
                        v-model="form.reference"
                        maxlength="500"
                        placeholder="Resolución, anexo y numeral"
                    />
                </label>
            </section>

            <section class="panel">
                <div class="panel-heading">
                    <Icon name="ListChecks" />
                    <h2>Rubros y puntajes</h2>
                </div>
                <p class="muted">
                    El Anexo 01 fija los valores por criterio y el tope de cada
                    rubro. La calificación se registra sin redondeos.
                </p>
                <details
                    v-for="(section, index) in rubric.rubrics"
                    :key="section.key"
                    class="rubric-section"
                    :open="index === 0 || sectionHasErrors(section.key)"
                >
                    <summary>
                        <span>{{ index + 1 }}. {{ section.name }}</span>
                        <small
                            >Máx. {{ formatter.format(section.max) }} pts</small
                        >
                    </summary>
                    <div class="rubric-content">
                        <p class="muted">{{ section.guidance }}</p>
                        <div class="table-scroll">
                            <table>
                                <thead>
                                    <tr>
                                        <th scope="col">Criterio</th>
                                        <th scope="col">Cómo se cuenta</th>
                                        <th scope="col">Puntos</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="item in section.items"
                                        :key="item.key"
                                    >
                                        <td>{{ item.name }}</td>
                                        <td>{{ item.unit }}</td>
                                        <td>
                                            <input
                                                v-if="canEdit && editPoints"
                                                v-model.number="
                                                    form.rubrics[section.key]
                                                        .points[item.key]
                                                "
                                                type="number"
                                                min="0.01"
                                                :max="section.max"
                                                step="0.01"
                                                :aria-label="`Puntos de ${item.name}`"
                                                class="points-input"
                                            />
                                            <strong v-else>{{
                                                formatter.format(
                                                    form.rubrics[section.key]
                                                        .points[item.key],
                                                )
                                            }}</strong>
                                            <small
                                                v-if="
                                                    template &&
                                                    Number(
                                                        form.rubrics[
                                                            section.key
                                                        ].points[item.key],
                                                    ) !== item.points
                                                "
                                                class="muted reference-points"
                                            >
                                                Referencia
                                                {{
                                                    formatter.format(
                                                        item.points,
                                                    )
                                                }}
                                            </small>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <label v-if="canEdit">
                            Nota para revisar los sustentos (opcional)
                            <textarea
                                v-model="form.rubrics[section.key].note"
                                maxlength="1000"
                                rows="2"
                            />
                        </label>
                        <p v-else-if="form.rubrics[section.key].note">
                            {{ form.rubrics[section.key].note }}
                        </p>
                    </div>
                </details>
            </section>

            <div v-if="canEdit" class="row end">
                <button
                    type="submit"
                    class="btn btn-primary"
                    :disabled="form.processing"
                >
                    {{
                        form.processing
                            ? "Guardando…"
                            : "Guardar aspectos externos"
                    }}
                </button>
            </div>
        </form>
    </AppLayout>
</template>

<style scoped>
.external-rubric {
    display: grid;
    gap: 1.25rem;
}

.attached-bases {
    margin-top: 1rem;
}

.rubric-section {
    border-top: 1px solid var(--line, #e1e8ef);
}

.rubric-section summary {
    cursor: pointer;
    padding: 0.9rem 0;
    color: inherit;
    font-weight: 650;
}

.rubric-section summary small {
    margin-inline-start: 0.7rem;
    white-space: nowrap;
    font-weight: 500;
}

.rubric-content {
    padding-bottom: 1rem;
}

.rubric-content label {
    margin-top: 0.8rem;
}

.points-input {
    width: 6rem;
}

.reference-points {
    display: block;
    white-space: nowrap;
}
</style>
