import { createApp, h } from "vue";
import { createInertiaApp } from "@inertiajs/vue3";
createInertiaApp({
    title: (title) => `${title} · Convocatorias UNHEVAL`,
    resolve: (name) =>
        import.meta.glob("./Pages/**/*.vue")[`./Pages/${name}.vue`](),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: { color: "#0879be" },
});
