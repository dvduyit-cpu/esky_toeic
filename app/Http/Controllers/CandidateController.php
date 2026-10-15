<?php

namespace App\Http\Controllers;

use App\Models\ExamAccess;
use App\Models\ExamAttempt;
use App\Support\ExamTrack;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CandidateController extends Controller
{
    public function index(): Response
    {
        $accesses = ExamAccess::where('user_id', auth()->id())->whereHas('exam', fn ($q) => $q->where('exam_track', auth()->user()->exam_track))->with('exam:id,title,description,duration_minutes,status')->withCount('attempts')->latest('exam_date')->get();
        $attempts = ExamAttempt::whereIn('exam_access_id', $accesses->pluck('id'))->latest()->get();
        foreach ($attempts as $attempt) {
            if (! $attempt->submitted_at && $attempt->expires_at->isPast()) {
                DB::transaction(function () use ($attempt) {
                    ExamAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail()->finish();
                });
                $attempt->refresh();
            }
        }

        return Inertia::render('Candidate/Home', [
            'accesses' => $accesses->map(fn ($access) => $access->toArray() + ['opens_at' => $access->opensAt()->toIso8601String(), 'closes_at' => $access->closesAt()->toIso8601String()]),
            'attempts' => $attempts->map(fn ($attempt) => $attempt->only(['id', 'exam_access_id', 'attempt_number', 'started_at', 'expires_at', 'submitted_at', 'correct_count', 'total_points', 'graded_at']) + ['total_questions' => count($attempt->questions_snapshot)]),
            'today' => now('Asia/Bangkok')->toDateString(),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function start(ExamAccess $examAccess): RedirectResponse
    {
        abort_unless($examAccess->user_id === auth()->id(), 403);
        $attempt = DB::transaction(function () use ($examAccess) {
            $access = ExamAccess::whereKey($examAccess->id)->lockForUpdate()->firstOrFail();
            $this->assertAvailable($access);
            $current = $access->attempts()->whereNull('submitted_at')->latest()->lockForUpdate()->first();
            if ($current && $current->expires_at->isFuture()) {
                return $current;
            }
            if ($current) {
                $current->finish();
            }
            if ($access->attempts()->count() >= $access->max_attempts) {
                throw ValidationException::withMessages(['access' => 'Bạn đã sử dụng hết số lượt thi được cấp.']);
            }
            $questions = $access->exam->questions()->with('group')->get();
            if ($questions->isEmpty()) {
                throw ValidationException::withMessages(['access' => 'Đề thi chưa có câu hỏi. Vui lòng liên hệ quản trị viên.']);
            }
            $expires = now()->addMinutes($access->exam->duration_minutes);
            $dayEnd = $access->closesAt()->utc();
            if ($expires->greaterThan($dayEnd)) {
                $expires = $dayEnd;
            }

            return $access->attempts()->create([
                'attempt_number' => $access->attempts()->count() + 1,
                'questions_snapshot' => $questions->map(fn ($question) => [
                    'id' => $question->id, 'part' => $question->part, 'content' => $question->content,
                    'kind' => $question->kind, 'skill' => $question->skill, 'max_points' => (float) $question->max_points, 'options' => $question->options, 'correct_option' => $question->correct_option,
                    'passage' => $question->group?->passage, 'media_path' => $question->group?->media_path,
                ])->all(),
                'answers' => [], 'started_at' => now(), 'expires_at' => $expires,
            ]);
        });

        return redirect(ExamTrack::candidateBase().'/attempts/'.$attempt->id);
    }

    public function show(ExamAttempt $examAttempt): Response
    {
        $this->authorizeAttempt($examAttempt);
        if (! $examAttempt->submitted_at && $examAttempt->expires_at->isPast()) {
            DB::transaction(fn () => ExamAttempt::whereKey($examAttempt->id)->lockForUpdate()->firstOrFail()->finish());
            $examAttempt->refresh();
        }

        return Inertia::render('Candidate/Attempt', [
            'attempt' => $examAttempt->only(['id', 'attempt_number', 'answers', 'started_at', 'expires_at', 'submitted_at', 'correct_count', 'total_points', 'graded_at']),
            'exam' => $examAttempt->access->exam->only(['title', 'duration_minutes']),
            'questions' => collect($examAttempt->questions_snapshot)->map(fn ($question) => collect($question)->except('correct_option')->all()),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function save(Request $request, ExamAttempt $examAttempt): JsonResponse
    {
        $this->authorizeAttempt($examAttempt);
        DB::transaction(function () use ($request, $examAttempt) {
            $attempt = ExamAttempt::whereKey($examAttempt->id)->lockForUpdate()->firstOrFail();
            abort_if($attempt->submitted_at || $attempt->expires_at->isPast(), 409, 'Bài thi đã kết thúc.');
            $attempt->update(['answers' => $this->validatedAnswers($request, $attempt)]);
        });

        return response()->json(['saved' => true]);
    }

    public function submit(Request $request, ExamAttempt $examAttempt): RedirectResponse
    {
        $this->authorizeAttempt($examAttempt);
        DB::transaction(function () use ($request, $examAttempt) {
            $attempt = ExamAttempt::whereKey($examAttempt->id)->lockForUpdate()->firstOrFail();
            if ($attempt->submitted_at) {
                return;
            }
            if ($attempt->expires_at->isFuture()) {
                $attempt->update(['answers' => $this->validatedAnswers($request, $attempt)]);
            }
            $attempt->finish();
        });

        return redirect(ExamTrack::candidateBase().'/attempts/'.$examAttempt->id);
    }

    private function assertAvailable(ExamAccess $access): void
    {
        if (! $access->is_active || $access->exam->status !== 'published' || now()->lt($access->opensAt()) || now()->gte($access->closesAt())) {
            throw ValidationException::withMessages(['access' => 'Chưa đến ngày/giờ thi, đã hết giờ, quyền thi bị thu hồi hoặc đề thi chưa mở. Thời gian tính theo giờ Việt Nam (UTC+7).']);
        }
    }

    private function authorizeAttempt(ExamAttempt $attempt): void
    {
        abort_unless($attempt->access->user_id === auth()->id(), 403);
        if (! $attempt->submitted_at) {
            abort_unless($attempt->access->is_active && $attempt->access->exam->status === 'published', 403, 'Quyền thi đã bị tạm khóa.');
        }
    }

    private function validatedAnswers(Request $request, ExamAttempt $attempt): array
    {
        $data = $request->validate(['answers' => 'present|array', 'answers.*' => 'nullable|string|max:20000']);
        $questions = collect($attempt->questions_snapshot)->keyBy('id');
        foreach ($data['answers'] as $id => $answer) {
            $question = $questions->get($id);
            if (! $question) {
                throw ValidationException::withMessages(['answers' => 'Câu hỏi không thuộc bài thi này.']);
            }
            $kind = $question['kind'] ?? 'mcq';
            if ($kind === 'mcq' && $answer !== null && (! in_array($answer, ['A', 'B', 'C', 'D'], true) || ! filled($question['options'][$answer] ?? null))) {
                throw ValidationException::withMessages(['answers' => 'Đáp án trắc nghiệm không hợp lệ.']);
            }
            if ($kind === 'speaking' && $answer !== null && $answer !== '' && ($answer !== 'recorded' || empty($attempt->recordings[$id]))) {
                throw ValidationException::withMessages(['answers' => 'Cần tải bài ghi âm trước khi nộp.']);
            }
        }

        return $data['answers'];
    }

    public function record(Request $request, ExamAttempt $examAttempt, string $questionId): JsonResponse
    {
        $this->authorizeAttempt($examAttempt);
        $question = collect($examAttempt->questions_snapshot)->firstWhere('id', (int) $questionId);
        abort_unless(($question['kind'] ?? null) === 'speaking', 404);
        $request->validate(['recording' => 'required|file|mimetypes:audio/webm,video/webm,audio/ogg,application/ogg,audio/wav,audio/x-wav,audio/mpeg,audio/mp4,video/mp4|max:20480']);
        $path = $request->file('recording')->store('exam-recordings', 'local');
        try {
            DB::transaction(function () use ($examAttempt, $questionId, $path) {
                $attempt = ExamAttempt::whereKey($examAttempt->id)->lockForUpdate()->firstOrFail();
                abort_if($attempt->submitted_at || ! $attempt->expires_at->isFuture(), 409, 'Bài thi đã kết thúc.');
                $recordings = $attempt->recordings ?? [];
                $old = $recordings[$questionId] ?? null;
                $recordings[$questionId] = $path;
                $answers = $attempt->answers ?? [];
                $answers[$questionId] = 'recorded';
                $attempt->update(['recordings' => $recordings, 'answers' => $answers]);
                if ($old) {
                    Storage::disk('local')->delete($old);
                }
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return response()->json(['saved' => true]);
    }

    public function recording(ExamAttempt $examAttempt, string $questionId)
    {
        abort_unless($examAttempt->access->user_id === auth()->id(), 403);
        $path = $examAttempt->recordings[$questionId] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Content-Disposition' => 'inline']);
    }
}
