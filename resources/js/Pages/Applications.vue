<script setup>
import { Link } from "@inertiajs/vue3";
import AppLayout from "../Layouts/AppLayout.vue";
import Badge from "../Components/Badge.vue";
import Icon from "../Components/Icon.vue";
import { url, date, shortRef } from "../lib/ui";
defineProps({ applications: Array });
</script>
<template>
    <AppLayout
        title="Mis postulaciones"
        subtitle="Retoma tus borradores y consulta el estado de tus expedientes."
        ><template #actions
            ><Link :href="url('/')" class="btn btn-primary"
                >Explorar plazas <Icon name="ArrowUpRight" :size="17" /></Link
        ></template>
        <section class="panel">
            <div v-if="!applications.length" class="empty-state">
                <Icon name="FolderOpen" :size="42" />
                <h3>Tu próxima oportunidad te espera</h3>
                <p>Elige una plaza e inicia tu primer expediente.</p>
                <Link :href="url('/')" class="btn btn-primary"
                    >Ver convocatorias</Link
                >
            </div>
            <Link
                v-for="a in applications"
                :key="a.id"
                :href="url('/mis-postulaciones/' + a.id)"
                class="application-row"
                ><div class="file-symbol">
                    <Icon name="FolderOpen" :size="24" />
                </div>
                <div class="grow">
                    <b>{{ a.vacancy.school }}</b
                    ><small>{{ a.recruitment_call.title }}</small
                    ><small
                        >{{ shortRef(a.reference) }} ·
                        {{ a.documents_count }} documentos ·
                        {{
                            a.submitted_at
                                ? "Enviado " + date(a.submitted_at)
                                : "Borrador sin enviar"
                        }}</small
                    >
                </div>
                <Badge :value="a.status" /><Icon name="ChevronRight"
            /></Link></section
    ></AppLayout>
</template>
