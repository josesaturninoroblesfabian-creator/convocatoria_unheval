<script setup>
import { computed, ref } from "vue";
import { Link, useForm } from "@inertiajs/vue3";
import AppLayout from "../Layouts/AppLayout.vue";
import Errors from "../Components/Errors.vue";
import Icon from "../Components/Icon.vue";
import { screeningRecordId } from "../lib/screening";
import { date, label, url } from "../lib/ui";

const props = defineProps({
    stage: String,
    call: Object,
    vacancy: Object,
    template: Object,
    configuration: Object,
    links: Object,
});
const internal = props.stage === "internal";
const title = internal ? "Aspectos internos" : "Aspectos externos";
const otherTitle = internal ? "Aspectos externos" : "Aspectos internos";
const reference = props.template ?? props.configuration;
const draft = props.configuration ?? props.template;
const canEdit = props.call.status === "BORRADOR" && Boolean(props.template);
const editing = ref(false);
const formatter = new Intl.NumberFormat("es-PE", { maximumFractionDigits: 2 });

function copyRubrics(source) {
    return (source?.rubrics ?? []).map((rubric) => ({
        key: rubric.key,
        name: rubric.name,
        guidance: rubric.guidance,
        note: rubric.note ?? "",
        ...(!internal && { max: rubric.max }),
        items: rubric.items.map((item) => ({
            ...item,
            ...(internal && {
                levels: item.levels.map((level) => ({ ...level })),
            }),
        })),
    }));
}

const form = useForm({
    category: reference?.category,
    version: reference?.version,
    revision:
        props.configuration?.revision ??
        props.configuration?.configured_at ??
        null,
    reference: draft?.reference ?? "",
    minimum: draft?.minimum,
    rubrics: copyRubrics(draft),
});

function maximum(section) {
    if (!internal) return Number(section.max) || 0;
    const cents = section.items.reduce((total, item) => {
        const points = item.levels.map((level) => Number(level.points) || 0);
        return total + Math.round(Math.max(0, ...points) * 100);
    }, 0);
    return cents / 100;
}

const total = computed(
    () =>
        form.rubrics.reduce(
            (sum, section) => sum + Math.round(maximum(section) * 100),
            0,
        ) / 100,
);

function newCriterion() {
    return {
        key: screeningRecordId(),
        name: "",
        ...(internal
            ? { levels: [{ points: "", description: "" }] }
            : { unit: "", points: "" }),
    };
}

function addRubric() {
    form.rubrics.push({
        key: screeningRecordId(),
        name: "",
        guidance: "",
        note: "",
        ...(!internal && { max: "" }),
        items: [newCriterion()],
    });
}

function restoreReference() {
    form.rubrics = copyRubrics(props.template);
    form.minimum = props.template.minimum;
    form.reference = "";
    form.clearErrors();
}

function sectionHasErrors(index) {
    return Object.keys(form.errors).some(
        (path) =>
            path === `rubrics.${index}` || path.startsWith(`rubrics.${index}.`),
    );
}

function save() {
    form.put(url(props.links.save), {
        preserveScroll: true,
        onSuccess: (page) => {
            form.revision = page.props.configuration.revision;
            editing.value = false;
        },
    });
}
</script>

<template>
    <AppLayout :title="title" :subtitle="vacancy.school">
        <template #actions>
            <Link :href="url(links.call)" class="btn btn-outline">
                <Icon name="ArrowLeft" :size="17" /> Volver a la convocatoria
            </Link>
        </template>

        <section class="panel">
            <div class="row between wrap">
                <div>
                    <p class="eyebrow">
                        {{ internal ? "Anexo 02" : "Anexo 01" }}
                    </p>
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
                <div class="row wrap">
                    <Link
                        :href="url(links.other)"
                        class="btn btn-outline btn-sm"
                        >{{ otherTitle }}</Link
                    >
                    <Link
                        v-if="canEdit"
                        :href="url(links.vacancy)"
                        class="btn btn-outline btn-sm"
                        >Ficha de la plaza</Link
                    >
                </div>
            </div>
        </section>

        <div v-if="!reference" class="notice notice-error">
            La categoría guardada no corresponde a esta modalidad. Corrige la
            ficha de la plaza para cargar la tabla.
        </div>

        <form v-else class="rubric-form" novalidate @submit.prevent="save">
            <section class="panel">
                <div class="panel-heading">
                    <Icon name="ClipboardCheck" />
                    <h2>Reglas de referencia</h2>
                </div>
                <div class="card-tags">
                    <span>{{ form.rubrics.length }} rubros</span>
                    <span>Máximo {{ formatter.format(total) }} puntos</span>
                    <span
                        >Mínimo
                        {{
                            form.minimum === ""
                                ? "por definir"
                                : formatter.format(form.minimum) + " puntos"
                        }}</span
                    >
                </div>
                <p>{{ reference.progression }}</p>
                <p class="muted">{{ reference.review }}</p>
                <p v-if="reference.disability_bonus" class="muted">
                    La bonificación del
                    {{ reference.disability_bonus.percent }} % por discapacidad
                    acreditada se aplica al
                    {{ reference.disability_bonus.stage }}. Se acredita con el
                    {{ reference.disability_bonus.evidence }}.
                </p>
                <a
                    :href="
                        reference.source.url.startsWith('/')
                            ? url(reference.source.url)
                            : reference.source.url
                    "
                    target="_blank"
                    rel="noopener"
                    class="text-link"
                >
                    {{ reference.source.label }}
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

            <div class="notice">
                <span v-if="configuration"
                    >Configuración guardada el
                    {{ date(configuration.configured_at, true) }}.</span
                >
                <span v-else
                    >Tabla de referencia precargada. Puedes guardarla para esta
                    plaza.</span
                >
                El uso de esta rúbrica en los expedientes se habilitará al
                configurar el flujo de evaluación.
            </div>
            <Errors :errors="form.errors" />

            <section class="panel">
                <div class="row between wrap">
                    <div class="panel-heading">
                        <Icon name="ListChecks" />
                        <h2>Rubros y puntajes</h2>
                    </div>
                    <button
                        v-if="canEdit"
                        type="button"
                        class="btn btn-outline btn-sm"
                        @click="editing = !editing"
                    >
                        {{
                            editing ? "Ver vista de lectura" : "Editar rúbrica"
                        }}
                    </button>
                </div>
                <p class="muted" v-if="internal">
                    Cada criterio tiene una escala propia. El máximo de un rubro
                    se calcula sumando el mayor puntaje de sus criterios.
                </p>
                <p class="muted" v-else>
                    El puntaje por unidad se aplica hasta el tope del rubro. La
                    calificación se registra sin redondeos.
                </p>

                <div v-if="editing" class="rubric-settings">
                    <label>
                        Mínimo de la etapa
                        <input
                            v-model.number="form.minimum"
                            type="number"
                            min="0"
                            step="0.01"
                        />
                    </label>
                    <label>
                        Referencia para los cambios
                        <input
                            v-model="form.reference"
                            maxlength="500"
                            placeholder="Resolución, anexo y numeral"
                        />
                        <small
                            >Obligatoria si cambias la rúbrica o el mínimo del
                            reglamento.</small
                        >
                    </label>
                    <button
                        type="button"
                        class="btn btn-outline btn-sm"
                        @click="restoreReference"
                    >
                        Restablecer el anexo de referencia
                    </button>
                </div>
                <p v-else-if="form.reference" class="muted">
                    Sustento registrado · {{ form.reference }}
                </p>

                <details
                    v-for="(section, index) in form.rubrics"
                    :key="section.key"
                    class="rubric-section"
                    :open="
                        index === 0 || sectionHasErrors(index) || !section.name
                    "
                >
                    <summary>
                        {{ index + 1 }}. {{ section.name || "Nuevo rubro" }}
                        <small
                            >Máx.
                            {{ formatter.format(maximum(section)) }} pts</small
                        >
                    </summary>
                    <div class="rubric-content">
                        <div v-if="editing" class="rubric-settings">
                            <label
                                >Nombre del rubro<input
                                    v-model="section.name"
                                    maxlength="180"
                            /></label>
                            <label v-if="!internal"
                                >Tope del rubro<input
                                    v-model.number="section.max"
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                            /></label>
                            <label
                                >Indicaciones de evaluación<textarea
                                    v-model="section.guidance"
                                    maxlength="2000"
                                    rows="2"
                                />
                            </label>
                            <button
                                v-if="form.rubrics.length > 1"
                                type="button"
                                class="text-link"
                                @click="form.rubrics.splice(index, 1)"
                            >
                                Quitar rubro
                            </button>
                        </div>
                        <p v-else-if="section.guidance" class="muted">
                            {{ section.guidance }}
                        </p>

                        <div v-if="!editing" class="table-scroll">
                            <table>
                                <thead>
                                    <tr>
                                        <th scope="col">Criterio</th>
                                        <th v-if="!internal" scope="col">
                                            Cómo se cuenta
                                        </th>
                                        <th scope="col">
                                            {{
                                                internal
                                                    ? "Escala de valoración"
                                                    : "Puntos"
                                            }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="item in section.items"
                                        :key="item.key"
                                    >
                                        <td>{{ item.name }}</td>
                                        <td v-if="!internal">
                                            {{ item.unit }}
                                        </td>
                                        <td v-if="internal">
                                            <ul class="scale-list">
                                                <li
                                                    v-for="(
                                                        level, position
                                                    ) in item.levels"
                                                    :key="position"
                                                >
                                                    <strong
                                                        >{{
                                                            formatter.format(
                                                                level.points,
                                                            )
                                                        }}
                                                        pts</strong
                                                    >
                                                    <span
                                                        v-if="level.description"
                                                    >
                                                        ·
                                                        {{
                                                            level.description
                                                        }}</span
                                                    >
                                                </li>
                                            </ul>
                                        </td>
                                        <td v-else>
                                            <strong>{{
                                                formatter.format(item.points)
                                            }}</strong>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <template v-else>
                            <div
                                v-for="(item, itemIndex) in section.items"
                                :key="item.key"
                                class="entry-card"
                            >
                                <label
                                    >Criterio {{ itemIndex + 1
                                    }}<input
                                        v-model="item.name"
                                        maxlength="250"
                                /></label>
                                <div v-if="!internal" class="rubric-settings">
                                    <label
                                        >Cómo se cuenta<input
                                            v-model="item.unit"
                                            maxlength="180"
                                    /></label>
                                    <label
                                        >Puntos por unidad<input
                                            v-model.number="item.points"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                    /></label>
                                </div>
                                <template v-else>
                                    <div
                                        v-for="(level, position) in item.levels"
                                        :key="position"
                                        class="rubric-settings"
                                    >
                                        <label
                                            >Puntos del nivel {{ position + 1
                                            }}<input
                                                v-model.number="level.points"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                        /></label>
                                        <label
                                            >Descripción del nivel
                                            (opcional)<input
                                                v-model="level.description"
                                                maxlength="500"
                                        /></label>
                                        <button
                                            v-if="item.levels.length > 1"
                                            type="button"
                                            class="text-link"
                                            @click="
                                                item.levels.splice(position, 1)
                                            "
                                        >
                                            Quitar nivel
                                        </button>
                                    </div>
                                    <button
                                        v-if="item.levels.length < 10"
                                        type="button"
                                        class="btn btn-outline btn-sm"
                                        @click="
                                            item.levels.push({
                                                points: '',
                                                description: '',
                                            })
                                        "
                                    >
                                        Añadir nivel
                                    </button>
                                </template>
                                <button
                                    v-if="section.items.length > 1"
                                    type="button"
                                    class="text-link"
                                    @click="section.items.splice(itemIndex, 1)"
                                >
                                    Quitar criterio
                                </button>
                            </div>
                            <button
                                v-if="section.items.length < 30"
                                type="button"
                                class="btn btn-outline btn-sm"
                                @click="section.items.push(newCriterion())"
                            >
                                Añadir criterio
                            </button>
                        </template>

                        <label v-if="canEdit">
                            Nota para la comisión (opcional)
                            <textarea
                                v-model="section.note"
                                maxlength="1000"
                                rows="2"
                            />
                        </label>
                        <p v-else-if="section.note">{{ section.note }}</p>
                    </div>
                </details>
                <button
                    v-if="editing && form.rubrics.length < 30"
                    type="button"
                    class="btn btn-outline btn-sm"
                    @click="addRubric"
                >
                    Añadir rubro
                </button>
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
                            : "Guardar " + title.toLowerCase()
                    }}
                </button>
            </div>
        </form>
    </AppLayout>
</template>

<style scoped>
.rubric-form {
    display: grid;
    gap: 1.25rem;
}
.attached-bases {
    margin-top: 1rem;
}
.rubric-section {
    border-top: 1px solid var(--border, #e1e8ef);
}
.rubric-section summary {
    cursor: pointer;
    padding: 0.9rem 0;
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
.rubric-settings {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 12rem), 1fr));
    gap: 0.75rem;
    align-items: end;
    margin-bottom: 1rem;
}
.rubric-settings button {
    justify-self: start;
}
.scale-list {
    list-style: none;
    margin: 0;
    padding: 0;
}
</style>
