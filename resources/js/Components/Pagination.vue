<script setup>
import { Link } from "@inertiajs/vue3";
defineProps({ data: Object });
</script>
<template>
    <div v-if="data.last_page > 1" class="pagination">
        <span>Mostrando {{ data.from }}–{{ data.to }} de {{ data.total }}</span>
        <div class="row">
            <Link
                v-if="data.prev_page_url"
                class="btn btn-outline btn-sm"
                :href="data.prev_page_url"
                >Anterior</Link
            ><span>{{ data.current_page }} / {{ data.last_page }}</span
            ><Link
                v-if="data.next_page_url"
                class="btn btn-outline btn-sm"
                :href="data.next_page_url"
                >Siguiente</Link
            >
        </div>
    </div>
</template>
