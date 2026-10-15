<?php

namespace App\Http\Middleware;

use App\Models\Exam;
use App\Models\ExamAccess;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\Subject;
use App\Models\User;
use App\Support\ExamTrack;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureExamTrack
{
    public function handle(Request $request, Closure $next, string $track = 'legacy'): Response
    {
        $slug = $track === 'legacy' ? ($request->is('exam', 'exam/*') ? ($request->user()?->exam_track ?? 'toeic') : 'toeic') : $track;
        abort_unless(isset(ExamTrack::all()[$slug]), 404);
        $request->attributes->set('exam_track', $slug);
        $request->attributes->set('admin_base', $request->is('manage/*') ? '/manage/'.$slug : '');
        $request->attributes->set('candidate_base', $track === 'legacy' ? '/exam' : '/exam/'.$slug);
        if ($request->user()) {
            if ($request->user()->isStaff()) {
                abort_unless($request->user()->canManageTrack($slug), 403);
            } else {
                abort_unless($request->user()->exam_track === $slug, 403);
            }
        }
        foreach ($request->route()->parameters() as $model) {
            if ($model instanceof ExamAccess) {
                abort_unless($model->exam->exam_track === $slug && $model->user->exam_track === $slug, 404);
            } elseif ($model instanceof ExamAttempt) {
                abort_unless($model->access->exam->exam_track === $slug, 404);
            } elseif ($model instanceof Question || $model instanceof QuestionGroup || $model instanceof Exam) {
                abort_unless($model->exam_track === $slug, 404);
            } elseif ($model instanceof User) {
                if ($request->is('manage/*')) {
                    abort_unless($model->role === 'student', 404);
                }if ($model->role === 'student') {
                    abort_unless($model->exam_track === $slug, 404);
                }
            } elseif ($model instanceof Subject) {
                abort_unless($slug === 'course', 404);
            }
        }

        return $next($request);
    }
}
