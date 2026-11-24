<script setup>
import { Link, router } from "@inertiajs/vue3";
import AppLayout from "../Layouts/AppLayout.vue";
import Badge from "../Components/Badge.vue";
import Icon from "../Components/Icon.vue";
import { url, date, shortRef } from "../lib/ui";
import { useApplicationWindow } from "../lib/useApplicationWindow";
defineProps({ applications: Array });
const isOpen = useApplicationWindow();
function remove(id) {
    if (window.confirm("¿Eliminar este borrador? No se podrá recuperar.")) {
        router.delete(url("/mis-postulaciones/" + id), {
            preserveScroll: true,
        });
    }
}
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
                <h3>Aún no tienes postulaciones</h3>
                <p>
                    En Explorar plazas puedes consultar el perfil de cada plaza
                    e iniciar un expediente.
                </p>
            </div>
            <div v-for="a in applications" :key="a.id" class="application-row">
                <Link
                    :href="url('/mis-postulaciones/' + a.id)"
                    class="row grow"
                >
                    <span class="file-symbol">
                        <Icon name="FolderOpen" :size="24" />
                    </span>
                    <span class="grow">
                        <b>{{ a.vacancy.school }}</b
                        ><small>{{ a.recruitment_call.title }}</small
                        ><small
                            >{{ shortRef(a.reference) }} ·
                            {{ a.documents_count }}
                            documentos ·
                            {{
                                a.submitted_at
                                    ? "Enviado " + date(a.submitted_at)
                                    : "Borrador sin enviar"
                            }}</small
                        >
                    </span>
                    <Badge :value="a.status" />
                    <span class="text-link">
                        {{
                            a.status === "BORRADOR" &&
                            isOpen(a.recruitment_call)
                                ? "Continuar borrador"
                                : "Ver expediente"
                        }}
                        <Icon name="ChevronRight" :size="16" />
                    </span>
                </Link>
                <button
                    v-if="a.status === 'BORRADOR'"
                    type="button"
                    class="icon-btn"
                    aria-label="Eliminar borrador"
                    @click="remove(a.id)"
                >
                    <Icon name="X" :size="18" />
                </button>
            </div></section
    ></AppLayout>
</template>
