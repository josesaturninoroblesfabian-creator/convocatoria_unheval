<script setup>
defineProps({ errors: Object });
</script>
<template>
    <div
        v-if="errors && Object.keys(errors).length"
        class="notice notice-error"
        role="alert"
    >
        <strong>Revisa la información</strong>
        <ul>
            <li v-for="(error, key) in errors" :key="key">{{ error }}</li>
        </ul>
    </div>
</template>
