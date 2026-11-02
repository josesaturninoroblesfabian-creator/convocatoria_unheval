<script setup>
import { Link } from "@inertiajs/vue3";
import AppLayout from "../Layouts/AppLayout.vue";
import Badge from "../Components/Badge.vue";
import Icon from "../Components/Icon.vue";
import { url, date } from "../lib/ui";
defineProps({ calls: Array });
</script>
<template>
    <AppLayout
        title="Convocatorias"
        subtitle="Organiza las plazas, el cronograma y las etapas de cada concurso."
        ><template #actions
            ><Link
                :href="url('/gestion/convocatorias/nueva')"
                class="btn btn-primary"
                ><Icon name="Plus" :size="18" />Nueva convocatoria</Link
            ></template
        >
        <div class="call-grid">
            <article v-for="c in calls" :key="c.id" class="panel call-card">
                <div class="row between">
                    <span class="file-symbol"
                        ><Icon name="BriefcaseBusiness" :size="24" /></span
                    ><Badge :value="c.status" />
                </div>
                <span class="eyebrow">{{ c.code }}</span>
                <h2>{{ c.title }}</h2>
                <p>{{ c.description }}</p>
                <div class="card-tags">
                    <span>{{ c.vacancies_count }} plazas</span
                    ><span>{{ c.applications_count }} expedientes</span
                    ><span>{{
                        c.is_demo ? "Demostración" : "Referencia oficial"
                    }}</span>
                </div>
                <div class="row between card-bottom">
                    <small>Cierre: {{ date(c.closes_at) }}</small
                    ><Link
                        :href="url('/gestion/convocatorias/' + c.id)"
                        class="text-link"
                        >Administrar <Icon name="ArrowRight" :size="17"
                    /></Link>
                </div>
            </article>
        </div>
        <div v-if="!calls.length" class="panel empty-state">
            <Icon name="BriefcaseBusiness" :size="40" />
            <h3>Comienza con una convocatoria</h3>
            <p>Define la información del concurso y luego añade sus plazas.</p>
        </div></AppLayout
    >
</template>
