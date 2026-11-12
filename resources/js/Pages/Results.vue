<script setup>
import PublicLayout from "../Layouts/PublicLayout.vue";
import Icon from "../Components/Icon.vue";
import Badge from "../Components/Badge.vue";
import { date } from "../lib/ui";
defineProps({ calls: Array });
const referenceMode = "nombramiento_referencia";
const outcomeLabels = {
    NO_SUPERA_EXTERNA: "No supera el mínimo externo",
    NO_SUPERA_INTERNA: "No supera el mínimo interno",
    NO_SUPERA_TOTAL: "No supera el mínimo total",
    SUPERA_MINIMOS: "Supera los mínimos",
};
function usesReferenceRubric(call, vacancy) {
    return (vacancy.evaluation_mode ?? call.evaluation_mode) === referenceMode;
}
function scoreText(score) {
    return score == null ? "—" : Number(score).toFixed(3);
}
function tied(rows, row) {
    return (
        row.score !== null &&
        rows.filter((r) => r.score === row.score).length > 1
    );
}
</script>
<template>
    <PublicLayout title="Resultados"
        ><div class="detail-heading">
            <div class="container">
                <span class="eyebrow">TRANSPARENCIA DEL PROCESO</span>
                <h1>Resultados de convocatorias</h1>
                <p>
                    Consulta las evaluaciones consolidadas que han sido
                    publicadas.
                </p>
            </div>
        </div>
        <div class="container content-section">
            <div v-if="!calls.length" class="panel empty-state">
                <Icon name="ClipboardCheck" :size="45" />
                <h3>Aún no hay resultados publicados en este sistema</h3>
                <p>
                    Los cuadros estarán disponibles cuando el gestor finalice la
                    evaluación de una convocatoria.
                </p>
                <a
                    class="text-link"
                    href="https://unheval.edu.pe/portal/"
                    target="_blank"
                    rel="noopener"
                    >Consultar publicaciones institucionales
                    <Icon name="ArrowUpRight" :size="17"
                /></a>
            </div>
            <section v-for="c in calls" :key="c.id" class="panel">
                <div class="row between wrap">
                    <h2>{{ c.title }}</h2>
                    <span class="muted"
                        >Publicado: {{ date(c.published_at, true) }}</span
                    >
                </div>
                <div v-if="c.is_demo" class="notice">
                    Resultados de demostración, sin validez oficial.
                </div>
                <div
                    v-for="v in c.vacancies"
                    :key="v.airhsp"
                    class="form-section"
                >
                    <h3>{{ v.school }} · AIRHSP {{ v.airhsp }}</h3>
                    <p v-if="usesReferenceRubric(c, v)" class="muted">
                        Los mínimos se verifican por bloque y en el total. La
                        tabla muestra el resultado de la evaluación de
                        referencia.
                    </p>
                    <p v-else-if="c.is_demo" class="muted">
                        El puntaje corresponde al promedio de las calificaciones
                        de los jurados.
                    </p>
                    <div class="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Código de expediente</th>
                                    <th>Estado</th>
                                    <template v-if="v.by_vacancy">
                                        <th>Externa (55)</th>
                                        <th>Interna (45)</th>
                                        <th>Puntaje final aprobatorio</th>
                                        <th>Resultado</th>
                                    </template>
                                    <template
                                        v-else-if="usesReferenceRubric(c, v)"
                                    >
                                        <th>Externa (55)</th>
                                        <th>Interna (45)</th>
                                        <th>Total (100)</th>
                                        <th>Resultado</th>
                                    </template>
                                    <template v-else>
                                        <th>Puntaje promedio</th>
                                        <th>Observación</th>
                                    </template>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="r in v.rows" :key="r.reference">
                                    <td class="mono">{{ r.reference }}</td>
                                    <td><Badge :value="r.status" /></td>
                                    <template v-if="v.by_vacancy">
                                        <td>
                                            {{ scoreText(r.outcome?.external) }}
                                        </td>
                                        <td>
                                            {{ scoreText(r.outcome?.internal) }}
                                        </td>
                                        <td>{{ scoreText(r.score) }}</td>
                                        <td>
                                            <div class="row wrap gap-8">
                                                <span
                                                    v-if="r.winner"
                                                    class="badge badge-apto"
                                                    >Ganador</span
                                                >
                                                <span>{{
                                                    outcomeLabels[
                                                        r.outcome?.stage
                                                    ] || "—"
                                                }}</span>
                                            </div>
                                            <small
                                                v-if="
                                                    r.tie_breaker === 'doctor'
                                                "
                                                >Desempate dirimido por Grado de
                                                Doctor</small
                                            >
                                            <small
                                                v-else-if="
                                                    r.tie_breaker ===
                                                        'pending' ||
                                                    (!r.tie_breaker &&
                                                        tied(v.rows, r))
                                                "
                                                >Empate pendiente de resolución
                                                por la comisión</small
                                            >
                                        </td>
                                    </template>
                                    <template
                                        v-else-if="usesReferenceRubric(c, v)"
                                    >
                                        <td>
                                            {{ scoreText(r.outcome?.external) }}
                                        </td>
                                        <td>
                                            {{ scoreText(r.outcome?.internal) }}
                                        </td>
                                        <td>
                                            {{ scoreText(r.outcome?.total) }}
                                        </td>
                                        <td>
                                            {{
                                                outcomeLabels[
                                                    r.outcome?.stage
                                                ] ?? "Sin calificación"
                                            }}
                                        </td>
                                    </template>
                                    <template v-else>
                                        <td>
                                            {{
                                                r.score === null
                                                    ? "No corresponde"
                                                    : Number(r.score).toFixed(3)
                                            }}
                                        </td>
                                        <td>
                                            {{
                                                tied(v.rows, r)
                                                    ? "Empate: requiere resolución de la comisión"
                                                    : "—"
                                            }}
                                        </td>
                                    </template>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-if="!v.rows.length" class="muted">
                        Sin expedientes recibidos.
                    </p>
                </div>
            </section>
        </div></PublicLayout
    >
</template>
