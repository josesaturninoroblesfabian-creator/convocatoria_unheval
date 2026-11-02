<script setup>
import { Link, useForm } from "@inertiajs/vue3";
import AppLayout from "../Layouts/AppLayout.vue";
import Icon from "../Components/Icon.vue";
import Errors from "../Components/Errors.vue";
import { url } from "../lib/ui";
const props = defineProps({ call: Object });
const localDate = (value) =>
    value
        ? new Date(new Date(value).getTime() - 5 * 3600000)
              .toISOString()
              .slice(0, 19)
        : "";
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
        { label: "Inscripción y presentación de expedientes", date: "" },
    ],
    rubric: props.call?.rubric || [
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
    ],
});
function submit() {
    if (props.call) form.put(url("/gestion/convocatorias/" + props.call.id));
    else form.post(url("/gestion/convocatorias"));
}
</script>
<template>
    <AppLayout
        :title="call ? 'Editar convocatoria' : 'Nueva convocatoria'"
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
                        >Modalidad<select v-model="form.modality">
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
                <h3>Cronograma visible al postulante</h3>
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
            <section class="panel">
                <div class="panel-heading">
                    <Icon name="ClipboardCheck" />
                    <h2>Rubros de evaluación de prueba</h2>
                </div>
                <p class="muted">
                    Los máximos son configurables. Esta ficha registra puntajes
                    manuales por jurado y no implementa los cálculos detallados
                    de los anexos oficiales.
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
                        {{
                            form.rubric.reduce(
                                (sum, r) => sum + Number(r.max || 0),
                                0,
                            )
                        }}
                        puntos</b
                    >
                </div>
            </section>
            <div class="row end">
                <Link
                    :href="url('/gestion/convocatorias')"
                    class="btn btn-outline"
                    >Cancelar</Link
                ><button class="btn btn-primary" :disabled="form.processing">
                    {{ form.processing ? "Guardando…" : "Guardar borrador"
                    }}<Icon name="Check" :size="18" />
                </button>
            </div></form
    ></AppLayout>
</template>
