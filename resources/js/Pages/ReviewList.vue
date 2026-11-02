<script setup>
import { ref } from "vue";
import { Link, router } from "@inertiajs/vue3";
import AppLayout from "../Layouts/AppLayout.vue";
import Icon from "../Components/Icon.vue";
import Badge from "../Components/Badge.vue";
import Pagination from "../Components/Pagination.vue";
import { url, date, shortRef } from "../lib/ui";
const props = defineProps({ applications: Object, filters: Object });
const sort = ref(props.filters.sort || "score");
const q = ref(props.filters.q || "");
const status = ref(props.filters.status || "");
function filter() {
    router.get(
        url("/expedientes"),
        { q: q.value, status: status.value, sort: sort.value },
        { preserveState: true, preserveScroll: true },
    );
}
</script>
<template>
    <AppLayout
        title="Expedientes"
        subtitle="Consulta las postulaciones recibidas y realiza su seguimiento."
        ><form class="search-panel" @submit.prevent="filter">
            <div class="search-input">
                <Icon name="Search" /><input
                    v-model="q"
                    aria-label="Buscar expediente"
                    placeholder="Buscar por nombre o código de expediente…"
                />
            </div>
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
        <div class="notice">
            El puntaje declarado sirve para priorizar la revisión. Todos los
            expedientes enviados deben tener una decisión motivada; el orden no
            declara ganadores.
        </div>
        <section class="panel">
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Postulante / expediente</th>
                            <th>Plaza</th>
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
                                }}<small class="block muted"
                                    >AIRHSP {{ a.vacancy.airhsp }}</small
                                >
                            </td>
                            <td>{{ date(a.submitted_at, true) }}</td>
                            <td>
                                {{
                                    a.declared_score === null
                                        ? "Sin formulario"
                                        : Number(a.declared_score).toFixed(3)
                                }}
                            </td>
                            <td><Badge :value="a.status" /></td>
                            <td>
                                <Link
                                    :href="url('/expedientes/' + a.id)"
                                    class="text-link"
                                    >Revisar <Icon name="ArrowRight" :size="16"
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
                        $page.props.auth.user.role === "jurado"
                            ? "Aquí aparecerán las postulaciones enviadas a tus plazas asignadas."
                            : "Los borradores solo son visibles para sus postulantes."
                    }}
                </p>
            </div>
            <Pagination :data="applications" /></section
    ></AppLayout>
</template>
