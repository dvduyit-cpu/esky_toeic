<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\Subject;
use App\Support\ExamTrack;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SubjectController extends Controller
{
    public function index()
    {
        abort_unless(ExamTrack::current() === 'course', 404);

        return Inertia::render('Subjects', ['subjects' => Subject::orderBy('name')->get()]);
    }

    private function data(Request $request, ?Subject $subject = null): array
    {
        return $request->validate(['code' => ['required', 'string', 'max:50', Rule::unique('subjects')->ignore($subject?->id)], 'name' => 'required|string|max:255', 'department' => 'nullable|string|max:255', 'is_active' => 'required|boolean']);
    }

    public function store(Request $request)
    {
        Subject::create($this->data($request));

        return back()->with('success', 'Đã thêm môn học.');
    }

    public function update(Request $request, Subject $subject)
    {
        $subject->update($this->data($request, $subject));

        return back()->with('success', 'Đã cập nhật môn học.');
    }

    public function destroy(Subject $subject)
    {
        if (Question::where('subject_id', $subject->id)->exists() || Exam::where('subject_id', $subject->id)->exists() || QuestionGroup::where('subject_id', $subject->id)->exists()) {
            return back()->withErrors(['delete' => 'Môn đã có học liệu hoặc đề thi. Hãy ngừng sử dụng thay vì xóa.']);
        } $subject->delete();

        return back()->with('success', 'Đã xóa môn học.');
    }
}
