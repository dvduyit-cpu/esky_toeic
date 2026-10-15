<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppIcon from '../Components/AppIcon.vue';
import BrandIdentity from '../Components/BrandIdentity.vue';
const page = usePage();
</script>
<template><Head title="Chọn phần thi" /><main class="portal-choice"><a href="/exam/login" class="brand"><BrandIdentity/></a><div class="portal-choice-heading"><div class="eyebrow">KHÔNG GIAN THÍ SINH</div><h1>Chọn phần thi của bạn</h1><p class="muted">Mỗi phần thi sử dụng tài khoản được cấp riêng.</p></div><div class="portal-cards"><a v-for="module in page.props.modules" :key="module.slug" :href="`/exam/${module.slug}/login`" class="card portal-card"><span class="metric-icon blue"><AppIcon :name="module.slug === 'course' ? 'book' : 'headphones'" :size="27" /></span><h2>{{ module.name }}</h2><p>{{ module.description }}</p><span class="text-link">Đăng nhập thi <AppIcon name="arrow" :size="17" /></span></a></div><div class="portal-switch"><a href="/admin/login">Dành cho quản trị viên / giáo viên →</a></div><footer class="portal-footer">{{page.props.branding.footer_text}}<span v-if="page.props.branding.footer_note"> · {{page.props.branding.footer_note}}</span></footer></main></template>
