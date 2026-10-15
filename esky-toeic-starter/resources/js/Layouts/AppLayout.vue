<script setup>
import { usePage, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
const page=usePage(); const user=computed(()=>page.props.auth?.user);
const logout=()=>router.post('/logout');
</script>
<template>
 <div class="shell"><aside class="sidebar"><h2>🎧 E-SKY TOEIC</h2><small>Hệ thống luyện thi</small><nav style="margin-top:30px"><Link href="/dashboard">▦ Tổng quan</Link><Link v-if="user?.role==='admin'" href="/users">♙ Tài khoản</Link><Link v-if="['admin','teacher'].includes(user?.role)" href="/questions">▤ Ngân hàng câu hỏi</Link><Link v-if="['admin','teacher'].includes(user?.role)" href="/exams">▣ Quản lý đề thi</Link></nav></aside><main class="main"><div class="top"><div><strong>{{user?.name}}</strong><div class="muted">{{user?.email}} · {{user?.role}}</div></div><button class="secondary" @click="logout">Đăng xuất</button></div><div v-if="page.props.flash?.success" class="notice">{{page.props.flash.success}}</div><slot/></main></div>
</template>
