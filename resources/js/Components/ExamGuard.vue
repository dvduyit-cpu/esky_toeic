<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({ submitting: Boolean, submitUrl: String, submitError: String });
const emit = defineEmits(['submit', 'violation']);
const started = ref(false), covered = ref(true), requesting = ref(false);
const violated = ref(false);
const leavePending = ref(false);
const reason = ref('Bấm vào bên dưới để vào chế độ làm bài tập trung.');
const fullscreenError = ref('');
const resumeButton = ref(null);
const fullscreenSupported = computed(() => !!document.fullscreenEnabled && !!document.documentElement.requestFullscreen);
let removeNavigationGuard;

function cover(message) {
 if (requesting.value) return;
 covered.value = true;
 reason.value = message;
 // Stop listening audio while its question is covered. Recording is left intact.
 document.querySelectorAll('.exam-workspace audio').forEach(audio => audio.pause());
 nextTick(() => resumeButton.value?.focus({ preventScroll: true }));
}
async function resume() {
 if (violated.value) return;
 requesting.value = true;
 fullscreenError.value = '';
 try {
  if (fullscreenSupported.value && !document.fullscreenElement) await document.documentElement.requestFullscreen();
  if (document.hidden) return;
  started.value = true;
  covered.value = false;
  leavePending.value = false;
 } catch {
  fullscreenError.value = 'Chưa bật được toàn màn hình. Hãy cho phép toàn màn hình rồi thử lại.';
 } finally { requesting.value = false; }
}
function violation(message) {
 if (!started.value || requesting.value || violated.value || props.submitting) return;
 violated.value = true;
 cover(message + ' Hệ thống đang tự động nộp bài với các đáp án hiện có.');
 emit('violation');
}
function requestLeave(message) {
 if (!started.value || requesting.value || violated.value || props.submitting || leavePending.value) return;
 leavePending.value = true;
 cover(message + ' Bạn có muốn nộp bài và kết thúc lượt thi không?');
}
function confirmLeave() {
 if (!leavePending.value) return;
 leavePending.value = false;
 violation('Bạn đã xác nhận kết thúc lượt thi.');
}
function visibility() { if (document.hidden) requestLeave('Bạn đã chuyển khỏi tab bài thi.'); }
function blur() { if (!leavePending.value && !violated.value) cover('Cửa sổ bài thi mất tiêu điểm. Bấm tiếp tục để trở lại làm bài.'); }
function fullscreen() { if (fullscreenSupported.value && !document.fullscreenElement) requestLeave('Bạn đã thoát toàn màn hình.'); }
function prevent(event) { event.preventDefault(); }
function keydown(event) {
 const key = event.key.toLowerCase();
 if (event.key === 'PrintScreen' || ((event.metaKey || event.ctrlKey) && event.shiftKey && ['s', '3', '4', '5'].includes(key))) {
  event.preventDefault();
  cover('Nội dung đã được che khi nhận phím chụp màn hình. Bấm tiếp tục để làm bài.');
 } else if ((event.ctrlKey || event.metaKey) && ['c', 'x', 'v', 'p', 's', 'a'].includes(key)) {
  event.preventDefault();
 }
}
function beforeUnload(event) { event.preventDefault(); event.returnValue = ''; }
onMounted(() => {
 document.body.classList.add('exam-session-active');
 window.addEventListener('blur', blur);
 window.addEventListener('beforeunload', beforeUnload);
 document.addEventListener('visibilitychange', visibility);
 document.addEventListener('fullscreenchange', fullscreen);
 document.addEventListener('keydown', keydown, true);
 for (const type of ['copy', 'cut', 'paste', 'contextmenu', 'dragstart']) document.addEventListener(type, prevent, true);
 removeNavigationGuard = router.on('before', event => {
  const visit = event.detail.visit;
  if (props.submitting && visit.method.toLowerCase() === 'post' && visit.url.pathname === props.submitUrl) return;
  requestLeave('Bạn đang yêu cầu rời trang bài thi.');
  return false;
 });
 nextTick(() => resumeButton.value?.focus());
});
onUnmounted(() => {
 document.body.classList.remove('exam-session-active');
 window.removeEventListener('blur', blur);
 window.removeEventListener('beforeunload', beforeUnload);
 document.removeEventListener('visibilitychange', visibility);
 document.removeEventListener('fullscreenchange', fullscreen);
 document.removeEventListener('keydown', keydown, true);
 for (const type of ['copy', 'cut', 'paste', 'contextmenu', 'dragstart']) document.removeEventListener(type, prevent, true);
 removeNavigationGuard?.();
 if (document.fullscreenElement) document.exitFullscreen().catch(() => {});
});
</script>

<template>
 <div class="exam-guard" :class="{ 'exam-covered': covered }">
  <div class="exam-protected-content" :inert="covered"><slot /></div>
  <div v-if="covered" class="exam-privacy-screen" role="dialog" aria-modal="true" aria-labelledby="exam-privacy-title" aria-describedby="exam-privacy-description">
   <div class="exam-privacy-card">
    <span class="exam-privacy-symbol" aria-hidden="true">▣</span>
    <div class="eyebrow">CHẾ ĐỘ LÀM BÀI TẬP TRUNG</div>
    <h1 id="exam-privacy-title">{{ violated ? 'Đang kết thúc bài thi' : leavePending ? 'Xác nhận rời bài thi' : started ? 'Bài thi đang được che' : 'Vào phòng thi' }}</h1>
    <p id="exam-privacy-description">{{ reason }}</p>
    <p class="exam-exit-warning" role="note"><strong>{{ leavePending ? 'Nhấn OK sẽ nộp các đáp án hiện có và kết thúc lượt thi. Nhấn Hủy để tiếp tục làm bài. Thời gian thi vẫn chạy trong lúc xác nhận.' : 'Khi yêu cầu rời bài thi, bạn sẽ được hỏi xác nhận. Chỉ khi nhấn OK, bài mới được nộp và kết thúc.' }}</strong></p>
    <p class="exam-privacy-note">Không sao chép, dán hoặc in nội dung. Chuyển tab hoặc thoát toàn màn hình sẽ che bài và yêu cầu xác nhận khi trở lại.</p>
    <p v-if="!fullscreenSupported" class="exam-privacy-note">Thiết bị này không hỗ trợ toàn màn hình. Chế độ che bài và hạn chế sao chép vẫn hoạt động.</p>
    <p v-if="fullscreenError" role="alert" class="error">{{ fullscreenError }}</p>
    <p v-if="violated && submitError" role="alert" class="error">{{ submitError }}</p>
    <template v-if="violated"><p v-if="submitting" role="status">Đang nộp bài, vui lòng chờ…</p><button v-else @click="emit('violation')">Thử nộp bài lại</button></template>
    <template v-else-if="leavePending"><button ref="resumeButton" class="secondary" :disabled="requesting || submitting" @click="resume">{{ requesting ? 'Đang mở…' : 'Hủy, tiếp tục làm bài' }}</button><button :disabled="requesting || submitting" @click="confirmLeave">OK, nộp bài và thoát</button></template>
    <template v-else><button ref="resumeButton" :disabled="requesting || submitting" @click="resume">{{ requesting ? 'Đang mở…' : started ? 'Trở lại làm bài' : 'Đã hiểu, vào làm bài' }}</button>
    <button class="secondary" :disabled="submitting" @click="emit('submit')">Nộp bài và kết thúc</button></template>
   </div>
  </div>
 </div>
</template>
