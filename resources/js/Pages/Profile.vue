<script setup>
import { useForm, usePage } from "@inertiajs/vue3";
import AppLayout from "../Layouts/AppLayout.vue";
import Icon from "../Components/Icon.vue";
import Errors from "../Components/Errors.vue";
import { url } from "../lib/ui";
const user = usePage().props.auth.user;
const form = useForm({
    name: user.name,
    document_number: user.document_number || "",
    phone: user.phone || "",
});
</script>
<template>
    <AppLayout
        title="Mi perfil"
        subtitle="Mantén tus datos de contacto actualizados."
        ><form
            class="panel narrow-panel"
            @submit.prevent="form.put(url('/perfil'))"
        >
            <div class="panel-heading">
                <Icon name="Users" />
                <h2>Datos personales</h2>
            </div>
            <Errors :errors="form.errors" /><label
                >Nombres y apellidos<input
                    v-model="form.name"
                    required
                    maxlength="150"
                    autocomplete="name"
            /></label>
            <div class="form-grid">
                <label
                    >DNI<input
                        v-model="form.document_number"
                        required
                        inputmode="numeric"
                        maxlength="8"
                        pattern="[0-9]{8}" /></label
                ><label
                    >Teléfono<input
                        v-model="form.phone"
                        required
                        type="tel"
                        autocomplete="tel"
                /></label>
            </div>
            <label
                >Correo de acceso<input :value="user.email" disabled
            /></label>
            <p class="muted">
                Los cambios se utilizarán en tus próximas postulaciones. La
                información incluida en los expedientes ya enviados permanece
                conservada.
            </p>
            <button class="btn btn-primary" :disabled="form.processing">
                {{ form.processing ? "Guardando…" : "Guardar mis datos" }}
            </button>
        </form></AppLayout
    >
</template>
