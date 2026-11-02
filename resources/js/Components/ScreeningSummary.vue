<script setup>
import Icon from "./Icon.vue";
defineProps({ report: Object });
</script>
<template>
    <section v-if="report?.enabled" class="screening-summary">
        <div class="row between wrap">
            <h3>Comprobación automática de lo declarado</h3>
            <span
                class="badge"
                :class="report.eligible ? 'badge-apto' : 'badge-no_apto'"
                >{{
                    report.eligible
                        ? "Mínimos declarados completos"
                        : "Faltan requisitos mínimos"
                }}</span
            >
        </div>
        <p class="footnote">
            Corte: {{ report.cutoff }}. Este cálculo no autentica documentos ni
            adjudica una plaza.
        </p>
        <div
            class="screening-check"
            v-for="(check, i) in report.checks"
            :key="i"
        >
            <Icon
                :name="check.passed ? 'CheckCircle2' : 'CircleAlert'"
                :size="17"
            /><span>{{ check.label }}</span
            ><b>{{ check.passed ? "Cumple" : "Pendiente" }}</b>
        </div>
        <div class="screening-metrics">
            <div>
                <small>Experiencia sin duplicar</small
                ><b>{{ report.experience_days }} días</b
                ><small>{{ report.complete_years }} años completos</small>
            </div>
            <div>
                <small>Capacitación declarada</small
                ><b>{{ report.training_hours }} horas</b>
            </div>
            <div>
                <small>Puntaje preliminar</small
                ><b>{{ Number(report.total).toFixed(3) }}</b
                ><small
                    >Formación {{ report.academic_points }} + experiencia
                    {{ report.experience_points }} + capacitación
                    {{ report.training_points }}</small
                >
            </div>
        </div>
    </section>
</template>
