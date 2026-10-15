<script setup>
import { usePage, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch, onMounted, onUnmounted } from 'vue';
import AppIcon from '../Components/AppIcon.vue';
import BrandIdentity from '../Components/BrandIdentity.vue';
const page = usePage();
const user = computed(() => page.props.auth?.user);
const context = computed(() => page.props.context);
const path = computed(() => page.url.split('?')[0]);
const labels = { dashboard:'Tổng quan',users:'Tài khoản',questions:'Ngân hàng câu hỏi',exams:'Đề thi','exam-accesses':'Tài khoản & cấp quyền thi',subjects:'Danh mục môn học',results:'Bài làm & kết quả',settings:'Cấu hình phần mềm & giao diện' };
const title = computed(() => labels[path.value.split('/').at(-1)] || 'Quản trị khảo thí');
const mobileOpen = ref(false), collapsed = ref(false), closedGroups = ref([]);
const isMobile = ref(window.matchMedia('(max-width:900px)').matches);
const modules = computed(() => page.props.modules.filter(module => user.value?.role === 'admin' || (user.value?.managed_tracks || ['toeic']).includes(module.slug)));
const groups = computed(() => [
 {id:'system',label:'QUẢN TRỊ CHUNG',links:[{href:'/dashboard',label:'Tổng quan',icon:'dashboard'},...(user.value?.role==='admin'?[{href:'/users',label:'Tài khoản quản trị',icon:'users'},{href:'/settings',label:'Cấu hình phần mềm',icon:'settings'}]:[])]},
 ...modules.value.map(module=>({id:module.slug,label:module.name.toUpperCase(),links:[
  ...(module.slug==='course'?[{href:'/manage/course/subjects',label:'Danh mục môn học',icon:'book'}]:[]),
  {href:`/manage/${module.slug}/questions`,label:'Ngân hàng câu hỏi',icon:'book'},
  {href:`/manage/${module.slug}/exams`,label:'Đề thi',icon:'file'},
  ...(user.value?.role==='admin'?[{href:`/manage/${module.slug}/users`,label:'Tài khoản thi',icon:'users'},{href:`/manage/${module.slug}/exam-accesses`,label:'Ngày / ca & lượt thi',icon:'check'}]:[]),
  {href:`/manage/${module.slug}/results`,label:'Bài làm & kết quả',icon:'file'},
 ]})),
]);
const date = new Intl.DateTimeFormat('vi-VN',{timeZone:'Asia/Bangkok'}).format(new Date());
function saveMenu(){try{localStorage.setItem(`exam-menu-${user.value.id}`,JSON.stringify({collapsed:collapsed.value,groups:closedGroups.value}));}catch{}}
function toggleMenu(){if(isMobile.value)mobileOpen.value=!mobileOpen.value;else{collapsed.value=!collapsed.value;saveMenu();}}
function toggleGroup(id){closedGroups.value=closedGroups.value.includes(id)?closedGroups.value.filter(item=>item!==id):[...closedGroups.value,id];saveMenu();}
function resize(){isMobile.value=window.matchMedia('(max-width:900px)').matches;if(!isMobile.value)mobileOpen.value=false;}
function escape(event){if(event.key==='Escape')mobileOpen.value=false;}
watch(()=>page.url,()=>mobileOpen.value=false);
onMounted(()=>{try{const saved=JSON.parse(localStorage.getItem(`exam-menu-${user.value.id}`)||'{}');collapsed.value=saved.collapsed===true;closedGroups.value=Array.isArray(saved.groups)?saved.groups:modules.value.map(module=>module.slug).filter(slug=>!path.value.startsWith('/manage/'+slug));}catch{}window.addEventListener('resize',resize);window.addEventListener('keydown',escape);});
onUnmounted(()=>{window.removeEventListener('resize',resize);window.removeEventListener('keydown',escape);});
</script>
<template><a href="#main-content" class="skip-link">Đến nội dung chính</a><div class="shell" :class="{'menu-collapsed':collapsed,'menu-open':mobileOpen}"><button v-if="mobileOpen" class="sidebar-overlay" aria-label="Đóng menu" @click="mobileOpen=false"/><aside class="sidebar" id="sidebar"><Link href="/dashboard" class="brand"><BrandIdentity/></Link><div class="workspace"><span class="workspace-icon"><AppIcon name="book"/></span><div><strong>Không gian quản trị</strong><small>TOEIC · VSTEP · Môn học</small></div></div><nav aria-label="Điều hướng chính"><section v-for="group in groups" :key="group.id" class="nav-group"><button class="nav-group-toggle" @click="toggleGroup(group.id)" :aria-expanded="!closedGroups.includes(group.id)" :aria-controls="`group-${group.id}`"><span>{{group.label}}</span><AppIcon name="chevron" :size="14" :class="{rotated:closedGroups.includes(group.id)}"/></button><div :id="`group-${group.id}`" v-show="!closedGroups.includes(group.id)"><Link v-for="link in group.links" :key="link.href" :href="link.href" class="nav-link" :class="{active:path===link.href}" :aria-current="path===link.href?'page':undefined"><AppIcon :name="link.icon" :size="18"/><span>{{link.label}}</span></Link></div></section></nav><div class="sidebar-bottom"><a class="nav-link" href="/exam/login" target="_blank" rel="noopener"><AppIcon name="arrow"/>Mở cổng thí sinh</a><small>{{page.props.branding.organization_name||page.props.branding.software_name}}</small></div></aside><div class="main-shell"><header class="topbar"><div class="topbar-left"><button class="icon-button" @click="toggleMenu" :aria-expanded="isMobile?mobileOpen:!collapsed" aria-controls="sidebar" aria-label="Mở hoặc ẩn menu"><AppIcon name="menu"/></button><div class="topbar-heading"><strong>{{title}}</strong><nav class="header-breadcrumb" aria-label="Đường dẫn"><Link href="/dashboard">Tổng quan</Link><span>›</span><span v-if="path.startsWith('/manage/')">{{context.name}} · </span><span>{{title}}</span></nav></div></div><div class="topbar-right"><span class="today">{{date}}</span><details class="profile-menu"><summary><span class="avatar">{{user?.name?.slice(0,1)}}</span><span class="profile-name"><strong>{{user?.name}}</strong><small>{{user?.role==='admin'?'Quản trị viên':'Giáo viên'}}</small></span><AppIcon name="chevron" :size="14"/></summary><div class="profile-dropdown"><span class="profile-email">{{user?.email}}</span><button @click="router.post('/logout')"><AppIcon name="logout" :size="16"/>Đăng xuất</button></div></details></div></header><main class="content" id="main-content"><span v-if="path.startsWith('/manage/')" class="module-badge">{{context.name}}</span><div v-if="page.props.flash?.success" class="notice success" role="status">{{page.props.flash.success}}</div><slot/><footer class="page-footer"><span>{{page.props.branding.footer_text}}</span><span>{{page.props.branding.footer_note}}</span></footer></main></div></div></template>
