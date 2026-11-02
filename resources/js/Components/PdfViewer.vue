<script setup>
import { ref, onMounted, onBeforeUnmount, watch, nextTick } from "vue";
import * as pdfjs from "pdfjs-dist";
import workerUrl from "pdfjs-dist/build/pdf.worker.min.mjs?url";
import Icon from "./Icon.vue";

pdfjs.GlobalWorkerOptions.workerSrc = workerUrl;
const props = defineProps({ src: { type: String, required: true } });
const canvas = ref(null);
const container = ref(null);
const currentPage = ref(1);
const totalPages = ref(0);
const loading = ref(true);
const rendering = ref(false);
const error = ref("");
let loadingTask;
let pdf;
let renderTask;
let generation = 0;

async function renderPage() {
    if (!pdf || !canvas.value) return;
    rendering.value = true;
    const token = generation;
    try {
        if (renderTask) {
            renderTask.cancel();
            await renderTask.promise.catch(() => {});
        }
        const page = await pdf.getPage(currentPage.value);
        if (token !== generation || !canvas.value) return;
        const natural = page.getViewport({ scale: 1 });
        const width = Math.max(240, (container.value?.clientWidth || 450) - 28);
        const scale = width / natural.width;
        const ratio = Math.min(window.devicePixelRatio || 1, 2);
        const viewport = page.getViewport({ scale });
        canvas.value.width = Math.floor(viewport.width * ratio);
        canvas.value.height = Math.floor(viewport.height * ratio);
        canvas.value.style.width = `${viewport.width}px`;
        canvas.value.style.height = `${viewport.height}px`;
        renderTask = page.render({
            canvasContext: canvas.value.getContext("2d"),
            viewport,
            transform: [ratio, 0, 0, ratio, 0, 0],
        });
        await renderTask.promise;
    } catch (exception) {
        if (
            token === generation &&
            exception.name !== "RenderingCancelledException"
        )
            error.value =
                "No se pudo mostrar esta página. Abre el documento en otra pestaña.";
    } finally {
        if (token === generation) rendering.value = false;
    }
}
async function loadDocument() {
    const token = ++generation;
    loading.value = true;
    error.value = "";
    currentPage.value = 1;
    totalPages.value = 0;
    renderTask?.cancel();
    if (loadingTask) await loadingTask.destroy().catch(() => {});
    pdf = null;
    try {
        loadingTask = pdfjs.getDocument({
            url: props.src,
            withCredentials: true,
            isEvalSupported: false,
        });
        const loaded = await loadingTask.promise;
        if (token !== generation) return;
        pdf = loaded;
        totalPages.value = loaded.numPages;
        loading.value = false;
        await nextTick();
        await renderPage();
    } catch (exception) {
        if (token === generation) {
            error.value =
                "No se pudo cargar el PDF. Puedes abrirlo en otra pestaña.";
            loading.value = false;
        }
    }
}
async function movePage(direction) {
    currentPage.value += direction;
    await renderPage();
}
onMounted(loadDocument);
watch(() => props.src, loadDocument);
onBeforeUnmount(() => {
    generation++;
    renderTask?.cancel();
    loadingTask?.destroy().catch(() => {});
});
</script>
<template>
    <div class="pdf-reader">
        <div class="pdf-pagination">
            <button
                class="icon-btn"
                aria-label="Página anterior del PDF"
                :disabled="loading || rendering || currentPage <= 1"
                @click="movePage(-1)"
            >
                <Icon name="ArrowLeft" :size="16" /></button
            ><span
                >Página {{ totalPages ? currentPage : "—" }} de
                {{ totalPages || "—" }}</span
            ><button
                class="icon-btn"
                aria-label="Página siguiente del PDF"
                :disabled="loading || rendering || currentPage >= totalPages"
                @click="movePage(1)"
            >
                <Icon name="ArrowRight" :size="16" /></button
            ><a :href="src" target="_blank" rel="noopener" class="text-link"
                >Abrir original <Icon name="ExternalLink" :size="13"
            /></a>
        </div>
        <div
            ref="container"
            class="pdf-canvas-container"
            :aria-busy="loading || rendering"
        >
            <div v-if="loading" class="pdf-message" role="status">
                Cargando documento privado…
            </div>
            <div v-if="error" class="notice notice-error" role="alert">
                {{ error }}
            </div>
            <canvas
                v-show="!loading && !error"
                ref="canvas"
                :aria-label="
                    'Página ' + currentPage + ' del documento de sustento'
                "
                role="img"
            ></canvas>
        </div>
    </div>
</template>
