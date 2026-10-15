<script setup>
import { Head, useForm } from '@inertiajs/vue3';
const form=useForm({email:'',password:''});
const submit=()=>form.post('/login',{onFinish:()=>form.reset('password')});
</script>
<template><Head title="Đăng nhập"/><div class="auth card"><h1>🎧 E-SKY TOEIC</h1><p class="muted">Đăng nhập hệ thống luyện thi Listening & Reading</p><form @submit.prevent="submit"><div class="field"><label>Email</label><input v-model="form.email" type="email" autocomplete="username" required/><div class="error">{{form.errors.email}}</div></div><div class="field"><label>Mật khẩu</label><input v-model="form.password" type="password" autocomplete="current-password" required/><div class="error">{{form.errors.password}}</div></div><button type="submit" :disabled="form.processing" style="width:100%">{{form.processing?'Đang đăng nhập...':'Đăng nhập'}}</button></form><p class="muted" style="font-size:13px">Liên hệ quản trị viên nếu chưa có tài khoản.</p></div></template>
