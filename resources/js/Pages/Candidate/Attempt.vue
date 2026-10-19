<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch, onMounted, onUnmounted, nextTick } from 'vue';
import axios from 'axios';
import { useExamContext } from '../../exam-context';
import AppModal from '../../Components/AppModal.vue';
import RecordingAnswer from '../../Components/RecordingAnswer.vue';
import CandidateLayout from '../../Layouts/CandidateLayout.vue';
import ExamGuard from '../../Components/ExamGuard.vue';
import BrandIdentity from '../../Components/BrandIdentity.vue';
import ToeicScoreSummary from '../../Components/ToeicScoreSummary.vue';
const props = defineProps({ attempt: Object, exam: Object, questions: Array, server_time: String });
const {context,candidateBase,page}=useExamContext(); const submitOpen=ref(false),recordingBusy=ref({}); const hasPendingRecording=computed(()=>Object.values(recordingBusy.value).some(Boolean));
const examHeader = ref(null);
const headerHeight = ref(100);
const partTabs = ref(null), partsHeight = ref(88);
let headerObserver;
onMounted(() => {
 if (!examHeader.value) return;
 const measure = () => { headerHeight.value = Math.ceil(examHeader.value?.getBoundingClientRect().height || 100); partsHeight.value = Math.ceil(partTabs.value?.getBoundingClientRect().height || 88); };
 measure();
 headerObserver = new ResizeObserver(measure);
 headerObserver.observe(examHeader.value);
 if (partTabs.value) headerObserver.observe(partTabs.value);
});
onUnmounted(() => headerObserver?.disconnect());
const answers = ref({ ...(props.attempt.answers || {}) });
const partNames = ['Mô tả tranh', 'Hỏi và đáp', 'Hội thoại', 'Bài nói ngắn', 'Hoàn thành câu', 'Hoàn thành đoạn văn', 'Đọc hiểu'];
const numberedQuestions = computed(() => props.questions.map((question, index) => ({ ...question, number: index + 1 })));
const sections = computed(() => context.value.slug === 'toeic'
 ? partNames.map((name, index) => ({ key: String(index + 1), label: `Part ${index + 1}`, name, questions: numberedQuestions.value.filter(question => Number(question.part) === index + 1) }))
 : [...new Set(props.questions.map(question => question.skill || 'general'))].map(skill => ({ key: skill, label: ({ listening: 'Nghe', reading: 'Đọc', writing: 'Viết', speaking: 'Nói' })[skill] || 'Bài thi', name: 'Câu hỏi', questions: numberedQuestions.value.filter(question => (question.skill || 'general') === skill) })));
const activePart = ref(sections.value.find(section => section.questions.length)?.key || '1');
const activeSection = computed(() => sections.value.find(section => section.key === activePart.value));
const currentQuestion = ref(null);
const progress = computed(() => props.questions.length ? Math.round(count.value / props.questions.length * 100) : 0);
const sectionAnswered = section => section.questions.filter(question => answers.value[question.id]).length;
function questionGroups(section) {
 const groups = [];
 for (const question of section.questions) {
  const reading = context.value.slug === 'toeic' ? Number(question.part) >= 6 : question.skill === 'reading';
  const split = reading && !!(question.passage || mediaUrl(question.media_path));
  const key = split ? JSON.stringify([question.passage || '', question.media_path || '']) : `question-${question.id}`;
  const previous = groups[groups.length - 1];
  if (previous?.key === key) previous.questions.push(question);
  else groups.push({ key, split, passage: question.passage, media_path: question.media_path, questions: [question] });
 }
 return groups;
}
async function goToQuestion(section, question) {
 activePart.value = section.key;
 currentQuestion.value = question.id;
 await nextTick();
 const target = document.getElementById(`exam-question-${question.id}`);
 target?.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
 target?.focus({ preventScroll: true });
}
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
 <template v-if="attempt.submitted_at"><div class="card result-card"><span class="result-icon">✓</span><div class="eyebrow">LƯỢT THI ĐÃ KẾT THÚC</div><h1>{{ exam.title }}</h1><p class="muted">Lượt thi số {{ attempt.attempt_number }}</p><ToeicScoreSummary :listening="attempt.listening_result" :reading="attempt.reading_result" :total="attempt.toeic_total_score"/><div v-if="attempt.graded_at && attempt.toeic_total_score==null" class="result-score">{{ attempt.total_points }} <span>{{attempt.listening_result||attempt.reading_result?'điểm theo câu':'điểm'}}</span></div><h2 v-else class="section-gap">Đã nộp · Chờ giáo viên chấm</h2><p class="muted">Trắc nghiệm: {{attempt.correct_count}} câu đúng. {{attempt.listening_result||attempt.reading_result?'Tổng TOEIC chỉ hiển thị khi đề có đủ 100 câu Listening và 100 câu Reading.':'Điểm hiển thị theo cấu hình từng câu.'}}</p><Link :href="candidateBase" class="btn">Về danh sách bài thi</Link></div></template>
 <template v-else><ExamGuard :submit-error="error" @violation="submit(false)" :submitting="sending" :submit-url="candidateBase+'/attempts/'+attempt.id+'/submit'" @submit="submit(true)"><div class="exam-workspace" :style="{'--exam-header-height':headerHeight+'px','--exam-parts-height':partsHeight+'px'}">
  <header ref="examHeader" class="attempt-toolbar exam-fixed-header" aria-label="Thông tin thí sinh và bài thi">
   <div class="exam-candidate-name"><span>Họ và tên</span><strong>{{page.props.auth.user.name}}</strong></div>
   <div class="exam-candidate-number"><span>Số báo danh</span><strong>{{page.props.auth.user.email}}</strong></div>
   <div class="exam-header-part"><label for="header-part">Phần đang làm</label><select id="header-part" v-model="activePart"><option v-for="section in sections" :key="section.key" :value="section.key">{{section.label}}</option></select></div>
   <div class="exam-header-clock"><span>Thời gian còn lại</span><strong :class="{'time-warning':remaining<300}" role="timer">{{time}}</strong></div>
   <div class="exam-header-progress"><span>Đã trả lời</span><strong>{{count}} / {{questions.length}}</strong></div>
   <div class="exam-header-actions"><span class="save-status" role="status">{{saveStatus}}</span><div class="actions"><button class="secondary" :disabled="sending||remaining===0" @click="save">Lưu lại</button><button :disabled="sending||hasPendingRecording" @click="submit(true)">{{sending?'Đang nộp…':'Nộp bài'}}</button></div></div>
  </header>


  <div v-if="error" class="notice error-notice" role="alert">{{ error }}</div>
  <nav ref="partTabs" class="exam-part-tabs" aria-label="Các phần bài thi"><button v-for="section in sections" :key="section.key" type="button" :class="{ active: activePart === section.key }" :aria-pressed="activePart === section.key" @click="activePart = section.key"><strong>{{ section.label }}</strong><span v-if="context.slug!=='toeic'">{{ section.name }}</span><small>{{ section.questions.length }} câu</small></button></nav>
  <div class="exam-columns"><div class="exam-paper">
   <section v-for="section in sections" v-show="activePart === section.key" :key="section.key" :aria-label="section.label">
    <header class="exam-section-heading"><div><span class="eyebrow">{{ context.slug === 'toeic' ? (Number(section.key) <= 4 ? 'LISTENING · PHẦN NGHE' : 'READING · PHẦN ĐỌC') : 'NỘI DUNG BÀI THI' }}</span><h2>{{ section.label }}<template v-if="context.slug!=='toeic'"> · {{ section.name }}</template></h2></div><span>{{ sectionAnswered(section) }} / {{ section.questions.length }} Đã làm</span></header>
    <div v-if="!section.questions.length" class="exam-empty">Phần này chưa có câu hỏi trong đề thi.</div>
    <div v-for="group in questionGroups(section)" :key="group.questions[0].id" class="exam-question-group" :class="{ 'reading-split': group.split }">
     <aside v-if="group.split" class="reading-passage-panel" :aria-label="'Tư liệu cho câu '+group.questions[0].number+' đến '+group.questions[group.questions.length-1].number">
      <header class="reading-passage-heading"><strong>Bài đọc</strong><span>Câu {{group.questions[0].number}}–{{group.questions[group.questions.length-1].number}}</span></header>
      <div class="reading-passage-body"><div class="exam-watermark" aria-hidden="true"><div v-for="mark in 3" :key="mark"><BrandIdentity :subtitle="page.props.auth.user.name+' · #'+attempt.id"/></div></div><p v-if="group.passage" class="passage">{{group.passage}}</p><template v-if="mediaUrl(group.media_path)"><audio v-if="/\.(mp3|wav|ogg)$/i.test(group.media_path)" :src="mediaUrl(group.media_path)" controls preload="metadata"/><img v-else :src="mediaUrl(group.media_path)" alt="Tư liệu bài đọc" class="question-image"/></template></div>
     </aside>
     <div class="reading-answer-panel">
  <fieldset v-for="question in group.questions" :key="question.id"  :id="`exam-question-${question.id}`" tabindex="-1" class="card question-card" :class="{ 'question-current': currentQuestion === question.id }" :disabled="sending || remaining === 0"><legend>Câu {{ question.number }} · {{context.slug==='toeic'?'Part '+question.part:({listening:'Nghe',reading:'Đọc',writing:'Viết',speaking:'Nói'})[question.skill]||'Bài thi môn học'}}</legend><div class="exam-watermark" aria-hidden="true"><div v-for="mark in 3" :key="mark"><BrandIdentity :subtitle="page.props.auth.user.name+' · #'+attempt.id"/></div></div><p v-if="!group.split && question.passage" class="passage">{{ question.passage }}</p><template v-if="!group.split && mediaUrl(question.media_path)"><audio v-if="/\.(mp3|wav|ogg)$/i.test(question.media_path)" :src="mediaUrl(question.media_path)" controls preload="metadata" /><img v-else :src="mediaUrl(question.media_path)" alt="Tư liệu câu hỏi" class="question-image" /></template><p class="question-content">{{ question.content }}</p><textarea v-if="question.kind==='essay'" v-model="answers[question.id]" class="question-response" rows="8" maxlength="20000" :aria-label="'Bài làm câu '+question.number" placeholder="Nhập bài viết / câu trả lời tự luận…"/><RecordingAnswer v-else-if="question.kind==='speaking'" :url="candidateBase+'/attempts/'+attempt.id+'/recordings/'+question.id" :saved="answers[question.id]==='recorded'" :disabled="sending||remaining===0" @saved="answers[question.id]='recorded'" @busy="value=>recordingBusy[question.id]=value"/><template v-else v-for="(option, key) in question.options" :key="key"><label v-if="option" class="answer-option" :class="{ selected: answers[question.id] === key }"><input v-model="answers[question.id]" type="radio" :name="`question-${question.id}`" :value="key" /><strong>{{ key }}.</strong><span>{{ option }}</span></label></template></fieldset></div></div>
   </section>
   <div class="exam-part-navigation"><button class="secondary" :disabled="sections.findIndex(section => section.key === activePart) === 0" @click="activePart = sections[sections.findIndex(section => section.key === activePart) - 1].key">← Phần trước</button><span>{{ activeSection?.label }}</span><button class="secondary" :disabled="sections.findIndex(section => section.key === activePart) === sections.length - 1" @click="activePart = sections[sections.findIndex(section => section.key === activePart) + 1].key">Phần tiếp →</button></div>
  </div>
  <aside class="exam-navigator" aria-label="Điều hướng câu hỏi"><div class="exam-nav-title"><h2>Phiếu trả lời</h2><strong>{{ progress }}%</strong></div><p>Đã làm <strong>{{ count }}</strong> / {{ questions.length }} câu</p><div class="exam-progress" role="progressbar" :aria-valuenow="count" :aria-valuemax="questions.length" :aria-valuemin="0" aria-label="Số câu đã trả lời"><span :style="{ width: progress + '%' }"></span></div><div class="exam-nav-legend"><span><i class="done"></i>Đã trả lời</span><span><i></i>Chưa trả lời</span></div>
   <div class="exam-nav-parts"><div v-for="section in sections" :key="section.key" class="exam-nav-part"><button class="exam-nav-part-title" :aria-pressed="activePart === section.key" @click="activePart = section.key"><strong>{{ section.label }}</strong><span>{{ sectionAnswered(section) }}/{{ section.questions.length }}</span></button><div class="exam-question-grid"><button v-for="question in section.questions" :key="question.id" :class="{ answered: !!answers[question.id], current: currentQuestion === question.id }" :aria-label="'Câu ' + question.number + (answers[question.id] ? ', Đã trả lời' : ', chưa trả lời')" :aria-current="currentQuestion === question.id ? 'location' : undefined" @click="goToQuestion(section, question)">{{ question.number }}</button></div></div></div>
   <button class="exam-submit" :disabled="sending || hasPendingRecording" @click="submit(true)">{{ sending ? 'Đang nộp…' : 'Nộp bài thi' }}</button>
  </aside></div>
  <div class="actions section-gap"><button :disabled="sending||hasPendingRecording" @click="submit(true)">Nộp bài và kết thúc lượt thi</button></div>
 </div></ExamGuard></template>
<AppModal :open="submitOpen" title="Nộp bài thi" :busy="sending" @close="submitOpen=false"><p>Bạn đã trả lời {{count}} / {{questions.length}} câu. Nộp bài sẽ kết thúc lượt thi này.</p><div class="modal-actions"><button class="secondary" @click="submitOpen=false">Tiếp tục làm bài</button><button :disabled="sending||hasPendingRecording" @click="submit(false)">Xác nhận nộp bài</button></div></AppModal></CandidateLayout></template>
