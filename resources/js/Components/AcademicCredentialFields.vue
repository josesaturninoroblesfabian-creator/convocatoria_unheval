<script setup>
import { computed } from "vue";
import Icon from "./Icon.vue";
import {
    academicCredentialLabels,
    academicCredentialLimit,
    screeningRecordId,
} from "../lib/screening";

const props = defineProps({ dateLimit: String, includeInstitutes: Boolean });
const labels = computed(() =>
    props.includeInstitutes
        ? {
              ...academicCredentialLabels,
              instituto: "Grado o título de instituto",
          }
        : academicCredentialLabels,
);
const credentials = defineModel({ type: Array, required: true });

function addCredential() {
    credentials.value.push({
        id: screeningRecordId(),
        kind: "",
        name: "",
        institution: "",
        awarded_at: "",
    });
}
</script>

<template>
    <div>
        <div
            v-for="(credential, i) in credentials"
            :key="credential.id"
            class="entry-card"
        >
            <div class="row between">
                <b>Credencial {{ i + 1 }}</b>
                <button
                    type="button"
                    class="text-link"
                    @click="credentials.splice(i, 1)"
                >
                    Quitar
                </button>
            </div>
            <div class="form-grid">
                <label>
                    Tipo
                    <select v-model="credential.kind" required>
                        <option value="" disabled>Seleccionar</option>
                        <option
                            v-for="(label, kind) in labels"
                            :key="kind"
                            :value="kind"
                        >
                            {{ label }}
                        </option>
                    </select>
                </label>
                <label>
                    Denominación
                    <input v-model="credential.name" maxlength="250" required />
                </label>
                <label>
                    Institución emisora
                    <input
                        v-model="credential.institution"
                        maxlength="200"
                        required
                    />
                </label>
                <label>
                    Fecha de obtención
                    <input
                        v-model="credential.awarded_at"
                        type="date"
                        :max="dateLimit"
                        required
                    />
                </label>
            </div>
        </div>
        <button
            v-if="credentials.length < academicCredentialLimit"
            type="button"
            class="btn btn-outline btn-sm"
            @click="addCredential"
        >
            <Icon name="Plus" :size="15" />Añadir grado, título o especialidad
        </button>
        <p v-else class="footnote">
            Has llegado al límite de
            {{ academicCredentialLimit }} credenciales.
        </p>
    </div>
</template>
