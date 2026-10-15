<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AppIcon from '../Components/AppIcon.vue';
import BrandIdentity from '../Components/BrandIdentity.vue';
const page = usePage();
const props = defineProps({ portal: { type: String, default: 'exam' } });
const context = computed(() => usePage().props.context);
const isAdmin = computed(() => props.portal === 'admin');
const loginUrl = computed(() => isAdmin.value ? '/admin/login' : '/exam/'+context.value.slug+'/login');
const form = useForm({ email: '', password: '' });
const showPassword = ref(false);
const submit = () => form.post(loginUrl.value, { onFinish: () => form.reset('password') });
</script>
<template>
 <Head :title="isAdmin ? 'Đăng nhập quản trị' : 'Đăng nhập thi'" />
 <div class="auth-shell">
  <aside class="auth-story"><a :href="loginUrl" class="brand"><BrandIdentity :subtitle="isAdmin?'CỔNG QUẢN TRỊ':'CỔNG THI '+context.name.toUpperCase()"/></a>
   <div class="auth-story-copy"><div class="eyebrow">SẴN SÀNG CHO HÀNH TRÌNH MỚI</div><h1 class="brand-login-heading">{{page.props.branding.login_heading}}</h1><p>{{page.props.branding.login_description}}</p><div class="auth-steps"><span>Học liệu</span><AppIcon name="arrow" :size="15" /><span>Luyện tập</span><AppIcon name="arrow" :size="15" /><span>Tiến bộ</span></div></div>
   <small class="auth-organization">{{page.props.branding.organization_name||page.props.branding.software_name}}</small>
  </aside>
  <main class="auth-entry"><div class="auth-form-wrap"><span class="portal-label">{{ isAdmin ? 'KHÔNG GIAN QUẢN TRỊ' : 'PHẦN THI '+context.name.toUpperCase() }}</span><div class="eyebrow">CHÀO MỪNG TRỞ LẠI</div><h2>{{ isAdmin ? 'Đăng nhập quản trị' : 'Đăng nhập thi '+context.name }}</h2><p class="muted">{{ isAdmin ? 'Dành cho quản trị viên và giáo viên quản lý kỳ thi.' : 'Dùng tài khoản thi được cấp theo ngày hoặc ca thi.' }}</p>
   <form @submit.prevent="submit" class="auth-login-form"><div class="field"><label for="login-email">Email</label><input id="login-email" v-model="form.email" type="email" autocomplete="username" required autofocus placeholder="Email của bạn" :aria-invalid="!!form.errors.email" aria-describedby="email-error" /><div v-if="form.errors.email" id="email-error" class="error" role="alert">{{ form.errors.email }}</div></div>
    <div class="field"><label for="login-password">Mật khẩu</label><div class="password-field"><input id="login-password" v-model="form.password" :type="showPassword ? 'text' : 'password'" autocomplete="current-password" required placeholder="Nhập mật khẩu" :aria-invalid="!!form.errors.password" aria-describedby="password-error" /><button type="button" class="password-toggle" @click="showPassword = !showPassword" :aria-pressed="showPassword">{{ showPassword ? 'Ẩn' : 'Hiện' }}</button></div><div v-if="form.errors.password" id="password-error" class="error" role="alert">{{ form.errors.password }}</div></div>
    <button class="auth-submit" type="submit" :disabled="form.processing"><span>{{ form.processing ? 'Đang đăng nhập…' : 'Đăng nhập' }}</span><AppIcon name="arrow" :size="18" /></button>
   </form><div v-if="!isAdmin" class="portal-switch"><a href="/exam/login">← Chọn phần thi khác</a></div><div class="portal-switch"><a :href="isAdmin ? '/exam/login' : '/admin/login'">{{ isAdmin ? 'Đến cổng đăng nhập thi' : 'Đến cổng quản trị' }} <AppIcon name="arrow" :size="15" /></a></div><p class="auth-support">Cần tài khoản hoặc đặt lại mật khẩu?<br /><a v-if="page.props.branding.support_email" :href="'mailto:'+page.props.branding.support_email">{{page.props.branding.support_email}}</a><span v-else>Liên hệ quản trị viên để được hỗ trợ.</span></p>
  </div></main>
 </div><footer class="login-footer">{{page.props.branding.footer_text}}<span v-if="page.props.branding.footer_note"> · {{page.props.branding.footer_note}}</span></footer>
</template>
