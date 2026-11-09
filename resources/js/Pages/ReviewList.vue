<script setup>
import { ref } from "vue";
import { Link, router } from "@inertiajs/vue3";
import AppLayout from "../Layouts/AppLayout.vue";
import Icon from "../Components/Icon.vue";
import Badge from "../Components/Badge.vue";
import Pagination from "../Components/Pagination.vue";
import { url, date, shortRef } from "../lib/ui";
const props = defineProps({
    applications: Object,
    filters: Object,
    calls: Array,
});
const sort = ref(props.filters.sort || "score");
const q = ref(props.filters.q || "");
const call = ref(props.filters.call ? Number(props.filters.call) : "");
const status = ref(props.filters.status || "");
function filter() {
    router.get(
        url("/expedientes"),
        {
            q: q.value,
            call: call.value,
            status: status.value,
            sort: sort.value,
        },
        { preserveState: true, preserveScroll: true },
    );
}
</script>
<template>
    <AppLayout
        :title="
            $page.props.auth.user.role === 'jurado'
                ? 'Expedientes asignados'
                : 'Expedientes recibidos'
        "
        :subtitle="
            $page.props.auth.user.role === 'jurado'
                ? 'Selecciona una convocatoria para revisar los expedientes de tus plazas.'
                : 'Selecciona una convocatoria para seguir el avance de su comisión.'
        "
        ><form class="search-panel review-filters" @submit.prevent="filter">
            <div class="search-input">
                <Icon name="Search" /><input
                    v-model="q"
                    aria-label="Buscar expediente"
                    placeholder="Buscar por nombre o código de expediente…"
                />
            </div>
            <select v-model="call" aria-label="Convocatoria" @change="filter">
                <option value="">Todas las convocatorias</option>
                <option v-for="item in calls" :key="item.id" :value="item.id">
                    {{ item.code }} · {{ item.title }}
                </option>
            </select>
            <select v-model="status" aria-label="Estado" @change="filter">
                <option value="">Todos los estados</option>
                <option value="RECIBIDA">Recibida</option>
                <option value="APTO">Apto</option>
                <option value="NO_APTO">No apto</option>
                <option value="EVALUADA">Evaluada</option></select
            ><select
                v-model="sort"
                aria-label="Orden de revisión"
                @change="filter"
            >
                <option value="score">Mayor puntaje declarado</option>
                <option value="recent">Recepción más reciente</option></select
            ><button class="btn btn-primary">Buscar</button>
        </form>
        <div v-if="sort === 'score'" class="notice">
            El puntaje declarado ordena los expedientes de prueba libre. La
            comisión verifica los sustentos antes de calificar.
        </div>
        <section class="panel">
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Postulante / expediente</th>
                            <th>Convocatoria y plaza</th>
                            <th>Recepción</th>
                            <th>Puntaje declarado</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="a in applications.data" :key="a.id">
                            <td>
                                <b>{{
                                    a.applicant_snapshot?.name || a.user.name
                                }}</b
                                ><small class="block mono muted">{{
                                    shortRef(a.reference)
                                }}</small>
                            </td>
                            <td>
                                {{ a.vacancy.school
                                }}<small class="block muted">{{
                                    a.recruitment_call.code
                                }}</small
                                ><small class="block muted"
                                    >AIRHSP {{ a.vacancy.airhsp }}</small
                                >
                            </td>
                            <td>{{ date(a.submitted_at, true) }}</td>
                            <td>
                                {{
                                    a.recruitment_call.evaluation_mode ===
                                    "nombramiento_referencia"
                                        ? "Evalúa la comisión"
                                        : a.declared_score === null
                                          ? "Sin formulario"
                                          : Number(a.declared_score).toFixed(3)
                                }}
                            </td>
                            <td><Badge :value="a.status" /></td>
                            <td>
                                <Link
                                    :href="url('/expedientes/' + a.id)"
                                    class="text-link"
                                    >{{
                                        $page.props.auth.user.role ===
                                            "jurado" &&
                                        a.recruitment_call.status ===
                                            "EN_EVALUACION" &&
                                        a.status === "RECIBIDA"
                                            ? "Revisar requisitos"
                                            : "Ver expediente"
                                    }}
                                    <Icon name="ArrowRight" :size="16"
                                /></Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="!applications.total" class="empty-state">
                <Icon name="FolderOpen" :size="38" />
                <h3>No hay expedientes para mostrar</h3>
                <p>
                    {{
                        call || status || q
                            ? "Cambia los filtros para consultar otros expedientes."
                            : $page.props.auth.user.role === "jurado"
                              ? "Aquí aparecerán las postulaciones enviadas a tus plazas asignadas."
                              : "Los borradores solo son visibles para sus postulantes."
                    }}
                </p>
            </div>
            <Pagination :data="applications" /></section
    ></AppLayout>
</template>

<style scoped>
.review-filters {
    flex-wrap: wrap;
}

.review-filters .search-input {
    flex: 1 1 16rem;
}

.review-filters select {
    flex: 1 1 9rem;
    min-width: 0;
    max-width: 100%;
}
</style>
