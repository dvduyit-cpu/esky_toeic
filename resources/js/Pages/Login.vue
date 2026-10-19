<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
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
 <div class="auth-shell" :class="{'student-auth':!isAdmin}">
  <aside class="auth-story"><div v-if="!isAdmin" class="login-ambient" aria-hidden="true"><i/><i/><span/></div><a :href="loginUrl" class="brand"><BrandIdentity :subtitle="isAdmin?'CỔNG QUẢN TRỊ':'CỔNG THI '+context.name.toUpperCase()"/></a>
   <div class="auth-story-copy"><span v-if="!isAdmin" class="student-login-module"><AppIcon :name="context.slug==='course'?'book':'graduation'" :size="20"/>{{context.name}}</span><div class="eyebrow">SẴN SÀNG CHO HÀNH TRÌNH MỚI</div><h1 class="brand-login-heading">{{page.props.branding.login_heading}}</h1><p>{{page.props.branding.login_description}}</p><div class="auth-steps"><span>Học liệu</span><AppIcon name="arrow" :size="15" /><span>Luyện tập</span><AppIcon name="arrow" :size="15" /><span>Tiến bộ</span></div></div>
   <small class="auth-organization">{{page.props.branding.organization_name||page.props.branding.software_name}}</small>
  </aside>
  <main class="auth-entry"><div class="auth-form-wrap"><Link v-if="!isAdmin" href="/exam/login" class="student-login-back"><AppIcon name="arrow" :size="16"/>Chọn phần thi khác</Link><span class="portal-label"><AppIcon v-if="!isAdmin" name="shield" :size="14"/>{{ isAdmin ? 'KHÔNG GIAN QUẢN TRỊ' : 'PHẦN THI '+context.name.toUpperCase() }}</span><div class="eyebrow">CHÀO MỪNG TRỞ LẠI</div><h2>{{ isAdmin ? 'Đăng nhập quản trị' : 'Đăng nhập thi '+context.name }}</h2><p class="muted">{{ isAdmin ? 'Dành cho quản trị viên và giáo viên quản lý kỳ thi.' : 'Dùng tài khoản thi được cấp theo ngày hoặc ca thi.' }}</p>
   <form @submit.prevent="submit" class="auth-login-form"><div class="field"><label for="login-email">{{isAdmin?'Email':'Tên đăng nhập'}}</label><input id="login-email" v-model="form.email" :type="isAdmin?'email':'text'" autocomplete="username" required autofocus :placeholder="isAdmin?'Email của bạn':'Nhập tài khoản được cấp'" :aria-invalid="!!form.errors.email" aria-describedby="email-error" /><div v-if="form.errors.email" id="email-error" class="error" role="alert">{{ form.errors.email }}</div></div>
    <div class="field"><label for="login-password">Mật khẩu</label><div class="password-field"><input id="login-password" v-model="form.password" :type="showPassword ? 'text' : 'password'" autocomplete="current-password" required placeholder="Nhập mật khẩu" :aria-invalid="!!form.errors.password" aria-describedby="password-error" /><button type="button" class="password-toggle" @click="showPassword = !showPassword" :aria-pressed="showPassword">{{ showPassword ? 'Ẩn' : 'Hiện' }}</button></div><div v-if="form.errors.password" id="password-error" class="error" role="alert">{{ form.errors.password }}</div></div>
    <button class="auth-submit" type="submit" :disabled="form.processing"><span>{{ form.processing ? 'Đang đăng nhập…' : isAdmin ? 'Đăng nhập' : 'Đăng nhập vào thi' }}</span><AppIcon name="arrow" :size="18" /></button>
   </form><div class="portal-switch"><a :href="isAdmin ? '/exam/login' : '/admin/login'">{{ isAdmin ? 'Đến cổng đăng nhập thi' : 'Đến cổng quản trị' }} <AppIcon name="arrow" :size="15" /></a></div><p class="auth-support">Cần tài khoản hoặc đặt lại mật khẩu?<br /><a v-if="page.props.branding.support_email" :href="'mailto:'+page.props.branding.support_email">{{page.props.branding.support_email}}</a><span v-else>Liên hệ quản trị viên để được hỗ trợ.</span></p>
  </div></main>
 </div><footer class="login-footer">{{page.props.branding.footer_text}}<span v-if="page.props.branding.footer_note"> · {{page.props.branding.footer_note}}</span></footer>
</template>
