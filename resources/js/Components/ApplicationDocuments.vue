<script setup>
import Icon from "./Icon.vue";
import { url } from "../lib/ui";

defineProps({
    requirements: { type: Array, required: true },
    documents: { type: Object, required: true },
    editable: Boolean,
    uploading: String,
    showVerdicts: Boolean,
    requestPath: String,
});
const emit = defineEmits(["select-file"]);

function selectFile(event, key) {
    const file = event.target.files[0];
    if (file) emit("select-file", key, file);
    event.target.value = "";
}
</script>

<template>
    <ul>
        <li
            v-for="requirement in requirements"
            :key="requirement.key"
            class="upload-row flex flex-wrap items-center justify-between gap-3"
        >
            <div class="row">
                <span
                    class="document-state"
                    :class="{ done: documents[requirement.key] }"
                >
                    <Icon
                        :name="
                            documents[requirement.key] ? 'Check' : 'FileText'
                        "
                        :size="17"
                    />
                </span>
                <div class="grow min-w-0">
                    <b>{{ requirement.label }}</b>
                    <small
                        >{{
                            requirement.required ? "Obligatorio" : "Opcional"
                        }}
                        ·
                        {{
                            documents[requirement.key]
                                ? "Adjuntado"
                                : "Sin adjuntar"
                        }}</small
                    >
                    <a
                        v-if="documents[requirement.key]"
                        :href="
                            url(`/documentos/${documents[requirement.key].id}`)
                        "
                        target="_blank"
                        rel="noopener"
                        class="file-link break-all"
                    >
                        {{ documents[requirement.key].name }}
                        <Icon name="ExternalLink" :size="13" />
                    </a>
                    <a
                        v-if="
                            editable &&
                            requestPath &&
                            requirement.key === 'solicitud_inscripcion'
                        "
                        :href="url(requestPath)"
                        target="_blank"
                        rel="noopener"
                        class="text-link mt-1"
                    >
                        Abrir solicitud prellenada
                        <Icon name="ExternalLink" :size="13" />
                    </a>
                    <template v-if="showVerdicts">
                        <small
                            v-if="
                                documents[requirement.key]?.review_status ===
                                'CUMPLE'
                            "
                            >Cumple</small
                        >
                        <div
                            v-else-if="
                                documents[requirement.key]?.review_status ===
                                'NO_CUMPLE'
                            "
                            class="mt-1 text-sm text-red-800"
                        >
                            <strong>No cumple.</strong>
                            {{ documents[requirement.key].review_notes }}
                        </div>
                    </template>
                </div>
            </div>
            <label
                v-if="editable"
                class="btn btn-outline btn-sm file-upload"
                :class="{ disabled: uploading }"
            >
                <Icon name="Upload" :size="15" />
                {{
                    uploading === requirement.key
                        ? "Subiendo…"
                        : documents[requirement.key]
                          ? "Reemplazar PDF"
                          : "Adjuntar PDF"
                }}
                <input
                    type="file"
                    accept="application/pdf,.pdf"
                    :aria-label="`Adjuntar PDF para ${requirement.label}`"
                    :disabled="!!uploading"
                    @change="selectFile($event, requirement.key)"
                />
            </label>
        </li>
    </ul>
</template>
