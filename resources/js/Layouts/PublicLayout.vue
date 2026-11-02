<script setup>
import { ref } from "vue";
import { Head, Link, usePage } from "@inertiajs/vue3";
import Icon from "../Components/Icon.vue";
import { url } from "../lib/ui";
defineProps({ title: String });
const page = usePage();
const menu = ref(false);
</script>
<template>
    <Head :title="title" />
    <div class="public-layout">
        <div class="institutional-bar">
            <div class="container row between">
                <span>Universidad Nacional Hermilio Valdizán</span
                ><a
                    href="https://unheval.edu.pe/portal/"
                    target="_blank"
                    rel="noopener"
                    >Portal institucional <Icon name="ArrowUpRight" :size="13"
                /></a>
            </div>
        </div>
        <header class="public-header">
            <div class="container header-inner">
                <Link :href="url('/')" class="brand"
                    ><img
                        :src="url('/images/unheval.png')"
                        alt="UNHEVAL"
                    /><span class="brand-divider"></span
                    ><span class="brand-caption"
                        >CONVOCATORIAS<br /><b>DOCENTES</b></span
                    ></Link
                ><button
                    class="mobile-menu icon-btn"
                    aria-label="Abrir menú"
                    @click="menu = !menu"
                >
                    <Icon name="Menu" />
                </button>
                <nav :class="{ 'nav-open': menu }">
                    <Link
                        :href="url('/')"
                        :class="{ active: page.component === 'Portal' }"
                        >Convocatorias</Link
                    ><Link
                        :href="url('/resultados')"
                        :class="{ active: page.component === 'Results' }"
                        >Resultados</Link
                    ><Link
                        :href="url('/ayuda')"
                        :class="{ active: page.component === 'Help' }"
                        >Guía del postulante</Link
                    ><Link
                        v-if="page.props.auth.user"
                        :href="url('/panel')"
                        class="btn btn-primary btn-sm"
                        >Mi espacio <Icon name="ArrowRight" :size="16" /></Link
                    ><Link
                        v-else
                        :href="url('/ingresar')"
                        class="btn btn-primary btn-sm"
                        >Ingresar <Icon name="ArrowRight" :size="16"
                    /></Link>
                </nav>
            </div>
        </header>
        <div v-if="page.props.flash.success" class="container">
            <div class="notice notice-success" role="status">
                {{ page.props.flash.success }}
            </div>
        </div>
        <main><slot /></main>
        <footer class="public-footer">
            <div class="container">
                <div class="footer-top">
                    <div>
                        <img
                            :src="url('/images/unheval.png')"
                            alt="UNHEVAL"
                            class="footer-logo"
                        />
                        <p>
                            Formamos profesionales.<br />Construimos el futuro
                            de nuestra región.
                        </p>
                    </div>
                    <div>
                        <h4>Convocatorias docentes</h4>
                        <Link :href="url('/')">Explorar plazas</Link
                        ><Link :href="url('/resultados')"
                            >Resultados publicados</Link
                        ><Link :href="url('/ayuda')"
                            >Orientación y privacidad</Link
                        >
                    </div>
                    <div>
                        <h4>Encuéntranos</h4>
                        <p>
                            Av. Universitaria N.° 601–607<br />Pillco Marca,
                            Huánuco, Perú
                        </p>
                        <a
                            href="https://unheval.edu.pe/portal/"
                            target="_blank"
                            rel="noopener"
                            >Portal oficial UNHEVAL
                            <Icon name="ArrowUpRight" :size="14"
                        /></a>
                    </div>
                </div>
                <div class="footer-bottom">
                    <span
                        >© {{ new Date().getFullYear() }} Universidad Nacional
                        Hermilio Valdizán</span
                    ><span>Hora oficial del sistema: Perú (UTC−5)</span>
                </div>
            </div>
        </footer>
    </div>
</template>
