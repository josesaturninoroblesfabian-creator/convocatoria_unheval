<script setup>
import { Link } from "@inertiajs/vue3";
import PublicLayout from "../Layouts/PublicLayout.vue";
import Icon from "../Components/Icon.vue";
import Badge from "../Components/Badge.vue";
import { url, date, label } from "../lib/ui";
defineProps({ call: Object });
</script>
<template>
    <PublicLayout :title="call.title"
        ><div class="detail-heading">
            <div class="container">
                <Link :href="url('/')" class="back-link"
                    ><Icon name="ArrowLeft" :size="16" />Todas las
                    convocatorias</Link
                ><Badge :value="call.status" />
                <h1>{{ call.title }}</h1>
                <p>{{ call.code }}</p>
            </div>
        </div>
        <div class="container content-section">
            <div v-if="call.is_demo" class="notice">
                Demostración del proceso digital. Los documentos adjuntos son
                referencias de un concurso histórico; las fechas y reglas
                configuradas aquí son de prueba.
            </div>
            <section class="panel">
                <h2>Información del proceso</h2>
                <p>{{ call.description }}</p>
                <div class="info-grid">
                    <div>
                        <small>Inicio de inscripción</small
                        ><b>{{ date(call.opens_at, true) }}</b>
                    </div>
                    <div>
                        <small>Cierre de inscripción</small
                        ><b>{{ date(call.closes_at, true) }}</b>
                    </div>
                </div>
                <div class="row wrap">
                    <a
                        v-for="doc in call.public_documents"
                        :key="doc.path"
                        :href="url(doc.path)"
                        target="_blank"
                        rel="noopener"
                        class="btn btn-outline btn-sm"
                        ><Icon name="FileText" :size="16" />{{ doc.name }}</a
                    >
                </div>
            </section>
            <section class="panel">
                <h2>Plazas de esta convocatoria</h2>
                <p>
                    Consulta el perfil de la plaza y sus requisitos. Podrás
                    iniciar tu expediente durante el periodo de inscripción.
                </p>
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Escuela profesional</th>
                                <th>Facultad</th>
                                <th>Registro</th>
                                <th>Categoría</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="v in call.vacancies" :key="v.id">
                                <td>
                                    <b>{{ v.school }}</b>
                                </td>
                                <td>{{ v.faculty }}</td>
                                <td class="mono">{{ v.airhsp }}</td>
                                <td>
                                    {{ label(v.category) }} ·
                                    {{ v.weekly_hours }} h
                                </td>
                                <td>
                                    <Link
                                        :href="url('/plazas/' + v.id)"
                                        class="btn btn-primary btn-sm"
                                        >Ver perfil y postular<Icon
                                            name="ArrowRight"
                                            :size="14"
                                    /></Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
            <section class="panel">
                <h2>Cronograma</h2>
                <div
                    v-for="(activity, i) in call.schedule"
                    :key="i"
                    class="schedule-row"
                >
                    <b>{{ activity.label }}</b
                    ><span>{{ activity.date }}</span>
                </div>
            </section>
        </div></PublicLayout
    >
</template>
