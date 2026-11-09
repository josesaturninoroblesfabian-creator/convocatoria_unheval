<script setup>
import { computed, ref } from "vue";
import { Link, useForm } from "@inertiajs/vue3";
import AppLayout from "../Layouts/AppLayout.vue";
import Icon from "../Components/Icon.vue";
import Errors from "../Components/Errors.vue";
import { label, url } from "../lib/ui";
const props = defineProps({
    call: Object,
    hasVacancies: Boolean,
    nombramientoTemplate: Object,
});
const referenceMode = "nombramiento_referencia";
const localDate = (value) =>
    value
        ? new Date(new Date(value).getTime() - 5 * 3600000)
              .toISOString()
              .slice(0, 19)
        : "";
const defaultRubric = () => [
    { key: "formacion", name: "Formación (ejemplo de prueba)", max: 10 },
    {
        key: "experiencia",
        name: "Experiencia (ejemplo de prueba)",
        max: 10,
    },
    {
        key: "capacitacion",
        name: "Capacitación (ejemplo de prueba)",
        max: 5,
    },
    { key: "clase", name: "Clase magistral (ejemplo de prueba)", max: 50 },
    {
        key: "entrevista",
        name: "Entrevista (ejemplo de prueba)",
        max: 25,
    },
];
const copyRubric = (rubric) =>
    rubric.map(({ key, name, max }) => ({ key, name, max }));
const form = useForm({
    code: props.call?.code || "",
    title: props.call?.title || "",
    description: props.call?.description || "",
    resolution: props.call?.resolution || "",
    modality: props.call?.modality || "NOMBRAMIENTO",
    opens_at: localDate(props.call?.opens_at),
    closes_at: localDate(props.call?.closes_at),
    is_demo: props.call?.is_demo ?? true,
    schedule: props.call?.schedule || [
        {
            label: "Inscripción digital",
            date: "Consulta la apertura y el cierre de inscripción de esta convocatoria.",
        },
    ],
    evaluation_mode: props.call?.evaluation_mode ?? null,
    rubric: copyRubric(
        props.call?.rubric?.length ? props.call.rubric : defaultRubric(),
    ),
});
const usesTemplate = computed(() => form.evaluation_mode === referenceMode);
const confirmingModeChange = ref(false);
let previousFreeRubric = null;
const rubricGroups = computed(() =>
    props.nombramientoTemplate
        ? [
              {
                  title: "Aspectos externos",
                  criteria: props.nombramientoTemplate.external,
              },
              {
                  title: "Aspectos internos",
                  criteria: props.nombramientoTemplate.internal,
              },
          ]
        : [],
);
const totalPoints = (criteria) =>
    criteria.reduce((sum, criterion) => sum + Number(criterion.max || 0), 0);

function changeEvaluationMode() {
    if (usesTemplate.value) {
        form.evaluation_mode = null;
        form.rubric = previousFreeRubric ?? defaultRubric();
    } else if (form.modality === "NOMBRAMIENTO" && props.nombramientoTemplate) {
        previousFreeRubric = copyRubric(form.rubric);
        form.evaluation_mode = referenceMode;
        form.rubric = copyRubric(
            rubricGroups.value.flatMap((group) => group.criteria),
        );
    }
    confirmingModeChange.value = false;
}

function submit() {
    if (confirmingModeChange.value) return;
    form.put(url("/gestion/convocatorias/" + props.call.id));
}
</script>
<template>
    <AppLayout
        title="Editar convocatoria"
        subtitle="La convocatoria se guardará como borrador."
        ><Errors :errors="form.errors" />
        <form @submit.prevent="submit" class="editor-form">
            <section class="panel">
                <div class="panel-heading">
                    <Icon name="FileText" />
                    <h2>Información general</h2>
                </div>
                <div class="form-grid">
                    <label
                        >Código de convocatoria<input
                            v-model="form.code"
                            required
                            maxlength="60"
                            placeholder="DEMO-NOMBRAMIENTO-2026-V" /></label
                    ><label
                        >Modalidad<select
                            v-model="form.modality"
                            :disabled="usesTemplate || confirmingModeChange"
                        >
                            <option value="NOMBRAMIENTO">Nombramiento</option>
                            <option value="CONTRATO">Contrato</option>
                        </select></label
                    >
                </div>
                <label
                    >Título<input
                        v-model="form.title"
                        required
                        maxlength="220"
                        placeholder="Concurso público de plazas docentes…" /></label
                ><label
                    >Descripción<textarea
                        v-model="form.description"
                        required
                        rows="4"
                        maxlength="5000"
                    ></textarea></label
                ><label
                    >Resolución o referencia documental<input
                        v-model="form.resolution"
                        maxlength="180"
                        placeholder="Número de resolución de aprobación" /></label
                ><label class="check-label"
                    ><input v-model="form.is_demo" type="checkbox" />Es una
                    convocatoria de demostración</label
                >
                <div class="notice">
                    La versión actual habilita publicación y evaluación de
                    procesos de demostración. Los procesos oficiales se
                    conservan en borrador hasta implementar y validar su
                    normativa específica.
                </div>
            </section>
            <section class="panel">
                <div class="panel-heading">
                    <Icon name="CalendarDays" />
                    <h2>Fechas de inscripción</h2>
                </div>
                <p class="muted">
                    Hora de Perú (UTC−5). El servidor verifica estas fechas al
                    guardar y al enviar cada expediente.
                </p>
                <div class="form-grid">
                    <label
                        >Inicio<input
                            v-model="form.opens_at"
                            type="datetime-local"
                            step="1"
                            required /></label
                    ><label
                        >Cierre<input
                            v-model="form.closes_at"
                            type="datetime-local"
                            step="1"
                            required
                    /></label>
                </div>
                <h3>Cronograma general de la convocatoria</h3>
                <div
                    v-for="(s, i) in form.schedule"
                    :key="i"
                    class="dynamic-row"
                >
                    <input
                        v-model="s.label"
                        aria-label="Actividad del cronograma"
                        placeholder="Actividad"
                        required
                    /><input
                        v-model="s.date"
                        aria-label="Fecha de la actividad"
                        placeholder="Fecha o rango publicado"
                        required
                    /><button
                        type="button"
                        class="icon-btn"
                        aria-label="Quitar actividad"
                        :disabled="form.schedule.length === 1"
                        @click="form.schedule.splice(i, 1)"
                    >
                        <Icon name="X" :size="17" />
                    </button>
                </div>
                <button
                    type="button"
                    class="btn btn-outline btn-sm"
                    @click="form.schedule.push({ label: '', date: '' })"
                >
                    <Icon name="Plus" :size="15" />Añadir actividad
                </button>
            </section>
            <section v-if="hasVacancies" class="panel">
                <div class="panel-heading">
                    <Icon name="ClipboardCheck" />
                    <h2>Evaluación de prueba</h2>
                </div>
                <div
                    v-if="
                        usesTemplate ||
                        (form.modality === 'NOMBRAMIENTO' &&
                            nombramientoTemplate)
                    "
                    class="notice"
                >
                    <b>{{
                        usesTemplate
                            ? "Plantilla de nombramiento aplicada"
                            : "Plantilla de nombramiento disponible"
                    }}</b>
                    <p>
                        La plantilla de referencia reúne los aspectos externos e
                        internos y los mínimos por categoría. Los puntajes de
                        los rubros se registran durante la revisión.
                    </p>
                    <template v-if="confirmingModeChange">
                        <p>
                            {{
                                usesTemplate
                                    ? "Volver a la evaluación libre reemplazará la plantilla por una rúbrica editable de prueba."
                                    : "Aplicar la plantilla reemplazará todos los rubros de evaluación de este borrador."
                            }}
                        </p>
                        <div class="row wrap">
                            <button
                                type="button"
                                class="btn btn-primary btn-sm"
                                @click="changeEvaluationMode"
                            >
                                {{
                                    usesTemplate
                                        ? "Confirmar cambio a libre"
                                        : "Confirmar reemplazo"
                                }}
                            </button>
                            <button
                                type="button"
                                class="btn btn-outline btn-sm"
                                @click="confirmingModeChange = false"
                            >
                                Conservar rúbrica actual
                            </button>
                        </div>
                    </template>
                    <button
                        v-else
                        type="button"
                        class="btn btn-outline btn-sm"
                        @click="confirmingModeChange = true"
                    >
                        {{
                            usesTemplate
                                ? "Volver a evaluación libre"
                                : "Aplicar plantilla"
                        }}
                    </button>
                </div>
                <template v-if="usesTemplate">
                    <div v-if="nombramientoTemplate">
                        <div v-for="group in rubricGroups" :key="group.title">
                            <h3>
                                {{ group.title }} ·
                                {{ totalPoints(group.criteria) }} puntos
                            </h3>
                            <div class="table-scroll">
                                <table :aria-label="group.title">
                                    <thead>
                                        <tr>
                                            <th scope="col">Rubro</th>
                                            <th scope="col">Máximo</th>
                                            <th scope="col">
                                                Orientación para la revisión
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="criterion in group.criteria"
                                            :key="criterion.key"
                                        >
                                            <td>{{ criterion.name }}</td>
                                            <td>{{ criterion.max }}</td>
                                            <td>{{ criterion.guidance }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <h3>Mínimos por categoría</h3>
                        <p class="muted">
                            Se comprueba el mínimo de cada bloque y el puntaje
                            total para la categoría de la plaza.
                        </p>
                        <div class="table-scroll">
                            <table aria-label="Mínimos por categoría">
                                <thead>
                                    <tr>
                                        <th scope="col">Categoría</th>
                                        <th scope="col">Externo</th>
                                        <th scope="col">Interno</th>
                                        <th scope="col">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(
                                            minimum, category
                                        ) in nombramientoTemplate.minimums"
                                        :key="category"
                                    >
                                        <td>{{ label(category) }}</td>
                                        <td>{{ minimum.external }}</td>
                                        <td>{{ minimum.internal }}</td>
                                        <td>{{ minimum.total }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>
                <template v-else>
                    <p class="muted">
                        En la evaluación libre, el gestor define los rubros y
                        sus máximos. Cada jurado registra los puntajes de
                        prueba.
                    </p>
                    <div
                        v-for="(r, i) in form.rubric"
                        :key="i"
                        class="rubric-editor"
                    >
                        <label
                            >Clave<input
                                v-model="r.key"
                                required
                                placeholder="formacion" /></label
                        ><label
                            >Nombre del rubro<input
                                v-model="r.name"
                                required /></label
                        ><label
                            >Máximo<input
                                v-model.number="r.max"
                                required
                                type="number"
                                min="0.001"
                                max="100"
                                step="0.001" /></label
                        ><button
                            class="icon-btn"
                            type="button"
                            aria-label="Quitar rubro"
                            :disabled="form.rubric.length === 1"
                            @click="form.rubric.splice(i, 1)"
                        >
                            <Icon name="X" :size="17" />
                        </button>
                    </div>
                    <div class="row between">
                        <button
                            type="button"
                            class="btn btn-outline btn-sm"
                            @click="
                                form.rubric.push({
                                    key: 'rubro_' + (form.rubric.length + 1),
                                    name: '',
                                    max: 10,
                                })
                            "
                        >
                            <Icon name="Plus" :size="15" />Añadir rubro</button
                        ><b
                            >Total máximo:
                            {{ totalPoints(form.rubric) }}
                            puntos</b
                        >
                    </div>
                </template>
            </section>
            <div class="row end">
                <Link
                    :href="url('/gestion/convocatorias')"
                    class="btn btn-outline"
                    >Cancelar</Link
                ><button
                    class="btn btn-primary"
                    :disabled="form.processing || confirmingModeChange"
                >
                    {{ form.processing ? "Guardando…" : "Guardar borrador"
                    }}<Icon name="Check" :size="18" />
                </button>
            </div></form
    ></AppLayout>
</template>
