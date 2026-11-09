<script setup>
import { computed, ref, watch } from "vue";
import { Link, useForm } from "@inertiajs/vue3";
import AppLayout from "../Layouts/AppLayout.vue";
import Errors from "../Components/Errors.vue";
import Icon from "../Components/Icon.vue";
import { date, label, url } from "../lib/ui";
import { screeningRecordId } from "../lib/screening";

const props = defineProps({
    call: Object,
    vacancy: Object,
    contractHours: Object,
    faculties: Array,
});
const contract = computed(() => props.call.modality === "CONTRATO");
const callPath = computed(() => "/gestion/convocatorias/" + props.call.id);
const vacancyPath = computed(() => "/gestion/plazas/" + props.vacancy?.id);
const newCourse = () => ({ code: "", name: "", ht: 0, hp: 0 });
const form = useForm({
    faculty: props.vacancy?.faculty ?? "",
    school: props.vacancy?.school ?? "",
    airhsp: props.vacancy?.airhsp ?? "",
    cap: props.vacancy?.cap ?? "",
    category: props.vacancy?.category ?? (contract.value ? "" : "AUXILIAR"),
    dedication: props.vacancy?.dedication ?? "TIEMPO COMPLETO",
    weekly_hours: props.vacancy?.weekly_hours ?? 40,
    description: props.vacancy?.description ?? "",
    professional_profile: props.vacancy?.professional_profile ?? "",
    courses: props.vacancy?.courses.length
        ? props.vacancy.courses.map((course) => ({ ...course }))
        : [newCourse()],
    schedule: (props.vacancy?.schedule ?? []).map((event) => ({ ...event })),
});
const hours = computed(() => props.contractHours[form.category]);
const rowKeys = {
    courses: form.courses.map(screeningRecordId),
    schedule: form.schedule.map(screeningRecordId),
};
const attachment = useForm({ name: "", file: null });
const removing = useForm({});
const fileInput = ref(null);
const selectedDocument = ref(null);

watch(
    () => form.dedication,
    (value) => {
        form.weekly_hours = value === "TIEMPO PARCIAL" ? 20 : 40;
    },
);

function addRow(section, row) {
    rowKeys[section].push(screeningRecordId());
    form[section].push(row);
}

function removeRow(section, index) {
    rowKeys[section].splice(index, 1);
    form[section].splice(index, 1);
}

function save() {
    if (props.vacancy) {
        form.put(url(vacancyPath.value), { preserveScroll: true });
    } else {
        form.post(url(callPath.value + "/plazas"));
    }
}

function attach() {
    attachment.post(url(vacancyPath.value + "/documentos"), {
        preserveScroll: true,
        onSuccess: () => {
            attachment.reset();
            if (fileInput.value) fileInput.value.value = "";
        },
    });
}

function removeDocument() {
    removing.delete(
        url(vacancyPath.value + "/documentos/" + selectedDocument.value.id),
        {
            preserveScroll: true,
            onSuccess: () => (selectedDocument.value = null),
        },
    );
}
</script>

<template>
    <AppLayout
        :title="vacancy ? 'Editar ficha de plaza' : 'Crear plaza docente'"
        :subtitle="call.code"
    >
        <template #actions>
            <Link :href="url(callPath)" class="btn btn-outline">
                <Icon name="ArrowLeft" :size="16" /> Volver a la convocatoria
            </Link>
            <Link
                v-if="vacancy"
                :href="url(vacancyPath + '/vista-previa')"
                class="btn btn-outline"
            >
                <Icon name="Eye" :size="16" /> Vista del postulante
            </Link>
        </template>

        <div class="notice">
            <strong>{{ label(call.modality) }} · {{ call.title }}</strong>
            <p>
                Completa la ficha que verá el postulante. Los requisitos de
                envío, filtros y evaluación se configuran después en la
                convocatoria.
            </p>
        </div>

        <nav aria-label="Secciones de la ficha" class="row wrap gap-12">
            <a href="#identificacion" class="btn btn-outline btn-sm"
                >1. Identificación</a
            >
            <a href="#carga-academica" class="btn btn-outline btn-sm"
                >2. Carga académica</a
            >
            <a href="#perfil-profesional" class="btn btn-outline btn-sm"
                >3. Perfil profesional</a
            >
            <a href="#cronograma" class="btn btn-outline btn-sm"
                >4. Cronograma</a
            >
            <a href="#documentos" class="btn btn-outline btn-sm"
                >5. Documentos</a
            >
        </nav>

        <form class="editor-form" @submit.prevent="save">
            <Errors :errors="form.errors" />

            <section id="identificacion" class="panel">
                <div class="panel-heading">
                    <Icon name="BriefcaseBusiness" />
                    <h2>1. Identificación de la plaza</h2>
                </div>
                <div class="form-grid">
                    <label
                        >Facultad
                        <input
                            v-model="form.faculty"
                            list="facultades-plaza"
                            required
                            maxlength="180"
                        />
                        <datalist id="facultades-plaza">
                            <option
                                v-for="faculty in faculties"
                                :key="faculty"
                                :value="faculty"
                            />
                        </datalist>
                    </label>
                    <label
                        >Escuela profesional
                        <input v-model="form.school" required maxlength="180" />
                    </label>
                    <label
                        >Código de registro AIRHSP
                        <input
                            v-model="form.airhsp"
                            required
                            maxlength="30"
                            placeholder="000078"
                        />
                    </label>
                    <label
                        >Número CAP
                        <input
                            v-model="form.cap"
                            required
                            maxlength="30"
                            placeholder="318"
                        />
                    </label>
                    <label v-if="contract"
                        >Tipo y clasificación del contrato
                        <select v-model="form.category" required>
                            <option value="" disabled>
                                Selecciona la clasificación
                            </option>
                            <option
                                v-for="(weekly, code) in contractHours"
                                :key="code"
                                :value="code"
                            >
                                {{ code }} · {{ weekly }} h ({{
                                    weekly / 2
                                }}
                                lectivas + {{ weekly / 2 }} no lectivas)
                            </option>
                        </select>
                    </label>
                    <template v-else>
                        <label
                            >Categoría docente
                            <select v-model="form.category" required>
                                <option
                                    v-for="category in [
                                        'AUXILIAR',
                                        'ASOCIADO',
                                        'PRINCIPAL',
                                    ]"
                                    :key="category"
                                    :value="category"
                                >
                                    {{ label(category) }}
                                </option>
                            </select>
                        </label>
                        <label
                            >Dedicación
                            <select v-model="form.dedication" required>
                                <option
                                    v-for="dedication in [
                                        'TIEMPO COMPLETO',
                                        'TIEMPO PARCIAL',
                                        'DEDICACIÓN EXCLUSIVA',
                                    ]"
                                    :key="dedication"
                                    :value="dedication"
                                >
                                    {{ label(dedication) }}
                                </option>
                            </select>
                        </label>
                        <label
                            >Horas semanales
                            <input
                                v-model.number="form.weekly_hours"
                                type="number"
                                min="1"
                                max="40"
                                required
                            />
                        </label>
                    </template>
                </div>
                <p v-if="contract && hours" class="notice">
                    {{ hours }} horas semanales · {{ hours / 2 }} lectivas y
                    {{ hours / 2 }} no lectivas.
                </p>
                <label
                    >Descripción breve <small>Opcional</small>
                    <textarea
                        v-model="form.description"
                        maxlength="4000"
                        rows="3"
                        placeholder="Información complementaria sobre la plaza"
                    />
                </label>
            </section>

            <section id="carga-academica" class="panel">
                <div class="panel-heading">
                    <Icon name="BookOpen" />
                    <h2>2. Carga académica</h2>
                </div>
                <p class="muted">
                    Registra los cursos, módulos o proyectos del cuadro
                    aprobado. HT son horas teóricas y HP son horas prácticas.
                </p>
                <div
                    v-for="(course, index) in form.courses"
                    :key="rowKeys.courses[index]"
                    class="entry-card"
                >
                    <div class="row between wrap">
                        <strong
                            >Curso, módulo o proyecto {{ index + 1 }}</strong
                        >
                        <button
                            v-if="form.courses.length > 1"
                            type="button"
                            class="text-link"
                            @click="removeRow('courses', index)"
                        >
                            Quitar
                        </button>
                    </div>
                    <div class="form-grid">
                        <label
                            >Código
                            <input
                                v-model="course.code"
                                maxlength="30"
                                required
                        /></label>
                        <label
                            >Denominación
                            <input
                                v-model="course.name"
                                maxlength="180"
                                required
                        /></label>
                        <label
                            >Horas teóricas
                            <input
                                v-model.number="course.ht"
                                type="number"
                                min="0"
                                max="40"
                                required
                        /></label>
                        <label
                            >Horas prácticas
                            <input
                                v-model.number="course.hp"
                                type="number"
                                min="0"
                                max="40"
                                required
                        /></label>
                    </div>
                    <small
                        >Total del curso ·
                        {{ Number(course.ht) + Number(course.hp) }} h</small
                    >
                </div>
                <button
                    v-if="form.courses.length < 30"
                    type="button"
                    class="btn btn-outline btn-sm"
                    @click="addRow('courses', newCourse())"
                >
                    <Icon name="Plus" :size="16" /> Añadir curso
                </button>
            </section>

            <section id="perfil-profesional" class="panel">
                <div class="panel-heading">
                    <Icon name="GraduationCap" />
                    <h2>3. Perfil profesional</h2>
                </div>
                <p class="muted">
                    Escribe el perfil que figura en el cuadro aprobado, por
                    ejemplo la especialidad, el grado, la experiencia y la
                    habilitación cuando corresponda.
                </p>
                <label
                    >Perfil publicado
                    <textarea
                        v-model="form.professional_profile"
                        required
                        maxlength="6000"
                        rows="7"
                        placeholder="Escribe cada condición en una línea."
                    />
                </label>
            </section>

            <section id="cronograma" class="panel">
                <div class="panel-heading">
                    <Icon name="CalendarDays" />
                    <h2>4. Cronograma de la plaza</h2>
                </div>
                <p class="notice">
                    <strong>Inscripción digital de la convocatoria</strong
                    ><br />
                    Del {{ date(call.opens_at, true) }} al
                    {{ date(call.closes_at, true) }}. Hora de Perú.
                </p>
                <p class="muted">
                    Puedes añadir actividades propias de esta plaza, como una
                    clase modelo o la publicación de resultados. Si la plaza no
                    tiene actividades propias, se verá el cronograma de la
                    convocatoria.
                </p>
                <div
                    v-for="(event, index) in form.schedule"
                    :key="rowKeys.schedule[index]"
                    class="entry-card"
                >
                    <div class="row between wrap">
                        <strong>Actividad {{ index + 1 }}</strong>
                        <button
                            type="button"
                            class="text-link"
                            @click="removeRow('schedule', index)"
                        >
                            Quitar
                        </button>
                    </div>
                    <label
                        >Actividad
                        <input
                            v-model="event.label"
                            maxlength="150"
                            required
                            placeholder="Ej. Clase modelo"
                    /></label>
                    <div class="form-grid">
                        <label
                            >Fecha y hora de inicio
                            <input
                                v-model="event.starts_at"
                                type="datetime-local"
                                required
                        /></label>
                        <label
                            >Fecha y hora de término <small>Opcional</small
                            ><input
                                v-model="event.ends_at"
                                type="datetime-local"
                                :min="event.starts_at || undefined"
                        /></label>
                    </div>
                    <label
                        >Indicaciones <small>Opcional</small
                        ><input
                            v-model="event.detail"
                            maxlength="300"
                            placeholder="Lugar, enlace o instrucciones"
                    /></label>
                </div>
                <button
                    v-if="form.schedule.length < 20"
                    type="button"
                    class="btn btn-outline btn-sm"
                    @click="
                        addRow('schedule', {
                            label: '',
                            starts_at: '',
                            ends_at: '',
                            detail: '',
                        })
                    "
                >
                    <Icon name="Plus" :size="16" /> Añadir actividad
                </button>
            </section>

            <div class="row end">
                <button class="btn btn-primary" :disabled="form.processing">
                    <Icon name="Save" :size="17" />
                    {{
                        vacancy
                            ? "Guardar cambios de la ficha"
                            : "Guardar ficha de plaza"
                    }}
                </button>
            </div>
        </form>

        <section id="documentos" class="panel">
            <div class="panel-heading">
                <Icon name="FileText" />
                <h2>5. Bases y otros documentos</h2>
            </div>
            <p v-if="!vacancy" class="muted">
                Guarda la ficha para adjuntar aquí las bases, anexos y
                comunicados en PDF.
            </p>
            <template v-else>
                <p class="muted">
                    Cada documento se adjunta a esta plaza. Se podrá abrir desde
                    el portal cuando se publique la convocatoria. Máximo diez
                    PDF, de hasta 5 MB cada uno.
                </p>
                <div
                    v-for="document in vacancy.public_documents"
                    :key="document.id"
                    class="schedule-row"
                >
                    <a
                        :href="url(document.path)"
                        target="_blank"
                        rel="noopener"
                        class="text-link"
                        >{{ document.name }}</a
                    >
                    <button
                        type="button"
                        class="btn btn-outline btn-sm"
                        @click="selectedDocument = document"
                    >
                        Retirar
                    </button>
                </div>
                <form
                    v-if="vacancy.public_documents.length < 10"
                    @submit.prevent="attach"
                >
                    <Errors :errors="attachment.errors" />
                    <div class="form-grid">
                        <label
                            >Nombre visible
                            <input
                                v-model="attachment.name"
                                required
                                maxlength="150"
                                placeholder="Ej. Bases de la plaza"
                        /></label>
                        <label
                            >Archivo PDF
                            <input
                                ref="fileInput"
                                type="file"
                                accept="application/pdf,.pdf"
                                required
                                @change="
                                    attachment.file =
                                        $event.target.files[0] ?? null
                                "
                        /></label>
                    </div>
                    <button
                        class="btn btn-outline"
                        :disabled="attachment.processing || !attachment.file"
                    >
                        <Icon name="Upload" :size="17" />
                        {{
                            attachment.processing
                                ? "Adjuntando…"
                                : "Adjuntar PDF"
                        }}
                    </button>
                </form>
            </template>
        </section>

        <div
            v-if="selectedDocument"
            class="modal-backdrop"
            @click.self="selectedDocument = null"
        >
            <section
                class="modal"
                role="dialog"
                aria-modal="true"
                aria-labelledby="retirar-pdf"
            >
                <h2 id="retirar-pdf">Retirar PDF de la plaza</h2>
                <p>{{ selectedDocument.name }}</p>
                <div class="row end">
                    <button
                        type="button"
                        class="btn btn-outline"
                        :disabled="removing.processing"
                        @click="selectedDocument = null"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        class="btn btn-primary"
                        :disabled="removing.processing"
                        @click="removeDocument"
                    >
                        Retirar documento
                    </button>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
