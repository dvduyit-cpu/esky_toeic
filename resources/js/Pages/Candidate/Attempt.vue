<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { useExamContext } from '../../exam-context';
import AppModal from '../../Components/AppModal.vue';
import RecordingAnswer from '../../Components/RecordingAnswer.vue';
import CandidateLayout from '../../Layouts/CandidateLayout.vue';
const props = defineProps({ attempt: Object, exam: Object, questions: Array, server_time: String });
const {context,candidateBase}=useExamContext(); const submitOpen=ref(false),recordingBusy=ref({}); const hasPendingRecording=computed(()=>Object.values(recordingBusy.value).some(Boolean));
const answers = ref({ ...(props.attempt.answers || {}) });
const offset = Date.parse(props.server_time) - Date.now();
const remaining = ref(Math.max(0, Math.ceil((Date.parse(props.attempt.expires_at) - Date.now() - offset) / 1000)));
const sending = ref(false);
const saveStatus = ref('Đã lưu');
const error = ref('');
const count = computed(() => Object.values(answers.value).filter(Boolean).length);
const time = computed(() => `${String(Math.floor(remaining.value / 60)).padStart(2,'0')}:${String(remaining.value % 60).padStart(2,'0')}`);
let timer, debounce, queue = Promise.resolve();
function save() {
 clearTimeout(debounce);
 if (props.attempt.submitted_at || remaining.value <= 0) return queue;
 const snapshot = { ...answers.value };
 saveStatus.value = 'Đang lưu…';
 queue = queue.then(async () => {
  saveStatus.value = 'Đang lưu…';
  try { await axios.put(`${candidateBase.value}/attempts/${props.attempt.id}/answers`, { answers: snapshot }, { timeout: 10000 }); saveStatus.value = JSON.stringify(snapshot) === JSON.stringify(answers.value) ? 'Đã lưu' : 'Chờ lưu…'; error.value = ''; }
  catch (failure) { saveStatus.value = 'Chưa lưu được'; error.value = failure.response?.status === 409 ? 'Bài thi đã hết giờ. Đang tải kết quả…' : 'Chưa lưu được đáp án. Kiểm tra kết nối và bấm Lưu lại.'; }
 });
 return queue;
}
watch(answers, () => { saveStatus.value = 'Chờ lưu…'; clearTimeout(debounce); debounce = setTimeout(save, 500); }, { deep: true });
async function submit(ask = true) {
 if (sending.value || props.attempt.submitted_at) return;
 if(ask){submitOpen.value=true;return;} submitOpen.value=false;
 sending.value = true;
 clearTimeout(debounce);
 await queue;
 router.post(`${candidateBase.value}/attempts/${props.attempt.id}/submit`, { answers: answers.value }, { onFinish: () => sending.value = false, onError: () => error.value = 'Chưa nộp được bài. Vui lòng kiểm tra kết nối và thử lại.' });
}
const mediaUrl = value => typeof value === 'string' && /^(?!.*\.\.)[a-zA-Z0-9_/-]+\.(mp3|wav|ogg|png|jpe?g|webp)$/i.test(value) ? '/storage/' + value : null;
function warnIfUnsaved(event) { if (!props.attempt.submitted_at && ['Chờ lưu…','Đang lưu…','Chưa lưu được'].includes(saveStatus.value)) { event.preventDefault(); event.returnValue = ''; } }
onMounted(() => { window.addEventListener('beforeunload', warnIfUnsaved); timer = setInterval(() => { remaining.value = Math.max(0, Math.ceil((Date.parse(props.attempt.expires_at) - Date.now() - offset) / 1000)); if (remaining.value === 0 && !props.attempt.submitted_at) submit(false); }, 1000); });
onUnmounted(() => { if (saveStatus.value === 'Chờ lưu…' && !props.attempt.submitted_at) save(); clearInterval(timer); clearTimeout(debounce); window.removeEventListener('beforeunload', warnIfUnsaved); });
</script>
<template><Head :title="exam.title" /><CandidateLayout>
 <template v-if="attempt.submitted_at"><div class="card result-card"><span class="result-icon">✓</span><div class="eyebrow">LƯỢT THI ĐÃ KẾT THÚC</div><h1>{{ exam.title }}</h1><p class="muted">Lượt thi số {{ attempt.attempt_number }}</p><div v-if="attempt.graded_at" class="result-score">{{ attempt.total_points }} <span>điểm</span></div><h2 v-else class="section-gap">Đã nộp · Chờ giáo viên chấm</h2><p class="muted">Trắc nghiệm: {{attempt.correct_count}} câu đúng. Điểm hiển thị theo cấu hình từng câu, chưa quy đổi chứng chỉ.</p><Link :href="candidateBase" class="btn">Về danh sách bài thi</Link></div></template>
 <template v-else><div class="page-heading"><div><div class="eyebrow">LƯỢT THI {{ attempt.attempt_number }}</div><h1>{{ exam.title }}</h1><p>Làm bài theo yêu cầu từng câu. Đáp án được tự động lưu.</p></div></div>
  <div class="attempt-toolbar"><div><strong :class="{ 'time-warning': remaining < 300 }">{{ time }}</strong><span>Thời gian còn lại</span></div><div><strong>{{ count }} / {{ questions.length }}</strong><span>Đã trả lời</span></div><span class="save-status" role="status">{{ saveStatus }}</span><button class="secondary" :disabled="sending || remaining === 0" @click="save">Lưu lại</button><button :disabled="sending||hasPendingRecording" @click="submit(true)">{{ sending ? 'Đang nộp…' : 'Nộp bài' }}</button></div>
  <div v-if="error" class="notice error-notice" role="alert">{{ error }}</div>
  <fieldset v-for="(question, index) in questions" :key="question.id" class="card question-card" :disabled="sending || remaining === 0"><legend>Câu {{ index + 1 }} · {{context.slug==='toeic'?'Part '+question.part:({listening:'Nghe',reading:'Đọc',writing:'Viết',speaking:'Nói'})[question.skill]||'Bài thi môn học'}}</legend><p v-if="question.passage" class="passage">{{ question.passage }}</p><template v-if="mediaUrl(question.media_path)"><audio v-if="/\.(mp3|wav|ogg)$/i.test(question.media_path)" :src="mediaUrl(question.media_path)" controls preload="metadata" /><img v-else :src="mediaUrl(question.media_path)" alt="Tư liệu câu hỏi" class="question-image" /></template><p class="question-content">{{ question.content }}</p><textarea v-if="question.kind==='essay'" v-model="answers[question.id]" class="question-response" rows="8" maxlength="20000" :aria-label="'Bài làm câu '+(index+1)" placeholder="Nhập bài viết / câu trả lời tự luận…"/><RecordingAnswer v-else-if="question.kind==='speaking'" :url="candidateBase+'/attempts/'+attempt.id+'/recordings/'+question.id" :saved="answers[question.id]==='recorded'" :disabled="sending||remaining===0" @saved="answers[question.id]='recorded'" @busy="value=>recordingBusy[question.id]=value"/><template v-else v-for="(option, key) in question.options" :key="key"><label v-if="option" class="answer-option" :class="{ selected: answers[question.id] === key }"><input v-model="answers[question.id]" type="radio" :name="`question-${question.id}`" :value="key" /><strong>{{ key }}.</strong><span>{{ option }}</span></label></template></fieldset>
  <div class="actions section-gap"><button :disabled="sending||hasPendingRecording" @click="submit(true)">Nộp bài và kết thúc lượt thi</button></div>
 </template>
<AppModal :open="submitOpen" title="Nộp bài thi" :busy="sending" @close="submitOpen=false"><p>Bạn đã trả lời {{count}} / {{questions.length}} câu. Nộp bài sẽ kết thúc lượt thi này.</p><div class="modal-actions"><button class="secondary" @click="submitOpen=false">Tiếp tục làm bài</button><button :disabled="sending||hasPendingRecording" @click="submit(false)">Xác nhận nộp bài</button></div></AppModal></CandidateLayout></template>
