<script setup>
import { label } from "../lib/ui";
defineProps({ value: String });
</script>
<template>
    <span class="badge" :class="'badge-' + value?.toLowerCase()"
        ><span class="badge-dot"></span>{{ label(value) }}</span
    >
</template>
