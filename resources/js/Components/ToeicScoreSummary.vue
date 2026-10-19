<script setup>
defineProps({ listening: Object, reading: Object, total: Number });
</script>

<template>
 <div v-if="listening || reading" class="toeic-score-summary">
  <div v-if="total != null" class="toeic-total"><strong>Tổng điểm TOEIC</strong><div class="result-score">{{total}} <span>/ 990</span></div></div>
  <div class="toeic-section-scores">
   <div v-for="section in [{name:'Listening',result:listening},{name:'Reading',result:reading}].filter(section=>section.result)" :key="section.name" class="listening-score-card">
    <strong>{{section.name}}</strong><div v-if="section.result.scaled!=null" class="result-score">{{section.result.scaled}} <span>/ 495</span></div>
    <p>{{section.result.correct}} / {{section.result.total}} câu đúng</p><small v-if="section.result.scaled==null">Cần đủ 100 câu {{section.name}} để quy đổi.</small>
   </div>
  </div>
  <p class="muted">Quy đổi theo bảng điểm được cấu hình cho kỳ thi.</p>
 </div>
</template>
