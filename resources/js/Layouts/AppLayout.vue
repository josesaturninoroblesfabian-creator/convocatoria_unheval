<script setup>
import { ref, computed } from "vue";
import { Head, Link, usePage } from "@inertiajs/vue3";
import Icon from "../Components/Icon.vue";
import { url } from "../lib/ui";
defineProps({ title: String, subtitle: String });
const page = usePage();
const open = ref(false);
const user = computed(() => page.props.auth.user);
const manager = computed(() =>
    ["administrador", "gestor"].includes(user.value.role),
);
const items = computed(() => [
    { label: "Resumen", path: "/panel", icon: "LayoutDashboard" },
    ...(manager.value
        ? [
              {
                  label: "Convocatorias",
                  path: "/gestion/convocatorias",
                  icon: "BriefcaseBusiness",
              },
          ]
        : []),
    ...(user.value.role === "postulante"
        ? [
              {
                  label: "Mis postulaciones",
                  path: "/mis-postulaciones",
                  icon: "FolderOpen",
              },
          ]
        : [
              {
                  label: "Expedientes",
                  path: "/expedientes",
                  icon: "ClipboardCheck",
              },
          ]),
    ...(user.value.role === "administrador"
        ? [
              {
                  label: "Usuarios y accesos",
                  path: "/gestion/usuarios",
                  icon: "Users",
              },
          ]
        : []),
    ...(manager.value
        ? [
              {
                  label: "Registro de actividad",
                  path: "/gestion/auditoria",
                  icon: "Activity",
              },
          ]
        : []),
    { label: "Mi perfil", path: "/perfil", icon: "Settings2" },
]);
const active = (path) =>
    page.url.split("?")[0] === url(path) ||
    (path !== "/panel" && page.url.startsWith(url(path + "/")));
</script>
<template>
    <Head :title="title" />
    <div class="app-layout">
        <aside class="sidebar" :class="{ 'sidebar-open': open }">
            <Link :href="url('/')" class="sidebar-brand"
                ><img :src="url('/images/unheval.png')" alt="UNHEVAL" /><span
                    >CONVOCATORIAS DOCENTES</span
                ></Link
            >
            <div class="workspace-label">
                {{
                    manager
                        ? "GESTIÓN ACADÉMICA"
                        : user.role === "jurado"
                          ? "COMISIÓN EVALUADORA"
                          : "ESPACIO DEL POSTULANTE"
                }}
            </div>
            <nav>
                <Link
                    v-for="item in items"
                    :key="item.path"
                    :href="url(item.path)"
                    :class="{ active: active(item.path) }"
                    ><Icon :name="item.icon" :size="20" />{{ item.label }}</Link
                >
            </nav>
            <div class="sidebar-bottom">
                <div v-if="page.props.demo" class="demo-card">
                    <Icon name="Sparkles" :size="19" /><strong
                        >Entorno de pruebas</strong
                    >
                    <p>Los procesos demo no tienen validez oficial.</p>
                </div>
                <Link :href="url('/')"
                    ><Icon name="ExternalLink" :size="18" />Ver portal
                    público</Link
                ><Link :href="url('/ayuda')"
                    ><Icon name="HelpCircle" :size="18" />Centro de ayuda</Link
                >
            </div>
            <div class="sidebar-user">
                <span class="avatar">{{ user.name.slice(0, 1) }}</span>
                <div>
                    <b>{{ user.name }}</b
                    ><small>{{ user.role }}</small>
                </div>
                <Link
                    :href="url('/salir')"
                    method="post"
                    as="button"
                    class="icon-btn"
                    aria-label="Cerrar sesión"
                    ><Icon name="LogOut" :size="18"
                /></Link>
            </div>
        </aside>
        <div v-if="open" class="sidebar-overlay" @click="open = false"></div>
        <div class="workspace">
            <header class="workspace-header">
                <div class="row">
                    <button
                        class="mobile-menu icon-btn"
                        aria-label="Abrir navegación"
                        @click="open = !open"
                    >
                        <Icon name="Menu" /></button
                    ><span>Plataforma de convocatoria docente</span>
                </div>
                <div class="row">
                    <span class="secure-label"
                        ><Icon name="ShieldCheck" :size="15" /> Espacio
                        seguro</span
                    ><span class="header-date">{{
                        new Intl.DateTimeFormat("es-PE", {
                            dateStyle: "long",
                            timeZone: "America/Lima",
                        }).format(new Date())
                    }}</span>
                </div>
            </header>
            <main class="workspace-main">
                <div class="page-title">
                    <div>
                        <div class="eyebrow">UNHEVAL / {{ user.role }}</div>
                        <h1>{{ title }}</h1>
                        <p v-if="subtitle">{{ subtitle }}</p>
                    </div>
                    <slot name="actions" />
                </div>
                <div
                    v-if="page.props.flash.success"
                    class="notice notice-success"
                    role="status"
                >
                    {{ page.props.flash.success }}
                </div>
                <slot />
            </main>
            <footer class="workspace-footer">
                <span>UNHEVAL · Vicerrectorado Académico</span
                ><span>Huánuco, Perú</span>
            </footer>
        </div>
    </div>
</template>
