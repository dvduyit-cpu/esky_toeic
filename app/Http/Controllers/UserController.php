<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamAccess;
use App\Models\ExamRound;
use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\User;
use App\Support\ExamTrack;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class UserController extends Controller
{
    public function importTemplate()
    {
        $this->admin();
        return \App\Support\ExcelFiles::download('mau-tai-khoan-'.ExamTrack::current().'.xlsx', ['ho_ten', 'ten_dang_nhap', 'mat_khau'], []);
    }

    public function import(Request $request)
    {
        $this->admin();
        $request->validate(['file' => 'required|file|mimes:xlsx|max:5120', 'preview' => 'sometimes|boolean']);
        $round = $this->roundSelection($request, true);
        $rows = \App\Support\StudentExcelImport::read($request->file('file')->getRealPath(), ExamTrack::current());
        if ($request->boolean('preview')) {
            return response()->json(['count' => count($rows), 'rows' => array_map(fn ($row) => ['row' => $row['row'], 'name' => $row['name'], 'username' => $row['email']], array_slice($rows, 0, 20))]);
        }
        try { DB::transaction(function () use ($rows, $round) {
            foreach ($rows as $row) {
                $user = User::create(['name' => $row['name'], 'email' => $row['email'], 'password' => $row['password'], 'role' => 'student', 'is_active' => true, 'exam_track' => ExamTrack::current(), 'login_scope' => ExamTrack::current()]);
                if ($round) $this->assignRound($user, $round);
            }
        }); }
        catch (\Illuminate\Database\UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['file' => 'Có tên đăng nhập vừa được tạo bởi yêu cầu khác. Hãy kiểm tra lại file trước khi nhập.']);
        }
        return back()->with('success', 'Đã nhập '.count($rows).' tài khoản'.($round ? ' và xếp vào khóa '.$round->name : '').'.');
    }

    private function admin(): void
    {
        abort_unless(auth()->user()->role === 'admin', 403);
    }

    private function managed(): bool
    {
        return request()->is('manage/*');
    }

    public function index(Request $r)
    {
        $this->admin();
        $filters = $r->validate(['search' => 'nullable|string|max:255', 'type' => 'nullable|in:staff,student']);
        $type = $this->managed() ? 'student' : ($filters['type'] ?? 'staff');

        return Inertia::render('Users', ['users' => User::with(['examAccesses' => fn ($q) => $q->with(['round:id,name', 'exam:id,status'])->withCount('attempts')->withCount(['attempts as live_attempts_count' => fn ($a) => $a->whereNull('submitted_at')->where('expires_at', '>', now())])])->whereIn('role', $type === 'student' ? ['student'] : ['admin', 'teacher'])->when($type === 'student', fn ($q) => $q->where('exam_track', ExamTrack::current()))->when($filters['search'] ?? null, fn ($q, $v) => $q->where(fn ($s) => $s->where('name', 'like', '%'.$v.'%')->orWhere('email', 'like', '%'.$v.'%')))->latest()->paginate(15)->withQueryString()->through(function ($user) {
            foreach ($user->examAccesses as $access) {
                $access->setAttribute('archived', !$access->is_active || $access->exam->status !== 'published' || now()->gte($access->closesAt()) || (!$access->live_attempts_count && $access->attempts_count >= $access->max_attempts));
            }
            return $user;
        }), 'search' => $filters['search'] ?? '',  'type' => $type,
 'exam_rounds' => $type === 'student' ? ExamRound::where('exam_track', ExamTrack::current())->whereDate('exam_date', '>=', now('Asia/Bangkok')->toDateString())->whereHas('exam', fn ($q) => $q->where('status', 'published')->has('questions'))->with('exam:id,title')->orderByDesc('exam_date')->get()->filter(fn ($round) => now()->lt((new ExamAccess($round->only(['exam_date', 'access_mode', 'start_time', 'end_time'])))->closesAt()))->values() : [],
 'exams' => $type === 'student' ? Exam::where('exam_track', ExamTrack::current())->where('status', 'published')->has('questions')->withCount('questions')->orderBy('title')->get(['id', 'title']) : [],
 'today' => now('Asia/Bangkok')->toDateString(),
 ]);
    }

    public function store(Request $r)
    {
        $this->admin();
        $scope = $r->input('role') === 'student' ? ExamTrack::current() : 'staff';
        $student = $r->input('role') === 'student';
        if ($student && !filled($r->input('password'))) $r->merge(['password' => '123456']);
        $data = $r->validate(['name' => 'required|string|max:255', 'email' => ['required', 'string', $student ? 'regex:/^[a-zA-Z0-9_.@+\-]+$/' : 'email', 'max:255', Rule::unique('users')->where('login_scope', $scope)], 'password' => 'required|string|min:'.($student ? 4 : 12).'|max:128', 'role' => ['required', Rule::in($this->managed() ? ['student'] : ['admin', 'teacher', 'student'])], 'managed_tracks' => 'sometimes|array', 'managed_tracks.*' => ['string', Rule::in(array_keys(ExamTrack::all()))]]);
        $data['managed_tracks'] = $data['role'] === 'teacher' ? ($data['managed_tracks'] ?? ['toeic']) : null;
        $round = $this->roundSelection($r, $student);
        DB::transaction(function () use ($data, $scope, $round) {
            $user = User::create($data + ['is_active' => true, 'exam_track' => ExamTrack::current(), 'login_scope' => $scope]);
            if ($round) $this->assignRound($user, $round);
        });

        return back()->with('success', $round ? 'Đã tạo tài khoản và cấp toàn bộ đề của đợt thi.' : 'Đã tạo tài khoản.');
    }

    public function update(Request $r, User $user)
    {
        $this->admin();
        $data = $r->validate(['name' => 'required|string|max:255', 'role' => ['required', Rule::in($this->managed() ? ['student'] : ['admin', 'teacher', 'student'])], 'is_active' => 'required|boolean', 'password' => 'nullable|string|min:'.($user->role === 'student' ? 4 : 12).'|max:128', 'managed_tracks' => 'sometimes|array', 'managed_tracks.*' => ['string', Rule::in(array_keys(ExamTrack::all()))]]);
        if ($user->id === auth()->id() && ($data['role'] !== 'admin' || ! $data['is_active'])) {
            return back()->withErrors(['role' => 'Không thể tự hạ quyền hoặc khóa chính mình.']);
        }if (($user->role === 'student') !== ($data['role'] === 'student')) {
            return back()->withErrors(['role' => 'Tài khoản thi và quản trị được tạo riêng.']);
        }if (empty($data['password'])) {
            unset($data['password']);
        }if ($data['role'] !== 'teacher') {
            $data['managed_tracks'] = null;
        }
        $round = $this->roundSelection($r, $user->role === 'student');
        DB::transaction(function () use ($user, $data, $round) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $user->update($data);
            if ($round) $this->assignRound($user, $round);
        });

        return back()->with('success', 'Đã cập nhật tài khoản.');
    }

    public function destroy(User $user)
    {
        $this->admin();
        if ($user->id === auth()->id()) {
            return back()->withErrors(['delete' => 'Không thể xóa tài khoản đang đăng nhập.']);
        }if (ExamAccess::where('user_id', $user->id)->orWhere('created_by', $user->id)->exists() || ExamRound::where('created_by', $user->id)->exists() || Exam::where('created_by', $user->id)->exists() || Question::where('created_by', $user->id)->exists() || QuestionGroup::where('created_by', $user->id)->exists()) {
            return back()->withErrors(['delete' => 'Tài khoản đã có dữ liệu. Hãy khóa tài khoản để giữ lịch sử.']);
        }$user->delete();

        return back()->with('success','Đã xóa tài khoản chưa sử dụng.');
    }

    public function storeRound(Request $request)
    {
        $this->admin();
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'exam_id' => ['required', Rule::exists('exams', 'id')->where('exam_track', ExamTrack::current())->where('status', 'published')],
            'exam_date' => 'required|date_format:Y-m-d|after_or_equal:'.now('Asia/Bangkok')->toDateString(),
            'access_mode' => 'required|in:day,session', 'max_attempts' => 'required|integer|between:1,100',
            'session_name' => 'exclude_unless:access_mode,session|required|string|max:255',
            'start_time' => 'exclude_unless:access_mode,session|required|date_format:H:i',
            'end_time' => 'exclude_unless:access_mode,session|required|date_format:H:i|after:start_time',
        ]);
        if (!Exam::findOrFail($data['exam_id'])->questions()->exists()) {
            throw ValidationException::withMessages(['exam_id' => 'Đề thi chưa có câu hỏi.']);
        }
        ExamRound::create($data + ['exam_track' => ExamTrack::current(), 'created_by' => auth()->id()]);
        return back()->with('success', 'Đã tạo đợt thi. Chọn đợt khi tạo tài khoản để cấp trọn đề.');
    }

    private function roundSelection(Request $request, bool $student): ?ExamRound
    {
        $data = $request->validate(['exam_round_id' => ['nullable', Rule::prohibitedIf(!$student), Rule::exists('exam_rounds', 'id')->where('exam_track', ExamTrack::current())]]);
        return empty($data['exam_round_id']) ? null : ExamRound::findOrFail($data['exam_round_id']);
    }

    private function assignRound(User $user, ExamRound $round): void
    {
        $exam = Exam::whereKey($round->exam_id)->lockForUpdate()->firstOrFail();
        $values = $round->only(['exam_id', 'exam_date', 'access_mode', 'session_name', 'start_time', 'end_time', 'max_attempts']);
        $access = new ExamAccess($values);
        if ($exam->status !== 'published' || !$exam->questions()->exists() || now()->gte($access->closesAt())) {
            throw ValidationException::withMessages(['exam_round_id' => 'Đợt thi đã hết hạn hoặc đề thi chưa mở.']);
        }
        $existing = ExamAccess::where('user_id', $user->id)->where('exam_id', $round->exam_id)->whereDate('exam_date', $round->exam_date)
            ->where('access_mode', $round->access_mode)->when($round->access_mode === 'session', fn ($q) => $q->where('start_time', $round->start_time))->first();
        if ($existing) {
            $existing->update(['exam_round_id' => $round->id]);
            return;
        }
        ExamAccess::create($values + ['user_id' => $user->id, 'exam_round_id' => $round->id, 'is_active' => true, 'created_by' => auth()->id()]);
    }
}
