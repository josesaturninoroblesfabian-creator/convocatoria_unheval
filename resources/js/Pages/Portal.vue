<script setup>
import { ref } from "vue";
import { Link, router } from "@inertiajs/vue3";
import PublicLayout from "../Layouts/PublicLayout.vue";
import Icon from "../Components/Icon.vue";
import Badge from "../Components/Badge.vue";
import Pagination from "../Components/Pagination.vue";
import { url, date, label } from "../lib/ui";
import { useApplicationWindow } from "../lib/useApplicationWindow";
const isOpen = useApplicationWindow();
const props = defineProps({
    vacancies: Object,
    filters: Object,
    faculties: Array,
    calls: Array,
    stats: Object,
});
const search = ref(props.filters.q || "");
const faculty = ref(props.filters.faculty || "");
const status = ref(props.filters.status || "abiertas");
function filter() {
    router.get(
        url("/"),
        { q: search.value, faculty: faculty.value, status: status.value },
        { preserveState: true, preserveScroll: true },
    );
}
function tab(value) {
    status.value = value;
    filter();
}
</script>
<template>
    <PublicLayout title="Convocatorias docentes"
        ><section class="hero">
            <img
                :src="url('/images/campus.webp')"
                alt="Campus de la Universidad Nacional Hermilio Valdizán"
                class="hero-photo"
            />
            <div class="hero-shade"></div>
            <div class="container hero-content">
                <div class="hero-kicker">
                    <span></span> TALENTO QUE TRANSFORMA
                </div>
                <h1>
                    Tu vocación enseña.<br />Tu talento <em>trasciende.</em>
                </h1>
                <p>
                    Forma parte de la comunidad docente valdizana.<br />Encuentra
                    tu próxima oportunidad en la UNHEVAL.
                </p>
                <a href="#convocatorias" class="btn btn-gold"
                    >Explorar convocatorias <Icon name="ArrowDown" :size="18"
                /></a>
                <div class="hero-location">
                    <Icon name="MapPin" :size="15" /> Huánuco, Perú
                    <span>·</span> Conocimiento al servicio de la sociedad
                </div>
            </div>
            <div class="hero-index">
                <span>01</span><i></i><small>UNHEVAL<br />DESDE 1964</small>
            </div>
        </section>
        <section class="stats-strip">
            <div class="container stats-inner">
                <div>
                    <span class="stat-icon"
                        ><Icon name="BriefcaseBusiness" :size="25" /></span
                    ><b>{{ String(stats.open).padStart(2, "0") }}</b
                    ><span
                        >Convocatorias<br /><strong
                            >con inscripción abierta</strong
                        ></span
                    >
                </div>
                <div>
                    <span class="stat-icon"
                        ><Icon name="GraduationCap" :size="27" /></span
                    ><b>{{ String(stats.vacancies).padStart(2, "0") }}</b
                    ><span
                        >Plazas docentes<br /><strong
                            >disponibles para postular</strong
                        ></span
                    >
                </div>
                <div>
                    <span class="stat-icon"
                        ><Icon name="Building2" :size="25" /></span
                    ><b>{{ String(stats.faculties).padStart(2, "0") }}</b
                    ><span
                        >Facultades<br /><strong
                            >representadas en el portal</strong
                        ></span
                    >
                </div>
                <div class="stats-note">
                    <Icon name="ShieldCheck" :size="24" /><span
                        >Un proceso claro,<br /><strong
                            >transparente y trazable.</strong
                        ></span
                    >
                </div>
            </div>
        </section>
        <section class="catalog-section container" id="convocatorias">
            <div class="section-heading">
                <div>
                    <span class="eyebrow"
                        >CONSTRUYE TU FUTURO CON NOSOTROS</span
                    >
                    <h2>Oportunidades para enseñar</h2>
                    <p>
                        Consulta los perfiles, revisa los requisitos y prepara
                        tu postulación.
                    </p>
                </div>
                <span class="edition-label"
                    ><Icon name="CalendarDays" :size="17" /> Convocatorias
                    {{ new Date().getFullYear() }}</span
                >
            </div>
            <div v-if="$page.props.demo" class="demo-notice">
                <Icon name="CircleAlert" :size="18" /><span
                    ><strong>Estás en el entorno de demostración.</strong> Las
                    plazas provienen del cuadro 2026-IV; las fechas y
                    evaluaciones de prueba no constituyen una convocatoria
                    oficial.</span
                >
            </div>
            <div class="catalog-tabs">
                <button
                    :class="{ active: status === 'abiertas' }"
                    @click="tab('abiertas')"
                >
                    Vigentes y próximas <span>{{ stats.open }}</span></button
                ><button
                    :class="{ active: status === 'cerradas' }"
                    @click="tab('cerradas')"
                >
                    Cerradas e históricas</button
                ><button
                    :class="{ active: status === 'todas' }"
                    @click="tab('todas')"
                >
                    Todas las plazas
                </button>
            </div>
            <form class="search-panel" @submit.prevent="filter">
                <div class="search-input">
                    <Icon name="Search" :size="20" /><input
                        aria-label="Buscar plaza"
                        v-model="search"
                        placeholder="Buscar por especialidad o código de plaza…"
                    />
                </div>
                <select
                    aria-label="Filtrar por facultad"
                    v-model="faculty"
                    @change="filter"
                >
                    <option value="">Todas las facultades</option>
                    <option v-for="f in faculties" :key="f">
                        {{ f }}
                    </option></select
                ><button class="btn btn-primary" type="submit">
                    <Icon name="Filter" :size="17" /> Buscar plazas
                </button>
            </form>
            <div class="results-count">
                <span
                    ><strong>{{ vacancies.total }}</strong> plazas
                    encontradas</span
                ><span class="muted"
                    >Ordenadas por publicación más reciente</span
                >
            </div>
            <div class="process-table table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Convocatoria</th>
                            <th>Documentos de referencia</th>
                            <th>Inscripción / estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="call in calls" :key="call.id">
                            <td>
                                <Link
                                    :href="url('/convocatorias/' + call.id)"
                                    class="process-title"
                                    >{{ call.title }}</Link
                                >
                                <p>
                                    {{ call.code }} ·
                                    {{ call.vacancies_count }} plazas
                                </p>
                                <small v-if="call.is_demo" class="muted"
                                    >Caso práctico con perfiles del cuadro
                                    2026-IV</small
                                >
                            </td>
                            <td>
                                <div class="process-docs">
                                    <a
                                        v-for="doc in call.public_documents"
                                        :key="doc.path"
                                        :href="url(doc.path)"
                                        target="_blank"
                                        rel="noopener"
                                        class="btn btn-outline btn-sm"
                                        ><Icon name="FileText" :size="14" />{{
                                            doc.name
                                        }}</a
                                    >
                                </div>
                            </td>
                            <td>
                                <div class="process-state">
                                    <Badge :value="call.status" /><span
                                        class="muted"
                                        >{{ date(call.opens_at, true) }}<br />al
                                        {{ date(call.closes_at, true) }}</span
                                    ><span>{{
                                        isOpen(call)
                                            ? "Ya puedes postular"
                                            : call.status === "PUBLICADA" &&
                                                new Date(call.opens_at) >
                                                    new Date()
                                              ? "Próximamente"
                                              : "Inscripción cerrada"
                                    }}</span>
                                </div>
                            </td>
                            <td>
                                <Link
                                    :href="url('/convocatorias/' + call.id)"
                                    class="btn btn-primary btn-sm"
                                    >Ver plazas<Icon
                                        name="ArrowRight"
                                        :size="14"
                                /></Link>
                            </td>
                        </tr>
                        <tr v-if="!calls.length">
                            <td colspan="4">
                                No hay convocatorias con estos filtros.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <h3 class="section-heading">Plazas con inscripción disponible</h3>
            <div class="panel table-scroll plaza-list-table">
                <table>
                    <thead>
                        <tr>
                            <th>Plaza / escuela profesional</th>
                            <th>Facultad</th>
                            <th>Categoría</th>
                            <th>Cierre</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="v in vacancies.data" :key="v.id">
                            <td>
                                <Link
                                    :href="url('/plazas/' + v.id)"
                                    class="table-title"
                                    >{{ v.school }}</Link
                                ><small
                                    >AIRHSP {{ v.airhsp }} · CAP
                                    {{ v.cap }}</small
                                >
                            </td>
                            <td>{{ v.faculty }}</td>
                            <td>
                                {{ label(v.category)
                                }}<small
                                    >{{ v.weekly_hours }} h ·
                                    {{ label(v.dedication) }}</small
                                >
                            </td>
                            <td>
                                {{ date(v.recruitment_call.closes_at, true) }}
                            </td>
                            <td>
                                <Link
                                    :href="url('/plazas/' + v.id)"
                                    class="btn btn-outline btn-sm"
                                    >Perfil y postulación<Icon
                                        name="ArrowRight"
                                        :size="14"
                                /></Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="!vacancies.total" class="empty-state">
                <Icon name="Search" :size="36" />
                <h3>No encontramos plazas con estos filtros</h3>
                <p>
                    Prueba otra especialidad o consulta las convocatorias
                    históricas.
                </p>
                <button
                    class="btn btn-outline"
                    @click="
                        search = '';
                        faculty = '';
                        tab('todas');
                    "
                >
                    Ver todas las plazas
                </button>
            </div>
            <Pagination :data="vacancies" />
        </section>
        <section class="how-section">
            <div class="container">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow">TU PRÓXIMO PASO, MÁS CERCA</span>
                        <h2>Postular es un proceso sencillo</h2>
                    </div>
                    <Link :href="url('/ayuda')" class="text-link"
                        >Conoce la guía completa
                        <Icon name="ArrowUpRight" :size="18"
                    /></Link>
                </div>
                <div class="steps-grid">
                    <div
                        v-for="(step, i) in [
                            {
                                icon: 'Users',
                                title: 'Crea tu cuenta',
                                text: 'Registra tus datos personales y accede a tu espacio de postulante.',
                            },
                            {
                                icon: 'Search',
                                title: 'Encuentra tu plaza',
                                text: 'Revisa el perfil profesional, las bases y el cronograma del concurso.',
                            },
                            {
                                icon: 'Upload',
                                title: 'Completa tu formulario',
                                text: 'Declara tu formación y experiencia, comprueba los mínimos y adjunta sus sustentos.',
                            },
                            {
                                icon: 'ClipboardCheck',
                                title: 'Sigue tu postulación',
                                text: 'Conserva tu cargo de recepción y consulta el estado desde tu panel.',
                            },
                        ]"
                        :key="i"
                    >
                        <div class="step-top">
                            <span class="step-icon"
                                ><Icon :name="step.icon" :size="24" /></span
                            ><span class="step-number">0{{ i + 1 }}</span>
                        </div>
                        <h3>{{ step.title }}</h3>
                        <p>{{ step.text }}</p>
                    </div>
                </div>
            </div>
        </section>
        <section class="help-banner container">
            <div class="help-banner-icon">
                <Icon name="HelpCircle" :size="34" />
            </div>
            <div>
                <h3>Te acompañamos en cada paso</h3>
                <p>
                    Encuentra orientación sobre el registro, tus documentos y el
                    envío del expediente.
                </p>
            </div>
            <Link :href="url('/ayuda')" class="btn btn-outline"
                >Guía del postulante <Icon name="ArrowRight" :size="17"
            /></Link></section
    ></PublicLayout>
</template>
