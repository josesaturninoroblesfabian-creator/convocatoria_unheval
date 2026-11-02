<script setup>
import { computed } from "vue";
import PublicLayout from "../Layouts/PublicLayout.vue";
import Icon from "../Components/Icon.vue";
import Badge from "../Components/Badge.vue";
import { date } from "../lib/ui";
const props = defineProps({ calls: Array });
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
                    Resultados de demostración, sin validez oficial. El puntaje
                    es el promedio de las calificaciones de los jurados. No
                    constituye adjudicación ni aplica los umbrales,
                    bonificaciones o desempates de la normativa.
                </div>
                <div
                    v-for="v in c.vacancies"
                    :key="v.airhsp"
                    class="form-section"
                >
                    <h3>{{ v.school }} · AIRHSP {{ v.airhsp }}</h3>
                    <div class="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Código de expediente</th>
                                    <th>Estado</th>
                                    <th>Puntaje promedio</th>
                                    <th>Observación</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="r in v.rows" :key="r.reference">
                                    <td class="mono">{{ r.reference }}</td>
                                    <td><Badge :value="r.status" /></td>
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
