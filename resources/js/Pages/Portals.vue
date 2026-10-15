<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppIcon from '../Components/AppIcon.vue';
import BrandIdentity from '../Components/BrandIdentity.vue';
const page = usePage();
const details = {
 toeic: { icon: 'headphones', eyebrow: 'TIẾNG ANH QUỐC TẾ', headline: 'Chinh phục mục tiêu TOEIC', tags: ['Listening', 'Reading'], note: 'Bài thi chia theo Part 1–7.', number: '01' },
 vstep: { icon: 'graduation', eyebrow: 'NĂNG LỰC NGOẠI NGỮ', headline: 'Tự tin với cả bốn kỹ năng', tags: ['Nghe', 'Đọc', 'Viết', 'Nói'], note: 'Không gian thi bốn kỹ năng.', number: '02' },
 course: { icon: 'book', eyebrow: 'KHẢO THÍ HỌC PHẦN', headline: 'Hoàn thành chặng đường học tập', tags: ['Trắc nghiệm', 'Tự luận'], note: 'Đề thi theo từng môn học.', number: '03' },
};
</script>
<template>
 <Head title="Chào mừng đến cổng thi" />
 <div class="student-welcome">
  <div class="welcome-ambient" aria-hidden="true"><i/><i/><i/></div>
  <header class="student-public-header"><Link href="/exam/login" class="brand"><BrandIdentity/></Link><div class="student-public-links"><a aria-label="Liên hệ hỗ trợ thí sinh" v-if="page.props.branding.support_email" :href="'mailto:'+page.props.branding.support_email"><AppIcon name="mail" :size="16"/><span>Hỗ trợ thí sinh</span></a><a href="/admin/login" class="staff-entry" aria-label="Cổng quản trị"><AppIcon name="users" :size="16"/><span>Cổng quản trị</span><AppIcon name="arrow" :size="15"/></a></div></header>
  <main class="student-landing-main">
   <section class="student-hero" aria-labelledby="welcome-heading"><div class="student-hero-copy"><div class="welcome-kicker"><span class="welcome-live-dot"/> KHÔNG GIAN KHẢO THÍ TRỰC TUYẾN</div><h1 id="welcome-heading">Sẵn sàng cho<br/><span>bước tiến tiếp theo?</span></h1><p>Chọn phần thi của bạn, đăng nhập và bắt đầu hành trình. Một không gian rõ ràng để bạn tập trung thể hiện năng lực.</p><a href="#exam-options" class="student-main-cta">Chọn phần thi <AppIcon name="arrow" :size="19"/></a><div class="welcome-trust"><span><AppIcon name="shield" :size="16"/>Tài khoản riêng từng phần thi</span><span><AppIcon name="check" :size="16"/>Tự lưu bài làm</span></div></div>
    <div class="student-hero-art" aria-hidden="true"><div class="hero-orbit orbit-one"/><div class="hero-orbit orbit-two"/><div class="hero-art-dots"/><div class="exam-preview-ticket"><div class="ticket-top"><span class="ticket-mini-logo"><AppIcon name="graduation" :size="23"/></span><span>HÀNH TRÌNH CỦA BẠN</span><AppIcon name="sparkles" :size="19"/></div><div class="ticket-emblem"><AppIcon name="check" :size="37"/></div><strong>Sẵn sàng vào thi</strong><p>Tự tin thể hiện điều bạn đã học.</p><div class="ticket-progress"><i/><i/><i/></div><div class="ticket-footer"><span>Chuẩn bị</span><span>Tự tin</span><span>Bắt đầu</span></div></div><div class="hero-floating-label floating-top"><span><AppIcon name="headphones" :size="21"/></span><div><strong>TOEIC & VSTEP</strong><small>Chinh phục ngoại ngữ</small></div></div><div class="hero-floating-label floating-bottom"><span><AppIcon name="book" :size="20"/></span><div><strong>Thi kết thúc môn</strong><small>Sẵn sàng cho kỳ thi của bạn</small></div><AppIcon name="check" :size="17"/></div></div>
   </section>
   <section id="exam-options" class="student-exam-options" aria-labelledby="options-heading"><div class="student-section-heading"><div><div class="eyebrow">BẮT ĐẦU TẠI ĐÂY</div><h2 id="options-heading">Bạn muốn tham gia phần thi nào?</h2></div><span>Mỗi phần thi sử dụng tài khoản được cấp riêng.</span></div><div class="student-module-grid"><Link v-for="(module,index) in page.props.modules" :key="module.slug" :href="`/exam/${module.slug}/login`" class="student-module-card" :class="'module-'+module.slug" :style="{'--card-order':index}"><div class="module-card-top"><span class="module-symbol"><AppIcon :name="details[module.slug].icon" :size="30"/></span><span class="module-number">{{details[module.slug].number}}</span></div><div class="module-eyebrow">{{details[module.slug].eyebrow}}</div><h3>{{module.name}}</h3><p>{{details[module.slug].headline}}</p><div class="module-tags"><span v-for="tag in details[module.slug].tags" :key="tag">{{tag}}</span></div><small>{{details[module.slug].note}}</small><div class="module-card-action"><span>Đăng nhập thi</span><span class="module-action-icon"><AppIcon name="arrow" :size="19"/></span></div></Link></div></section>
   <section class="student-start-guide" aria-label="Các bước tham gia thi"><div v-for="(step,index) in [{title:'Chọn phần thi',text:'TOEIC, VSTEP hoặc môn học.'},{title:'Đăng nhập tài khoản',text:'Dùng tài khoản được cấp cho phần thi.'},{title:'Kiểm tra và bắt đầu',text:'Xem ngày, ca thi và số lượt của bạn.'}]" :key="step.title"><span>{{index+1}}</span><p><strong>{{step.title}}</strong><small>{{step.text}}</small></p><AppIcon v-if="index<2" name="arrow" :size="18"/></div></section>
  </main>
  <footer class="student-public-footer"><span>{{page.props.branding.footer_text}}</span><span>{{page.props.branding.footer_note}}</span></footer>
 </div>
</template>
