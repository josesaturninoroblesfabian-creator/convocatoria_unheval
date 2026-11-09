<script setup>
import { computed } from "vue";
import { additionalAcademicPoints } from "../lib/screening";

const model = defineModel({ required: true });
const pointLabels = computed(() => ({
    titulo: "Puntos por título profesional",
    ...(model.value.academic_credentials
        ? { bachiller: "Puntos por bachiller" }
        : {}),
    maestro: "Puntos por maestro",
    doctor: "Puntos por doctor",
    ...(model.value.academic_credentials && model.value.allow_residency
        ? { segunda_especialidad: "Puntos por segunda especialidad admitida" }
        : {}),
}));
function initializeAcademicPoints() {
    if (model.value.academic_credentials) {
        model.value.degree_points = {
            ...additionalAcademicPoints,
            ...model.value.degree_points,
        };
    }
}
function addField() {
    model.value.fields.push({
        key: "campo_" + (model.value.fields.length + 1),
        label: "",
        type: "text",
        required: true,
        options: [],
        expected_value: "",
        requires_document: true,
    });
}
</script>
<template>
    <div class="screening-editor">
        <label class="check-label"
            ><input type="checkbox" v-model="model.enabled" />Usar formulario
            estructurado con comprobación de requisitos</label
        ><template v-if="model.enabled"
            ><div class="notice">
                Configura lo que indican las bases de esta plaza. Las reglas se
                bloquearán al publicar. Los puntajes son preliminares y
                requieren validación documental.
            </div>
            <label class="check-label">
                <input
                    type="checkbox"
                    v-model="model.academic_credentials"
                    @change="initializeAcademicPoints"
                />
                Registrar grados y títulos por separado
            </label>
            <label v-if="model.academic_credentials" class="check-label">
                <input type="checkbox" v-model="model.allow_residency" />
                Admitir la segunda especialidad obtenida por residentado médico
                u odontológico, según las bases
            </label>
            <div class="form-grid">
                <label
                    >Grado mínimo<select v-model="model.minimum_degree">
                        <option value="titulo">Título profesional</option>
                        <option value="maestro">Maestro / Magíster</option>
                        <option value="doctor">Doctor</option>
                    </select></label
                ><label
                    >Experiencia mínima (meses)<input
                        v-model.number="model.minimum_experience_months"
                        type="number"
                        min="0"
                        max="600"
                        required /></label
                ><label
                    >Experiencia que se computa<select
                        v-model="model.experience_type"
                    >
                        <option value="toda">Profesional y docente</option>
                        <option value="profesional">Solo profesional</option>
                        <option value="docente">Solo docente</option>
                    </select></label
                ><label
                    >Capacitación mínima (horas)<input
                        v-model.number="model.minimum_training_hours"
                        type="number"
                        min="0"
                        max="10000"
                        required
                /></label>
            </div>
            <label class="check-label"
                ><input
                    type="checkbox"
                    v-model="model.count_from_bachelor"
                />Contar únicamente experiencia posterior a la obtención del
                bachiller</label
            >
            <h3 class="form-section">Puntajes preliminares configurables</h3>
            <div class="form-grid">
                <label v-for="(title, key) in pointLabels" :key="key"
                    >{{ title
                    }}<input
                        v-model.number="model.degree_points[key]"
                        type="number"
                        step="0.001"
                        min="0"
                        max="100"
                        required /></label
                ><label
                    >Puntos por año completo<input
                        v-model.number="model.experience_points_per_year"
                        type="number"
                        step="0.001"
                        min="0"
                        max="100"
                        required /></label
                ><label
                    >Tope de puntos por experiencia<input
                        v-model.number="model.experience_cap"
                        type="number"
                        step="0.001"
                        min="0"
                        max="100"
                        required /></label
                ><label
                    >Horas de capacitación por punto<input
                        v-model.number="model.training_hours_per_point"
                        type="number"
                        min="1"
                        max="5000"
                        required /></label
                ><label
                    >Tope de puntos por capacitación<input
                        v-model.number="model.training_cap"
                        type="number"
                        step="0.001"
                        min="0"
                        max="100"
                        required
                /></label>
            </div>
            <p v-if="model.academic_credentials" class="footnote">
                Formación profesional: un título y el mayor puntaje de un grado
                admitido, hasta 10 puntos. Se suman periodos sin superposición y
                horas de los cursos declarados.
            </p>
            <p v-else class="footnote">
                Solo puntúa el grado seleccionado; no se acumulan maestro y
                doctor. Se suman periodos sin superposición y horas de los
                cursos declarados.
            </p>
            <h3 class="form-section">Preguntas adicionales de esta plaza</h3>
            <div
                v-for="(f, i) in model.fields"
                :key="i"
                class="custom-field-editor"
            >
                <div class="form-grid">
                    <label
                        >Clave única<input
                            v-model="f.key"
                            required
                            pattern="[A-Za-z0-9_-]+"
                            maxlength="30" /></label
                    ><label
                        >Pregunta<input
                            v-model="f.label"
                            required
                            maxlength="200"
                            placeholder="Ejemplo: Nivel de quechua, si las bases lo exigen" /></label
                    ><label
                        >Tipo<select v-model="f.type">
                            <option value="text">Texto</option>
                            <option value="select">Selección</option>
                            <option value="number">Número</option>
                            <option value="date">Fecha</option>
                        </select></label
                    ><label v-if="f.type === 'select'"
                        >Opciones separadas por coma<input
                            :value="f.options.join(', ')"
                            @input="
                                f.options = $event.target.value
                                    .split(',')
                                    .map((s) => s.trim())
                                    .filter(Boolean)
                            "
                            required
                            placeholder="Básico, Intermedio, Avanzado" /></label
                    ><label
                        >Respuesta exigida (opcional)<input
                            v-model="f.expected_value"
                            maxlength="100"
                            placeholder="Vacío: solo comprobar que respondió"
                    /></label>
                </div>
                <div class="row wrap">
                    <label class="check-label"
                        ><input
                            v-model="f.required"
                            type="checkbox"
                        />Obligatorio</label
                    ><label class="check-label"
                        ><input
                            v-model="f.requires_document"
                            type="checkbox"
                        />Pedir sustento PDF</label
                    ><button
                        type="button"
                        class="text-link"
                        @click="model.fields.splice(i, 1)"
                    >
                        Quitar pregunta
                    </button>
                </div>
            </div>
            <button
                type="button"
                class="btn btn-outline btn-sm"
                @click="addField"
            >
                Añadir pregunta
            </button></template
        >
    </div>
</template>
