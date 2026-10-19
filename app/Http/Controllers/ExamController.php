<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamAccess;
use App\Models\Question;
use App\Models\Subject;
use App\Support\ExamTrack;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ExamController extends Controller
{
    private function owner(Exam $exam): void
    {
        abort_unless(auth()->user()->role === 'admin' || $exam->created_by === auth()->id(), 403);
    }

    public function index()
    {
        return Inertia::render('Exams', ['exams' => Exam::where('exam_track', ExamTrack::current())->withCount('questions')->with('questions:questions.id,content,part,kind,skill')->latest()->paginate(15), 'questions' => Question::where('exam_track', ExamTrack::current())->get(['id', 'part', 'content', 'subject_id', 'kind', 'skill']), 'subjects' => ExamTrack::current() === 'course' ? Subject::where('is_active', true)->orderBy('name')->get() : []]);
    }

    private function data(Request $r): array
    {
        $data = $r->validate(['title' => 'required|string|max:255', 'description' => 'nullable|string|max:10000', 'duration_minutes' => 'required|integer|between:5,360', 'subject_id' => [ExamTrack::current() === 'course' ? 'required' : 'nullable', Rule::exists('subjects', 'id')->where('is_active', true)]]);
        if (ExamTrack::current() !== 'course') {
            $data['subject_id'] = null;
        }

return $data;
    }

    public function store(Request $r)
    {
        Exam::create($this->data($r) + ['created_by' => auth()->id(), 'exam_track' => ExamTrack::current(), 'status' => 'draft']);

        return back()->with('success', 'Đã tạo đề nháp.');
    }

    public function update(Request $r, Exam $exam)
    {
        $this->owner($exam);
        if ($exam->status !== 'draft' || ExamAccess::where('exam_id', $exam->id)->exists()) {
            return back()->withErrors(['exam' => 'Đề đã mở hoặc được cấp quyền thi. Hãy tạo đề mới để giữ lịch sử.']);
        }$data = $this->data($r);
        if ($exam->questions()->exists() && $exam->subject_id !== ($data['subject_id'] ? (int) $data['subject_id'] : null)) {
            return back()->withErrors(['subject_id' => 'Không thể đổi môn khi đề đã có câu hỏi.']);
        }$exam->update($data);

        return back()->with('success', 'Đã cập nhật đề thi.');
    }

    public function attach(Request $r, Exam $exam)
    {
        $this->owner($exam);
        $v = $r->validate(['question_id' => ['required', Rule::exists('questions', 'id')->where('exam_track', ExamTrack::current())]]);

        return DB::transaction(function () use ($exam, $v) {
            $exam = Exam::whereKey($exam->id)->lockForUpdate()->firstOrFail();
            if ($exam->status !== 'draft') {
                return back()->withErrors(['exam' => 'Đóng đề trước khi thay đổi câu hỏi.']);
            }$q = Question::findOrFail($v['question_id']);
            if ($q->subject_id !== $exam->subject_id) {
                return back()->withErrors(['question_id' => 'Câu hỏi phải cùng môn với đề thi.']);
            }if ($exam->questions()->where('questions.id', $q->id)->exists()) {
                return back()->withErrors(['question_id' => 'Câu hỏi đã có trong đề.']);
            }$exam->questions()->attach($q->id, ['position' => ((int) $exam->questions()->max('exam_questions.position')) + 1]);

            return back()->with('success', 'Đã thêm câu hỏi vào đề.');
        });
    }

    public function detach(Exam $exam, Question $question)
    {
        $this->owner($exam);
        if ($exam->status !== 'draft') {
            return back()->withErrors(['exam' => 'Đóng đề trước khi gỡ câu hỏi.']);
        }$exam->questions()->detach($question->id);

        return back()->with('success', 'Đã gỡ câu hỏi khỏi đề.');
    }

    public function status(Request $r, Exam $exam)
    {
        $this->owner($exam);
        $data = $r->validate(['status' => 'required|in:draft,published']);
        if ($data['status'] === 'published' && ! $exam->questions()->exists()) {
            return back()->withErrors(['status' => 'Cần thêm câu hỏi trước khi mở đề.']);
        }$exam->update($data);

        return back()->with('success', $data['status'] === 'published' ? 'Đã mở đề thi.' : 'Đã đóng đề thi.');
    }

    public function destroy(Exam $exam)
    {
        $this->owner($exam);
        if (ExamAccess::where('exam_id', $exam->id)->exists() || \App\Models\ExamRound::where('exam_id', $exam->id)->exists() || $exam->status === 'published') {
            return back()->withErrors(['delete' => 'Đề đã mở hoặc có quyền thi, không thể xóa. Hãy đóng đề để ngừng sử dụng.']);
        }$exam->questions()->detach();
        $exam->delete();

        return back()->with('success','Đã xóa đề nháp.');
    }
}
