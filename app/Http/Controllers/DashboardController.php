<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\Question;
use App\Models\User;
use App\Support\ExamTrack;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $tracks = collect(ExamTrack::all())->filter(fn ($track) => auth()->user()->canManageTrack($track['slug']));

        return Inertia::render('Dashboard', ['stats' => ['users' => User::whereIn('role', ['admin', 'teacher'])->count(), 'questions' => Question::whereIn('exam_track', $tracks->keys())->count(), 'exams' => Exam::whereIn('exam_track', $tracks->keys())->count()], 'moduleStats' => $tracks->map(fn ($track) => $track + ['questions' => Question::where('exam_track', $track['slug'])->count(), 'exams' => Exam::where('exam_track', $track['slug'])->count(), 'students' => User::where('role', 'student')->where('exam_track', $track['slug'])->count()])->values()]);
    }
}
