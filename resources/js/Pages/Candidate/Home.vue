<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, onUnmounted } from 'vue';
import { useExamContext } from '../../exam-context';
import ConfirmAction from '../../Components/ConfirmAction.vue';
import CandidateLayout from '../../Layouts/CandidateLayout.vue';
const props = defineProps({ accesses: Array, attempts: Array, today: String, server_time: String });
const offset = Date.parse(props.server_time) - Date.now();
const {context,candidateBase}=useExamContext(); const action=ref(null);
const clock = ref(Date.now() + offset);
const timer = setInterval(() => clock.value = Date.now() + offset, 1000);
onUnmounted(() => clearInterval(timer));
const start = useForm({});
const active = access => props.attempts.find(attempt => attempt.exam_access_id === access.id && !attempt.submitted_at && Date.parse(attempt.expires_at) > clock.value);
function available(access) { return access.is_active && access.exam.status === 'published' && clock.value >= Date.parse(access.opens_at) && clock.value < Date.parse(access.closes_at); }
function status(access) { if (!access.is_active) return 'Quyền thi đã thu hồi'; if (access.exam.status !== 'published') return 'Đề thi tạm đóng'; if (clock.value < Date.parse(access.opens_at)) return 'Chưa đến giờ thi'; if (clock.value >= Date.parse(access.closes_at)) return 'Đã hết thời gian thi'; if (active(access)) return 'Đang làm bài'; if (access.attempts_count >= access.max_attempts) return 'Đã hết lượt'; return 'Sẵn sàng'; }
function begin(access) { if(active(access))return;action.value={method:'post',url:candidateBase.value+'/accesses/'+access.id+'/start',title:'Bắt đầu lượt thi mới',message:'Bắt đầu bài mới sẽ tính một lượt thi. Bạn đã sẵn sàng?',button:'Bắt đầu thi'}; }
const date = value => new Intl.DateTimeFormat('vi-VN', { timeZone: 'Asia/Bangkok' }).format(new Date(value));
</script>
<template><Head title="Cổng thi" /><CandidateLayout><div class="page-heading"><div><div class="eyebrow">KHÔNG GIAN THÍ SINH</div><h1>Bài thi {{context.name}} của bạn</h1><p>Kiểm tra ngày, ca và số lượt trước khi bắt đầu.</p></div><span class="pill">{{ date(today) }}</span></div><div class="notice">Mỗi bài mới tính 1 lượt. Bạn có thể mở lại bài đang làm trong thời gian còn lại mà không mất thêm lượt. Hệ thống tự lưu đáp án khi bạn chọn.</div>
 <div v-if="!accesses.length" class="card empty-state"><h2>Bạn chưa được cấp quyền thi</h2><p>Liên hệ quản trị viên để được cấp đề thi, ngày/ca thi và số lượt.</p></div>
 <div class="candidate-exams"><article v-for="access in accesses" :key="access.id" class="card"><div class="panel-title"><span class="pill">{{ access.access_mode === 'session' ? access.session_name : 'Thi theo ngày' }}</span><span class="muted">{{ access.exam.duration_minutes }} phút</span></div><h2 class="candidate-exam-title">{{ access.exam.title }}</h2><p class="muted">{{ access.exam.description || 'Bài thi được cấp riêng' }}</p><div class="info-row"><span>Ngày thi</span><strong>{{ date(access.exam_date) }}</strong></div><div v-if="access.access_mode === 'session'" class="info-row"><span>Khung giờ</span><strong>{{ access.start_time.slice(0,5) }}–{{ access.end_time.slice(0,5) }}</strong></div><div class="info-row"><span>Lượt đã dùng / tối đa</span><strong>{{ access.attempts_count }} / {{ access.max_attempts }}</strong></div><div class="info-row"><span>Trạng thái</span><span class="pill">{{ status(access) }}</span></div><div class="section-gap"><Link v-if="available(access) && active(access)" :href="`${candidateBase}/attempts/${active(access).id}`" class="btn">Tiếp tục bài đang làm →</Link><button v-else :disabled="start.processing || !available(access) || access.attempts_count >= access.max_attempts" @click="begin(access)">Bắt đầu thi</button></div></article></div>
 <section v-if="attempts.length" class="card section-gap"><h2>Lịch sử lượt thi</h2><div class="tablewrap"><table><thead><tr><th>Đề thi</th><th>Lượt</th><th>Ngày bắt đầu</th><th>Trạng thái</th><th>Kết quả</th></tr></thead><tbody><tr v-for="attempt in attempts" :key="attempt.id"><td>{{ accesses.find(access => access.id === attempt.exam_access_id)?.exam.title }}</td><td>{{ attempt.attempt_number }}</td><td>{{ date(attempt.started_at) }}</td><td>{{ attempt.submitted_at ? 'Đã kết thúc' : Date.parse(attempt.expires_at) <= clock ? 'Hết thời gian' : 'Đang làm' }}</td><td><Link :href="`${candidateBase}/attempts/${attempt.id}`" class="text-link">{{ attempt.submitted_at ? (attempt.graded_at ? (attempt.total_points+' điểm') : 'Chờ chấm') : 'Xem bài thi →' }}</Link></td></tr></tbody></table></div><p class="info-copy">Trắc nghiệm được chấm tự động. Bài tự luận và bài nói sẽ có điểm sau khi giáo viên chấm.</p></section>
<ConfirmAction :action="action" @close="action=null"/></CandidateLayout></template>
