<script setup>
import { computed } from "vue";
import Icon from "./Icon.vue";

const props = defineProps({ call: Object });

const steps = [
    {
        key: "convocatoria",
        label: "Convocatoria",
        hint: "Publicación y difusión",
        icon: "Megaphone",
    },
    {
        key: "inscripcion",
        label: "Inscripción",
        hint: "Presentación de expedientes",
        icon: "FileText",
    },
    {
        key: "verificacion",
        label: "Verificación",
        hint: "Requisitos y documentos",
        icon: "ClipboardCheck",
    },
    {
        key: "evaluacion",
        label: "Evaluación",
        hint: "Aspectos externos e internos",
        icon: "GraduationCap",
    },
    {
        key: "resultados",
        label: "Resultados",
        hint: "Publicación y recursos",
        icon: "ListChecks",
    },
    {
        key: "adjudicacion",
        label: "Adjudicación",
        hint: "Informe final y resolución",
        icon: "Award",
    },
];

const activeIndex = computed(() => {
    switch (props.call.status) {
        case "BORRADOR":
            return 0;
        case "PUBLICADA":
            return 1;
        case "EN_EVALUACION":
            return 3;
        case "FINALIZADA":
        case "ARCHIVADA":
            return 5;
        default:
            return 0;
    }
});

const currentLabel = computed(() => steps[activeIndex.value].label);
</script>
<template>
    <div class="process-stepper">
        <div class="process-stepper-heading">
            <Icon name="ShieldCheck" :size="17" />
            <span
                >Proceso del concurso · Etapa actual:
                <strong>{{ currentLabel }}</strong></span
            >
        </div>
        <ol class="process-stepper-track" :aria-label="'Proceso del concurso'">
            <li
                v-for="(step, index) in steps"
                :key="step.key"
                class="process-step"
                :class="{
                    'is-done': index < activeIndex,
                    'is-current': index === activeIndex,
                }"
            >
                <span class="process-step-dot">
                    <Icon v-if="index < activeIndex" name="Check" :size="15" />
                    <Icon v-else :name="step.icon" :size="16" />
                </span>
                <span class="process-step-text">
                    <b>{{ step.label }}</b>
                    <small>{{ step.hint }}</small>
                </span>
            </li>
        </ol>
    </div>
</template>
