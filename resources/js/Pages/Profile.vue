<script setup>
import { computed, ref, watch } from "vue";
import { useForm, useHttp, usePage } from "@inertiajs/vue3";
import AppLayout from "../Layouts/AppLayout.vue";
import AcademicCredentialFields from "../Components/AcademicCredentialFields.vue";
import Icon from "../Components/Icon.vue";
import Errors from "../Components/Errors.vue";
import { academicCredentialLimit, screeningRecordId } from "../lib/screening";
import { url } from "../lib/ui";
const props = defineProps({
    academicCredentials: Array,
    credentialDateLimit: String,
    registrySources: { type: Array, default: () => [] },
});
const page = usePage();
const user = page.props.auth.user;
const form = useForm({
    name: user.name,
    document_number: user.document_number || "",
    phone: user.phone || "",
});
const academicForm = useForm({
    credentials: props.academicCredentials ?? [],
});
const savedDni = computed(() => page.props.auth.user.document_number);
const canLookUp = computed(
    () =>
        /^[0-9]{8}$/.test(savedDni.value ?? "") &&
        form.document_number === savedDni.value,
);
const lookup = useHttp({ source: "", consent: false });
const result = ref(null);
const lookupError = ref("");
const syncMessage = ref("");
const sourceLabels = {
    sunedu: "Grados universitarios · SUNEDU",
    minedu: "Títulos de institutos · MINEDU",
};
watch([savedDni, () => form.document_number], () => {
    result.value = null;
    syncMessage.value = "";
    lookup.consent = false;
});
async function consultRegistry() {
    if (
        !canLookUp.value ||
        lookup.processing ||
        academicForm.processing ||
        !lookup.consent
    )
        return;
    const requestedDni = savedDni.value;
    const source = lookup.source;
    result.value = null;
    syncMessage.value = "";
    lookupError.value = "";
    lookup.clearErrors();
    try {
        const data = await lookup.post(url("/perfil/consultar-registro"));
        if (
            canLookUp.value &&
            savedDni.value === requestedDni &&
            lookup.source === source &&
            lookup.consent
        ) {
            result.value = data;
            if (data.status === "found")
                appendAcademicRecords(data.records ?? []);
        }
    } catch (error) {
        if (!lookup.hasErrors) {
            lookupError.value =
                error?.response?.status === 429
                    ? "Alcanzaste el límite temporal de consultas. Intenta en un minuto."
                    : "La consulta no se pudo completar. Puedes seguir editando tu perfil.";
        }
    }
}

function credentialKey({ kind, name, institution, awarded_at }) {
    return JSON.stringify(
        [kind, name, institution, awarded_at].map((value) =>
            (value ?? "")
                .trim()
                .replace(/\s+/g, " ")
                .toLocaleLowerCase("es-PE"),
        ),
    );
}

function appendAcademicRecords(records) {
    const existing = new Set(academicForm.credentials.map(credentialKey));
    let added = 0;
    for (const record of records) {
        if (academicForm.credentials.length >= academicCredentialLimit) break;
        if (
            !record.kind ||
            !record.title ||
            !record.institution ||
            !record.awarded_at
        )
            continue;
        const credential = {
            kind: record.kind,
            name: record.title,
            institution: record.institution,
            awarded_at: record.awarded_at,
        };
        const key = credentialKey(credential);
        if (existing.has(key)) continue;
        existing.add(key);
        academicForm.credentials.push({
            id: screeningRecordId(),
            ...credential,
        });
        added++;
    }
    syncMessage.value = added
        ? `Se añadieron ${added} registros a Mi formación. Revísalos y pulsa Guardar formación.`
        : "Sin registros nuevos para añadir. Los resultados ya guardados, incompletos o sin un tipo reconocido quedan disponibles para consulta.";
}
</script>
<template>
    <AppLayout
        title="Mi perfil"
        :subtitle="
            academicCredentials
                ? 'Actualiza tus datos y guarda tu formación para futuras postulaciones.'
                : 'Mantén tus datos de contacto actualizados.'
        "
        ><form
            class="panel narrow-panel"
            @submit.prevent="form.put(url('/perfil'))"
        >
            <div class="panel-heading">
                <Icon name="Users" />
                <h2>Datos personales</h2>
            </div>
            <Errors :errors="form.errors" /><label
                >Nombres y apellidos<input
                    v-model="form.name"
                    required
                    maxlength="150"
                    autocomplete="name"
            /></label>
            <div class="form-grid">
                <label
                    >DNI<input
                        v-model="form.document_number"
                        required
                        inputmode="numeric"
                        maxlength="8"
                        pattern="[0-9]{8}" /></label
                ><label
                    >Teléfono<input
                        v-model="form.phone"
                        required
                        type="tel"
                        autocomplete="tel"
                /></label>
            </div>
            <label
                >Correo de acceso<input :value="user.email" disabled
            /></label>
            <p class="muted">
                Los cambios se utilizarán en tus próximas postulaciones. La
                información incluida en los expedientes ya enviados permanece
                conservada.
            </p>
            <button class="btn btn-primary" :disabled="form.processing">
                {{ form.processing ? "Guardando…" : "Guardar mis datos" }}
            </button>
        </form>
        <form
            v-if="registrySources.length"
            class="panel narrow-panel"
            @submit.prevent="consultRegistry"
        >
            <div class="panel-heading">
                <Icon name="Search" />
                <h2>Sincronizar formación</h2>
            </div>
            <p class="muted">
                Consulta SUNEDU o MINEDU a través de ApisTED con el DNI
                guardado. Los registros compatibles se añaden a Mi formación
                para que los revises y guardes.
            </p>
            <p v-if="!canLookUp" class="notice">
                Guarda un DNI de ocho dígitos en Datos personales antes de
                consultar.
            </p>
            <fieldset
                :disabled="
                    !canLookUp || lookup.processing || academicForm.processing
                "
            >
                <label>
                    Registro
                    <select
                        v-model="lookup.source"
                        required
                        @change="
                            result = null;
                            syncMessage = '';
                        "
                    >
                        <option value="" disabled>
                            Selecciona un registro
                        </option>
                        <option
                            v-for="source in registrySources"
                            :key="source"
                            :value="source"
                        >
                            {{ sourceLabels[source] }}
                        </option>
                    </select>
                </label>
                <label class="check-label">
                    <input v-model="lookup.consent" type="checkbox" required />
                    Autorizo el envío de mi DNI al proveedor de la consulta
                    seleccionada.
                </label>
            </fieldset>
            <Errors :errors="lookup.errors" />
            <p v-if="lookupError" class="notice notice-error" role="alert">
                {{ lookupError }}
            </p>
            <button
                class="btn btn-outline"
                :disabled="
                    !canLookUp ||
                    !lookup.source ||
                    !lookup.consent ||
                    lookup.processing ||
                    academicForm.processing
                "
            >
                {{
                    lookup.processing
                        ? "Consultando…"
                        : "Sincronizar datos académicos"
                }}
            </button>
            <div v-if="result" class="notice" aria-live="polite">
                <p v-if="result.status === 'unavailable'">
                    {{ result.message }}
                </p>
                <p v-else-if="result.status === 'empty'">
                    La consulta no encontró registros. Puedes registrar tu
                    formación con sus sustentos.
                </p>
                <ul v-else-if="result.records?.length">
                    <li v-for="(record, index) in result.records" :key="index">
                        <strong>{{ record.title }}</strong>
                        <small class="block">{{
                            [record.institution, record.date]
                                .filter(Boolean)
                                .join(" · ")
                        }}</small>
                    </li>
                </ul>
                <p v-if="syncMessage">
                    {{ syncMessage }} <a href="#formacion">Ir a Mi formación</a>
                </p>
            </div>
        </form>
        <form
            v-if="academicCredentials"
            id="formacion"
            class="panel narrow-panel"
            @submit.prevent="
                academicForm.put(url('/perfil/formacion'), {
                    preserveScroll: true,
                })
            "
        >
            <div class="panel-heading">
                <Icon name="GraduationCap" />
                <h2>Mi formación</h2>
            </div>
            <p class="muted">
                Registra aquí tus grados, títulos y especialidades. En una
                postulación podrás elegir cuáles copiar y adjuntar los
                documentos que exige esa plaza.
            </p>
            <Errors :errors="academicForm.errors" />
            <p v-if="!academicForm.credentials.length" class="muted">
                Aún no has registrado credenciales académicas.
            </p>
            <fieldset :disabled="academicForm.processing">
                <AcademicCredentialFields
                    v-model="academicForm.credentials"
                    :date-limit="credentialDateLimit"
                    include-institutes
                />
            </fieldset>
            <p class="footnote">
                Tus datos de formación se guardan en tu perfil. Cada expediente
                conserva su propia copia. Los títulos de instituto quedan en el
                perfil para consulta.
            </p>
            <button
                class="btn btn-primary"
                :disabled="
                    academicForm.processing ||
                    lookup.processing ||
                    !academicForm.isDirty
                "
            >
                {{
                    academicForm.processing ? "Guardando…" : "Guardar formación"
                }}
            </button>
        </form>
    </AppLayout>
</template>
