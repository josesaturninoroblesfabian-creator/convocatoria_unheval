<script setup>
import { onUnmounted, ref, watch } from "vue";
import { Head, Link, useForm, useHttp } from "@inertiajs/vue3";
import Icon from "../Components/Icon.vue";
import Errors from "../Components/Errors.vue";
import { url } from "../lib/ui";
const props = defineProps({
    mode: String,
    token: String,
    email: String,
    dniLookupEnabled: Boolean,
});
const form = useForm({
    name: "",
    document_number: "",
    phone: "",
    email: props.email || "",
    password: "",
    password_confirmation: "",
    remember: false,
    consent: false,
    token: props.token || "",
});
const dniLookup = useHttp({ dni: "", consent: false });
const dniMessage = ref("");
let timer;
let attemptedDni = "";
let suggestedName = "";

async function fillNameFromDni() {
    const dni = form.document_number;
    if (
        props.mode !== "register" ||
        !props.dniLookupEnabled ||
        !dniLookup.consent ||
        !/^[0-9]{8}$/.test(dni) ||
        dniLookup.processing ||
        attemptedDni === dni
    )
        return;

    attemptedDni = dni;
    dniLookup.dni = dni;
    const currentName = form.name;
    try {
        const result = await dniLookup.post(url("/registro/consultar-dni"));
        if (form.document_number !== dni || !dniLookup.consent) return;
        if (result.status === "found" && form.name === currentName) {
            form.name = suggestedName = result.name;
            dniMessage.value =
                "Nombre completado. Revísalo antes de crear tu cuenta.";
        } else {
            dniMessage.value =
                result.status === "found"
                    ? "La consulta respondió. Conservamos el nombre que estás editando."
                    : "No se pudo autocompletar el nombre. Puedes escribirlo manualmente.";
        }
    } catch {
        if (form.document_number === dni) {
            dniMessage.value =
                "No se pudo autocompletar el nombre. Puedes escribirlo manualmente.";
        }
    }
}

watch(
    () => form.document_number,
    () => {
        if (suggestedName && form.name === suggestedName) form.name = "";
        suggestedName = "";
        dniMessage.value = "";
    },
);
watch(
    [
        () => form.document_number,
        () => dniLookup.consent,
        () => dniLookup.processing,
    ],
    () => {
        clearTimeout(timer);
        if (!dniLookup.processing) timer = setTimeout(fillNameFromDni, 400);
    },
);
onUnmounted(() => {
    clearTimeout(timer);
    dniLookup.cancel();
});
const titles = {
    login: "Bienvenido de nuevo",
    register: "Tu futuro empieza aquí",
    forgot: "Recupera tu acceso",
    reset: "Elige una nueva contraseña",
};
function submit() {
    const paths = {
        login: "/ingresar",
        register: "/registro",
        forgot: "/recuperar-acceso",
        reset: "/restablecer",
    };
    form.post(url(paths[props.mode]), {
        onFinish: () => form.reset("password", "password_confirmation"),
    });
}
function demo(role) {
    form.email = role + "@demo.test";
    form.password = {
        admin: "90000001",
        gestor: "90000002",
        jurado: "90000003",
        jurado2: "90000004",
        jurado3: "90000005",
        postulante: "90000006",
    }[role];
}
</script>
<template>
    <Head :title="titles[mode]" />
    <div class="auth-layout">
        <aside class="auth-visual">
            <img :src="url('/images/campus.webp')" alt="Campus UNHEVAL" />
            <div></div>
            <Link :href="url('/')" class="auth-brand"
                ><img :src="url('/images/unheval.png')" alt="UNHEVAL"
            /></Link>
            <section>
                <span class="hero-kicker">COMUNIDAD DOCENTE VALDIZANA</span>
                <h1>El conocimiento<br />crece cuando<br />lo compartes.</h1>
                <p>
                    Una nueva oportunidad para enseñar,<br />investigar y
                    transformar nuestra región.
                </p>
                <span class="row"
                    ><Icon name="MapPin" :size="16" /> Huánuco, Perú</span
                >
            </section>
            <footer>Universidad Nacional Hermilio Valdizán</footer>
        </aside>
        <main class="auth-main">
            <Link :href="url('/')" class="back-link"
                ><Icon name="ArrowLeft" :size="16" /> Volver a
                convocatorias</Link
            >
            <div class="auth-form">
                <span class="eyebrow">CONVOCATORIAS DOCENTES</span>
                <h2>{{ titles[mode] }}</h2>
                <p>
                    {{
                        mode === "register"
                            ? "Crea tu cuenta para preparar y seguir tus postulaciones."
                            : mode === "forgot"
                              ? "Te enviaremos un enlace si tu correo está registrado."
                              : "Accede a tu espacio en la plataforma de convocatorias."
                    }}
                </p>
                <div
                    v-if="$page.props.flash.success"
                    class="notice notice-success"
                >
                    {{ $page.props.flash.success }}
                </div>
                <Errors :errors="form.errors" />
                <form @submit.prevent="submit">
                    <template v-if="mode === 'register'">
                        <label>
                            DNI<input
                                v-model="form.document_number"
                                required
                                inputmode="numeric"
                                pattern="[0-9]{8}"
                                maxlength="8"
                                placeholder="8 dígitos"
                            />
                        </label>
                        <label v-if="dniLookupEnabled" class="check-label">
                            <input
                                v-model="dniLookup.consent"
                                type="checkbox"
                            />
                            Autorizo consultar mi DNI en APIs Perú para
                            completar mi nombre.
                        </label>
                        <p
                            v-if="
                                dniLookupEnabled &&
                                (dniLookup.processing || dniMessage)
                            "
                            class="muted"
                            aria-live="polite"
                        >
                            {{
                                dniLookup.processing
                                    ? "Consultando DNI…"
                                    : dniMessage
                            }}
                        </p>
                        <div class="form-grid">
                            <label
                                >Nombres y apellidos<input
                                    v-model="form.name"
                                    required
                                    maxlength="150"
                                    autocomplete="name"
                                    placeholder="Como aparecen en tu DNI"
                            /></label>
                            <label
                                >Teléfono<input
                                    v-model="form.phone"
                                    required
                                    type="tel"
                                    autocomplete="tel"
                                    placeholder="Número de contacto"
                            /></label></div></template
                    ><label
                        >Correo electrónico<input
                            v-model="form.email"
                            required
                            type="email"
                            autocomplete="email"
                            placeholder="nombre@correo.com" /></label
                    ><label v-if="mode !== 'forgot'"
                        >Contraseña<input
                            v-model="form.password"
                            required
                            type="password"
                            :autocomplete="
                                mode === 'login'
                                    ? 'current-password'
                                    : 'new-password'
                            "
                            :minlength="mode === 'login' ? 1 : 10"
                            :placeholder="
                                mode === 'login'
                                    ? 'Ingresa tu contraseña'
                                    : 'Mínimo 10 caracteres, letras y números'
                            " /></label
                    ><label v-if="['register', 'reset'].includes(mode)"
                        >Confirmar contraseña<input
                            v-model="form.password_confirmation"
                            required
                            type="password"
                            autocomplete="new-password"
                            placeholder="Repite tu contraseña"
                    /></label>
                    <div
                        v-if="mode === 'login'"
                        class="row between auth-options"
                    >
                        <label class="check-label"
                            ><input
                                type="checkbox"
                                v-model="form.remember"
                            />Recordarme</label
                        ><Link :href="url('/recuperar-acceso')"
                            >Olvidé mi contraseña</Link
                        >
                    </div>
                    <label
                        v-if="mode === 'register'"
                        class="check-label consent"
                        ><input
                            v-model="form.consent"
                            type="checkbox"
                            required
                        /><span
                            >Acepto el tratamiento de mis datos para la gestión
                            del concurso. He leído la
                            <Link :href="url('/ayuda#privacidad')"
                                >información de privacidad</Link
                            >.</span
                        ></label
                    ><button
                        class="btn btn-primary full"
                        :disabled="form.processing"
                    >
                        {{
                            form.processing
                                ? "Procesando…"
                                : mode === "register"
                                  ? "Crear mi cuenta"
                                  : mode === "forgot"
                                    ? "Enviar enlace"
                                    : mode === "reset"
                                      ? "Guardar contraseña"
                                      : "Iniciar sesión"
                        }}<Icon name="ArrowRight" :size="18" />
                    </button>
                </form>
                <p v-if="mode === 'login'" class="auth-switch">
                    ¿Es tu primera postulación?
                    <Link :href="url('/registro')">Crea tu cuenta</Link>
                </p>
                <p v-else class="auth-switch">
                    ¿Ya tienes una cuenta?
                    <Link :href="url('/ingresar')">Inicia sesión</Link>
                </p>
                <div
                    v-if="$page.props.demo && mode === 'login'"
                    class="demo-login"
                >
                    <span>EXPLORA EL SISTEMA CON UNA CUENTA DE PRUEBA</span>
                    <div class="row wrap">
                        <button
                            v-for="role in [
                                'admin',
                                'gestor',
                                'jurado',
                                'postulante',
                            ]"
                            :key="role"
                            type="button"
                            @click="demo(role)"
                        >
                            {{
                                role === "admin"
                                    ? "Administrador"
                                    : role.charAt(0).toUpperCase() +
                                      role.slice(1)
                            }}
                        </button>
                    </div>
                    <small
                        >Selecciona un perfil y pulsa Iniciar sesión.
                        Credenciales solo de desarrollo.</small
                    >
                </div>
                <div class="auth-secure">
                    <Icon name="LockKeyhole" :size="15" /> Tus documentos se
                    guardan en un espacio privado.
                </div>
            </div>
        </main>
    </div>
</template>
