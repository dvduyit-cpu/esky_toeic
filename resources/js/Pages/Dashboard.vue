<script setup>
import {Head,usePage} from '@inertiajs/vue3';import AppLayout from '../Layouts/AppLayout.vue';
defineProps({stats:Object});const user=usePage().props.auth.user;
</script>
<template><Head title="Tổng quan"/><AppLayout><h1>Tổng quan hệ thống</h1><p class="muted">Chào mừng {{user.name}} đến với E-SKY TOEIC.</p><div class="grid"><div class="card" v-for="(value,key) in stats" :key="key"><div class="muted">{{({users:'Tài khoản',questions:'Câu hỏi',exams:'Đề thi'})[key]||key}}</div><div class="stat">{{value}}</div></div></div><div class="card" style="margin-top:20px"><h2>Giai đoạn 1 · Hệ thống nền</h2><p>Tài khoản, phân quyền, ngân hàng câu hỏi và đề thi nháp đã được tạo cấu trúc. Module thi và chấm điểm sẽ phát triển trong giai đoạn tiếp theo.</p></div></AppLayout></template>
