<script setup>
import { ref } from "vue";
import { useForm } from "@inertiajs/vue3";
import AppLayout from "../Layouts/AppLayout.vue";
import Icon from "../Components/Icon.vue";
import Errors from "../Components/Errors.vue";
import Pagination from "../Components/Pagination.vue";
import { url } from "../lib/ui";
defineProps({ users: Object });
const adding = ref(false);
const form = useForm({ name: "", email: "", role: "jurado", password: "" });
function save() {
    form.post(url("/gestion/usuarios"), {
        onSuccess: () => {
            form.reset();
            adding.value = false;
        },
    });
}
</script>
<template>
    <AppLayout
        title="Usuarios y accesos"
        subtitle="Cada persona cuenta con un rol y un espacio de trabajo definido."
        ><template #actions
            ><button class="btn btn-primary" @click="adding = !adding">
                <Icon :name="adding ? 'X' : 'Plus'" :size="18" />{{
                    adding ? "Cerrar" : "Nuevo usuario interno"
                }}
            </button></template
        ><Errors :errors="form.errors" />
        <section v-if="adding" class="panel">
            <h2>Crear usuario interno</h2>
            <form @submit.prevent="save">
                <div class="form-grid">
                    <label
                        >Nombres y apellidos<input
                            v-model="form.name"
                            required
                            maxlength="150" /></label
                    ><label
                        >Correo<input
                            v-model="form.email"
                            type="email"
                            required
                            maxlength="150" /></label
                    ><label
                        >Rol<select v-model="form.role">
                            <option value="jurado">Jurado</option>
                            <option value="gestor">
                                Gestor de convocatorias
                            </option>
                            <option value="administrador">
                                Administrador del sistema
                            </option>
                        </select></label
                    ><label
                        >Contraseña inicial<input
                            v-model="form.password"
                            type="password"
                            required
                            minlength="12"
                            autocomplete="new-password"
                            placeholder="12 caracteres, letras, números y símbolos"
                    /></label>
                </div>
                <button class="btn btn-primary" :disabled="form.processing">
                    Crear usuario
                </button>
            </form>
        </section>
        <section class="panel">
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Correo</th>
                            <th>Rol</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="u in users.data" :key="u.id">
                            <td>
                                <span class="row"
                                    ><span class="avatar small">{{
                                        u.name.slice(0, 1)
                                    }}</span
                                    ><b>{{ u.name }}</b></span
                                >
                            </td>
                            <td>{{ u.email }}</td>
                            <td>
                                <span class="role-pill">{{ u.role }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :data="users" />
        </section>
        <div class="notice">
            Los postulantes crean sus propias cuentas desde el portal. El
            registro público siempre asigna el rol postulante.
        </div></AppLayout
    >
</template>
