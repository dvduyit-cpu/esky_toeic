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

    public function index()
    {
        $results = ExamAttempt::whereHas('access.exam', fn ($q) => $q->where('exam_track', ExamTrack::current())->when(auth()->user()->role === 'teacher', fn ($q) => $q->where('created_by', auth()->id())))->with('access.user:id,name,email', 'access.exam:id,title')->latest()->paginate(15)->through(fn ($attempt) => $attempt->only(['id', 'attempt_number', 'answers', 'submitted_at', 'correct_count', 'total_points', 'graded_at', 'manual_scores']) + ['student' => $attempt->access->user, 'exam' => $attempt->access->exam, 'questions' => $attempt->questions_snapshot]);

        return Inertia::render('Results', ['results' => $results]);
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
