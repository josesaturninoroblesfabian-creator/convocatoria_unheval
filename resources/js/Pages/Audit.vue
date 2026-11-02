<script setup>
import AppLayout from "../Layouts/AppLayout.vue";
import Pagination from "../Components/Pagination.vue";
import { date } from "../lib/ui";
defineProps({ events: Object });
</script>
<template>
    <AppLayout
        title="Registro de actividad"
        subtitle="Consulta quién realizó cada acción y cuándo ocurrió."
        ><section class="panel">
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha (Perú)</th>
                            <th>Acción</th>
                            <th>Usuario</th>
                            <th>Registro afectado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="e in events.data" :key="e.id">
                            <td>{{ date(e.created_at, true) }}</td>
                            <td>{{ e.action.replaceAll("_", " ") }}</td>
                            <td>
                                {{
                                    e.user_id
                                        ? "Usuario #" + e.user_id
                                        : "Sistema"
                                }}
                            </td>
                            <td>
                                {{
                                    e.subject_type.slice(
                                        e.subject_type.lastIndexOf(
                                            String.fromCharCode(92),
                                        ) + 1,
                                    )
                                }}
                                #{{ e.subject_id }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="!events.total" class="empty-state">
                Todavía no hay acciones registradas.
            </div>
            <Pagination :data="events" /></section
    ></AppLayout>
</template>
