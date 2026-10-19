<?php

namespace App\Http\Controllers;

use App\Models\ExamAttempt;
use App\Support\ExamTrack;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ResultController extends Controller
{
    private function owner(ExamAttempt $attempt): void
    {
        abort_unless(auth()->user()->role === 'admin' || $attempt->access->exam->created_by === auth()->id(), 403);
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'exam_id' => ['nullable', \Illuminate\Validation\Rule::exists('exams', 'id')->where('exam_track', ExamTrack::current())],
            'exam_round_id' => ['nullable', \Illuminate\Validation\Rule::exists('exam_rounds', 'id')->where('exam_track', ExamTrack::current())],
        ]);
    }

    private function resultQuery(array $filters)
    {
        return ExamAttempt::whereHas('access.exam', fn ($q) => $q->where('exam_track', ExamTrack::current())->when(auth()->user()->role === 'teacher', fn ($q) => $q->where('created_by', auth()->id())))
            ->when($filters['exam_id'] ?? null, fn ($q, $id) => $q->whereHas('access', fn ($q) => $q->where('exam_id', $id)))
            ->when($filters['exam_round_id'] ?? null, fn ($q, $id) => $q->whereHas('access', fn ($q) => $q->where('exam_round_id', $id)))
            ->with('access.user:id,name,email', 'access.exam:id,title,exam_track', 'access.round:id,name')->latest();
    }

    public function index(Request $request)
    {
        $filters = $this->filters($request);
        $results = $this->resultQuery($filters)->paginate(15)->withQueryString()->through(fn ($attempt) => $attempt->only(['id', 'attempt_number', 'answers', 'submitted_at', 'correct_count', 'total_points', 'graded_at', 'manual_scores', 'listening_result', 'reading_result', 'toeic_total_score']) + ['student' => $attempt->access->user, 'exam' => $attempt->access->exam, 'questions' => $attempt->questions_snapshot]);

        return Inertia::render('Results', [
            'results' => $results, 'filters' => $filters,
            'exams' => \App\Models\Exam::where('exam_track', ExamTrack::current())->when(auth()->user()->role === 'teacher', fn ($q) => $q->where('created_by', auth()->id()))->orderBy('title')->get(['id', 'title']),
            'exam_rounds' => \App\Models\ExamRound::where('exam_track', ExamTrack::current())->whereHas('exam', fn ($q) => $q->when(auth()->user()->role === 'teacher', fn ($q) => $q->where('created_by', auth()->id())))->orderByDesc('exam_date')->get(['id', 'name']),
        ]);
    }

    public function export(Request $request)
    {
        $query = $this->resultQuery($this->filters($request));
        $rows = (function () use ($query) {
            $number = 0;
            foreach ($query->lazy(100) as $attempt) {
                yield [++$number, $attempt->access->user->name, $attempt->access->user->email,
                    $attempt->access->round?->name, $attempt->access->exam->title, $attempt->attempt_number,
                    $attempt->started_at?->timezone('Asia/Bangkok')->format('d/m/Y H:i:s'),
                    $attempt->submitted_at?->timezone('Asia/Bangkok')->format('d/m/Y H:i:s'),
                    !$attempt->submitted_at ? 'Đang làm' : ($attempt->graded_at ? 'Đã chấm' : 'Chờ chấm'),
                    $attempt->submitted_at ? $attempt->correct_count : null,
                    $attempt->graded_at ? $attempt->total_points : null,
                    $attempt->listening_result['correct'] ?? null, $attempt->listening_result['scaled'] ?? null,
                    $attempt->reading_result['correct'] ?? null, $attempt->reading_result['scaled'] ?? null,
                    $attempt->toeic_total_score];
            }
        })();
        return \App\Support\ExcelFiles::download('ket-qua-'.ExamTrack::current().'-'.now('Asia/Bangkok')->format('Ymd-His').'.xlsx',
            ['STT', 'Họ và tên', 'Số báo danh / Tên đăng nhập', 'Khóa thi', 'Đề thi', 'Lượt thi', 'Bắt đầu (UTC+7)', 'Nộp bài (UTC+7)', 'Trạng thái', 'Tổng câu đúng', 'Điểm theo câu', 'Câu nghe đúng', 'Listening /495', 'Câu đọc đúng', 'Reading /495', 'Tổng TOEIC /990'], $rows);
    }

    public function grade(Request $request, ExamAttempt $examAttempt)
    {
        $this->owner($examAttempt);
        abort_unless($examAttempt->submitted_at, 422);
        $data = $request->validate(['scores' => 'required|array', 'scores.*' => 'required|numeric|min:0']);
        $manual = collect($examAttempt->questions_snapshot)->filter(fn ($q) => ($q['kind'] ?? 'mcq') !== 'mcq')->keyBy('id');
        if (count($data['scores']) !== $manual->count()) {
            throw ValidationException::withMessages(['scores' => 'Cần chấm đủ các câu tự luận và bài nói.']);
        }
        foreach ($data['scores'] as $id => $score) {
            if (! $manual->has($id) || $score > (float) ($manual[$id]['max_points'] ?? 1)) {
                throw ValidationException::withMessages(['scores' => 'Điểm phải nằm trong điểm tối đa của từng câu.']);
            }
        }
        DB::transaction(function () use ($examAttempt, $data) {
            $attempt = ExamAttempt::whereKey($examAttempt->id)->lockForUpdate()->firstOrFail();
            $attempt->manual_scores = $data['scores'];
            $attempt->graded_by = auth()->id();
            $attempt->calculateScore();
        });

        return back()->with('success', 'Đã lưu điểm chấm bài.');
    }

    public function recording(ExamAttempt $examAttempt, string $questionId)
    {
        $this->owner($examAttempt);
        $path = $examAttempt->recordings[$questionId] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Content-Disposition' => 'inline']);
    }
}
