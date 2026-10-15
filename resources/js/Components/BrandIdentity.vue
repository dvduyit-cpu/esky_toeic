<script setup>
import {computed} from 'vue';import {usePage} from '@inertiajs/vue3';import AppIcon from './AppIcon.vue';
defineProps({settings:Object,subtitle:String});const page=usePage();const branding=computed(()=>page.props.branding);
</script>
<template><span class="brand-mark" :class="{'custom-logo':(settings||branding).logo_url}"><img v-if="(settings||branding).logo_url" :src="(settings||branding).logo_url" :alt="'Logo '+(settings||branding).software_name"/><AppIcon v-else name="headphones" :size="25"/></span><span class="brand-copy"><strong>{{(settings||branding).software_name}}</strong><small>{{subtitle||(settings||branding).tagline}}</small></span></template>
