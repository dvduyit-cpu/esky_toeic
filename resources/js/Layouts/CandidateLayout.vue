<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import AppIcon from '../Components/AppIcon.vue';
import BrandIdentity from '../Components/BrandIdentity.vue';
const page = usePage();
</script>
<template><div class="candidate-shell" :class="{'candidate-lobby':page.component==='Candidate/Home'}"><header class="candidate-header"><Link :href="page.props.context.candidate_base" class="brand"><BrandIdentity :subtitle="'CỔNG THI '+page.props.context.name.toUpperCase()"/></Link><div class="candidate-profile"><span class="candidate-user-chip"><span class="avatar">{{page.props.auth.user.name.slice(0,1)}}</span>{{ page.props.auth.user.name }}</span><button class="secondary" @click="router.post('/logout')"><AppIcon name="logout" :size="16" />Đăng xuất</button></div></header><main class="candidate-content"><div v-if="Object.keys(page.props.errors || {}).length" class="notice error-notice" role="alert">{{ Object.values(page.props.errors).join(' · ') }}</div><slot /></main><footer class="candidate-footer">{{page.props.branding.footer_text}}<span v-if="page.props.branding.footer_note"> · {{page.props.branding.footer_note}}</span></footer></div></template>
