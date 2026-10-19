<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamAccess;
use App\Models\User;
use App\Support\ExamTrack;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ExamAccessController extends Controller
{
    private function admin(): void
    {
        abort_unless(auth()->user()->role === 'admin', 403);
    }

    public function index(Request $request): Response
    {
        $this->admin();
        $filters = $request->validate(['search' => 'nullable|string|max:255', 'date' => 'nullable|date_format:Y-m-d']);

        return Inertia::render('ExamAccesses', [
            'accesses' => ExamAccess::whereHas('exam', fn ($query) => $query->where('exam_track', ExamTrack::current()))->with(['user:id,name,email,is_active', 'exam:id,title,status', 'round:id,name'])
                ->withCount('attempts')->with(['attempts' => fn ($query) => $query->select('id', 'exam_access_id', 'attempt_number', 'started_at', 'submitted_at', 'correct_count')->latest()])
                ->when($filters['date'] ?? null, fn ($query, $date) => $query->whereDate('exam_date', $date))
                ->when($filters['search'] ?? null, fn ($query, $search) => $query->whereHas('user', fn ($user) => $user->where(fn ($names) => $names->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'))))
                ->latest()->paginate(15)->withQueryString(),
            'students' => User::where('exam_track', ExamTrack::current())->where('role', 'student')->where('is_active', true)->orderBy('name')->get(['id', 'name', 'email']),
            'exams' => Exam::where('exam_track', ExamTrack::current())->where('status', 'published')->withCount('questions')->orderBy('title')->get(['id', 'title']),
            'filters' => $filters,
            'today' => now('Asia/Bangkok')->toDateString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->admin();
        $data = $request->validate([
            'user_id' => ['required', Rule::exists('users', 'id')->where('role', 'student')->where('is_active', true)->where('exam_track', ExamTrack::current())],
            'exam_id' => ['required', Rule::exists('exams', 'id')->where('status', 'published')->where('exam_track', ExamTrack::current())],
            'exam_date' => 'required|date_format:Y-m-d|after_or_equal:'.now('Asia/Bangkok')->toDateString(),
            'max_attempts' => 'required|integer|between:1,100',
            'access_mode' => 'required|in:day,session',
            'session_name' => 'exclude_unless:access_mode,session|required|string|max:255',
            'start_time' => 'exclude_unless:access_mode,session|required|date_format:H:i',
            'end_time' => 'exclude_unless:access_mode,session|required|date_format:H:i|after:start_time',
        ]);
        if ($data['access_mode'] === 'session') {
            $data['start_time'] .= ':00';
            $data['end_time'] .= ':00';
        }
        DB::transaction(function () use ($data) {
            User::whereKey($data['user_id'])->lockForUpdate()->firstOrFail();
            if (ExamAccess::where('user_id', $data['user_id'])->where('exam_id', $data['exam_id'])->whereDate('exam_date', $data['exam_date'])->where('access_mode', $data['access_mode'])->when($data['access_mode'] === 'session', fn ($query) => $query->where('start_time', $data['start_time']))->exists()) {
                throw ValidationException::withMessages(['exam_date' => 'Đã cấp đề thi này theo ngày hoặc ca đã chọn. Hãy chỉnh sửa lượt thi hiện có.']);
            }
            ExamAccess::create($data + ['created_by' => auth()->id()]);
        });

        return back()->with('success', 'Đã cấp quyền thi theo ngày và số lượt.');
    }

    public function update(Request $request, ExamAccess $examAccess): RedirectResponse
    {
        $this->admin();
        $mode = $request->input('access_mode', $examAccess->access_mode);
        if ($mode === 'session' && $examAccess->access_mode === 'session') {
            $request->mergeIfMissing([
                'session_name' => $examAccess->session_name,
                'start_time' => substr($examAccess->start_time, 0, 5),
                'end_time' => substr($examAccess->end_time, 0, 5),
            ]);
        }
        $data = $request->validate([
            'max_attempts' => 'required|integer|between:1,100',
            'is_active' => 'required|boolean',
            'exam_date' => ['sometimes', 'required', 'date_format:Y-m-d', Rule::when($request->input('exam_date', $examAccess->exam_date->toDateString()) !== $examAccess->exam_date->toDateString(), 'after_or_equal:'.now('Asia/Bangkok')->toDateString())],
            'access_mode' => 'sometimes|required|in:day,session',
            'session_name' => [Rule::excludeIf($mode !== 'session'), 'required', 'string', 'max:255'],
            'start_time' => [Rule::excludeIf($mode !== 'session'), 'required', 'date_format:H:i'],
            'end_time' => [Rule::excludeIf($mode !== 'session'), 'required', 'date_format:H:i', 'after:start_time'],
        ]);
        if ($mode === 'session') {
            $data['start_time'] .= ':00';
            $data['end_time'] .= ':00';
        } else {
            $data += ['session_name' => null, 'start_time' => null, 'end_time' => null];
        }
        DB::transaction(function () use ($data, $examAccess) {
            User::whereKey($examAccess->user_id)->lockForUpdate()->firstOrFail();
            $access = ExamAccess::whereKey($examAccess->id)->lockForUpdate()->firstOrFail();
            $date = $data['exam_date'] ?? $access->exam_date->toDateString();
            $mode = $data['access_mode'] ?? $access->access_mode;
            $start = $data['start_time'] ?? $access->start_time;
            if (ExamAccess::where('user_id', $access->user_id)->where('exam_id', $access->exam_id)
                ->whereKeyNot($access->id)->whereDate('exam_date', $date)->where('access_mode', $mode)
                ->when($mode === 'session', fn ($query) => $query->where('start_time', $start))->exists()) {
                throw ValidationException::withMessages(['exam_date' => 'Đã có quyền thi cho đề này vào ngày hoặc ca đã chọn.']);
            }
            if ($data['max_attempts'] < $access->attempts()->count()) {
                throw ValidationException::withMessages(['max_attempts' => 'Tổng số lượt phải lớn hơn hoặc bằng số lượt đã sử dụng.']);
            }
            $access->update($data);
        });

        return back()->with('success', 'Đã cập nhật quyền thi.');
    }

    public function destroy(ExamAccess $examAccess)
    {
        $this->admin();
        if ($examAccess->attempts()->exists()) {
            return back()->withErrors(['delete' => 'Quyền đã phát sinh lượt thi. Hãy thu hồi quyền thay vì xóa.']);
        }
        $examAccess->delete();

        return back()->with('success', 'Đã xóa quyền thi chưa sử dụng.');
    }
}
