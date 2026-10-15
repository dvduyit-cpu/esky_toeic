<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamAccess;
use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\User;
use App\Support\ExamTrack;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UserController extends Controller
{
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

        return Inertia::render('Users', ['users' => User::whereIn('role', $type === 'student' ? ['student'] : ['admin', 'teacher'])->when($type === 'student', fn ($q) => $q->where('exam_track', ExamTrack::current()))->when($filters['search'] ?? null, fn ($q, $v) => $q->where(fn ($s) => $s->where('name', 'like', '%'.$v.'%')->orWhere('email', 'like', '%'.$v.'%')))->latest()->paginate(15)->withQueryString(), 'search' => $filters['search'] ?? '', 'type' => $type]);
    }

    public function store(Request $r)
    {
        $this->admin();
        $scope = $r->input('role') === 'student' ? ExamTrack::current() : 'staff';
        $data = $r->validate(['name' => 'required|string|max:255', 'email' => ['required', 'email', 'max:255', Rule::unique('users')->where('login_scope', $scope)], 'password' => 'required|string|min:12|max:128', 'role' => ['required', Rule::in($this->managed() ? ['student'] : ['admin', 'teacher', 'student'])], 'managed_tracks' => 'sometimes|array', 'managed_tracks.*' => ['string', Rule::in(array_keys(ExamTrack::all()))]]);
        $data['managed_tracks'] = $data['role'] === 'teacher' ? ($data['managed_tracks'] ?? ['toeic']) : null;
        User::create($data + ['is_active' => true, 'exam_track' => ExamTrack::current(), 'login_scope' => $scope]);

        return back()->with('success', 'Đã tạo tài khoản.');
    }

    public function update(Request $r, User $user)
    {
        $this->admin();
        $data = $r->validate(['name' => 'required|string|max:255', 'role' => ['required', Rule::in($this->managed() ? ['student'] : ['admin', 'teacher', 'student'])], 'is_active' => 'required|boolean', 'password' => 'nullable|string|min:12|max:128', 'managed_tracks' => 'sometimes|array', 'managed_tracks.*' => ['string', Rule::in(array_keys(ExamTrack::all()))]]);
        if ($user->id === auth()->id() && ($data['role'] !== 'admin' || ! $data['is_active'])) {
            return back()->withErrors(['role' => 'Không thể tự hạ quyền hoặc khóa chính mình.']);
        }if (($user->role === 'student') !== ($data['role'] === 'student')) {
            return back()->withErrors(['role' => 'Tài khoản thi và quản trị được tạo riêng.']);
        }if (empty($data['password'])) {
            unset($data['password']);
        }if ($data['role'] !== 'teacher') {
            $data['managed_tracks'] = null;
        }$user->update($data);

        return back()->with('success', 'Đã cập nhật tài khoản.');
    }

    public function destroy(User $user)
    {
        $this->admin();
        if ($user->id === auth()->id()) {
            return back()->withErrors(['delete' => 'Không thể xóa tài khoản đang đăng nhập.']);
        }if (ExamAccess::where('user_id', $user->id)->orWhere('created_by', $user->id)->exists() || Exam::where('created_by', $user->id)->exists() || Question::where('created_by', $user->id)->exists() || QuestionGroup::where('created_by', $user->id)->exists()) {
            return back()->withErrors(['delete' => 'Tài khoản đã có dữ liệu. Hãy khóa tài khoản để giữ lịch sử.']);
        }$user->delete();

        return back()->with('success','Đã xóa tài khoản chưa sử dụng.');
    }
}
