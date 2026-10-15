<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
const props = defineProps({ errors: Object });
const items = computed(() => Object.values(props.errors || {}));
</script>
<template><div v-if="items.length" class="notice error-notice" role="alert">{{ items.join(' · ') }}</div></template>
