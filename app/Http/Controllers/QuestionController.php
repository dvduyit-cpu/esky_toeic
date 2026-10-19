<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\Subject;
use App\Support\ExamTrack;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class QuestionController extends Controller
{
    private function owner($model): void
    {
        abort_unless(auth()->user()->role === 'admin' || $model->created_by === auth()->id(), 403);
    }

    private function track(): string
    {
        return ExamTrack::current();
    }

    public function index(Request $request)
    {
        $filters = $request->validate(['section' => 'nullable|in:listening,reading', 'part' => 'nullable|integer|between:1,7', 'subject_id' => 'nullable|integer', 'skill' => 'nullable|in:listening,reading,writing,speaking', 'search' => 'nullable|string|max:255']);
        $counts = [];
        if ($this->track() === 'toeic') {
            $counts = [
                'listening' => Question::where('exam_track', 'toeic')->whereBetween('part', [1, 4])->count(),
                'reading' => Question::where('exam_track', 'toeic')->whereBetween('part', [5, 7])->count(),
            ];
            $filters['section'] = $filters['section'] ?? (!empty($filters['part']) ? ((int) $filters['part'] <= 4 ? 'listening' : 'reading') : ($counts['listening'] ? 'listening' : 'reading'));
            if (!empty($filters['part']) && ($filters['section'] === 'listening') !== ((int) $filters['part'] <= 4)) unset($filters['part']);
            unset($filters['skill']);
        }

        return Inertia::render('Questions', [
            'questions' => Question::where('exam_track', $this->track())->when($this->track() === 'toeic', fn ($q) => $q->whereBetween('part', $filters['section'] === 'listening' ? [1, 4] : [5, 7]))->when($filters['part'] ?? null, fn ($q, $v) => $q->where('part', $v))->when($filters['subject_id'] ?? null, fn ($q, $v) => $q->where('subject_id', $v))->when($filters['skill'] ?? null, fn ($q, $v) => $q->where('skill', $v))->when($filters['search'] ?? null, fn ($q, $v) => $q->where('content', 'like', '%'.$v.'%'))->latest()->paginate(15)->withQueryString(),
            'section_counts' => $counts,
            'groups' => QuestionGroup::where('exam_track', $this->track())->latest()->get(), 'subjects' => ExamTrack::current() === 'course' ? Subject::where('is_active', true)->orderBy('name')->get() : [], 'filters' => $filters,
        ]);
    }

    private function data(Request $request): array
    {
        $track = $this->track();
        $rules = ['part' => 'required|integer|between:1,7', 'question_group_id' => ['nullable', 'integer', Rule::exists('question_groups', 'id')->where('exam_track', $track)], 'content' => 'required|string|max:10000', 'kind' => ['sometimes', Rule::in($track === 'toeic' ? ['mcq'] : ['mcq', 'essay', 'speaking'])], 'skill' => [$track === 'vstep' ? 'required' : 'nullable', Rule::in(['listening', 'reading', 'writing', 'speaking'])], 'max_points' => 'sometimes|numeric|between:0.1,100', 'difficulty' => 'required|in:easy,medium,hard', 'explanation' => 'nullable|string|max:20000', 'subject_id' => [$track === 'course' ? 'required' : 'nullable', Rule::exists('subjects', 'id')->where('is_active', true)]];
        $kind = $request->input('kind', 'mcq');
        if ($kind === 'mcq') {
            $rules += ['options' => 'required|array:A,B,C,D', 'options.A' => 'required|string|max:10000', 'options.B' => 'required|string|max:10000', 'options.C' => 'nullable|string|max:10000', 'options.D' => 'nullable|string|max:10000', 'correct_option' => 'required|in:A,B,C,D'];
        }
        $data = $request->validate($rules);
        $data['kind'] = $kind;
        $data['max_points'] = $data['max_points'] ?? 1;
        if ($track !== 'course') {
            $data['subject_id'] = null;
        }
        if ($track !== 'vstep') {
            $data['skill'] = null;
        }
        if ($kind === 'mcq' && ! filled($data['options'][$data['correct_option']] ?? null)) {
            throw ValidationException::withMessages(['correct_option' => 'Đáp án đúng phải có nội dung.']);
        }
        if ($kind !== 'mcq') {
            $data['options'] = [];
            $data['correct_option'] = '';
        }
        if ($track === 'vstep' && (($data['skill'] === 'speaking' && $kind !== 'speaking') || ($data['skill'] === 'writing' && $kind !== 'essay') || (in_array($data['skill'], ['listening', 'reading']) && $kind !== 'mcq'))) {
            throw ValidationException::withMessages(['kind' => 'Nghe/đọc dùng trắc nghiệm, viết dùng tự luận, nói dùng bài ghi âm.']);
        }
        if (! empty($data['question_group_id'])) {
            $group = QuestionGroup::findOrFail($data['question_group_id']);
            if ($track === 'toeic' && $group->part !== (int) $data['part']) {
                throw ValidationException::withMessages(['question_group_id' => 'Nhóm tư liệu không cùng Part.']);
            } if ($track === 'course' && $group->subject_id !== (int) $data['subject_id']) {
                throw ValidationException::withMessages(['question_group_id' => 'Nhóm tư liệu không cùng môn học.']);
            }
        }
        if ($track === 'toeic' && in_array((int) $data['part'], [3, 4, 6, 7], true) && empty($data['question_group_id'])) {
            throw ValidationException::withMessages(['question_group_id' => 'Part này cần gắn nhóm tư liệu.']);
        }

        return $data;
    }

    public function store(Request $request)
    {
        Question::create($this->data($request) + ['exam_track' => $this->track(), 'created_by' => auth()->id(), 'status' => 'draft']);

        return back()->with('success', 'Đã thêm câu hỏi.');
    }

    public function update(Request $request, Question $question)
    {
        $this->owner($question);
        if ($question->exams()->exists()) {
            return back()->withErrors(['question' => 'Câu hỏi đã gắn vào đề. Tạo câu hỏi mới để giữ nội dung đề hiện có.']);
        }$question->update($this->data($request));

        return back()->with('success', 'Đã cập nhật câu hỏi.');
    }

    public function destroy(Question $question)
    {
        $this->owner($question);
        if ($question->exams()->exists()) {
            return back()->withErrors(['delete' => 'Câu hỏi đã dùng trong đề, không thể xóa.']);
        }$question->delete();

        return back()->with('success', 'Đã xóa câu hỏi.');
    }

    private function groupData(Request $request): array
    {
        $data = $request->validate(['part' => 'required|integer|between:1,7', 'title' => 'required|string|max:255', 'passage' => 'nullable|string|max:60000', 'media_path' => 'nullable|string|max:500', 'subject_id' => [$this->track() === 'course' ? 'required' : 'nullable', Rule::exists('subjects', 'id')->where('is_active', true)]]);
        if ($this->track() !== 'course') {
            $data['subject_id'] = null;
        }

return $data;
    }

    public function storeGroup(Request $request)
    {
        QuestionGroup::create($this->groupData($request) + ['exam_track' => $this->track(), 'created_by' => auth()->id()]);

        return back()->with('success', 'Đã thêm nhóm tư liệu.');
    }

    public function updateGroup(Request $request, QuestionGroup $questionGroup)
    {
        $this->owner($questionGroup);
        if ($questionGroup->questions()->exists()) {
            return back()->withErrors(['group' => 'Nhóm đã có câu hỏi. Tạo nhóm mới để thay đổi tư liệu.']);
        }$questionGroup->update($this->groupData($request));

        return back()->with('success', 'Đã sửa nhóm tư liệu.');
    }

    public function destroyGroup(QuestionGroup $questionGroup)
    {
        $this->owner($questionGroup);
        if ($questionGroup->questions()->exists()) {
            return back()->withErrors(['delete' => 'Nhóm đã có câu hỏi, không thể xóa.']);
        }$questionGroup->delete();

        return back()->with('success','Đã xóa nhóm tư liệu.');
    }
}
