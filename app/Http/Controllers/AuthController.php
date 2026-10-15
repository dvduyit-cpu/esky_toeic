<?php

namespace App\Http\Controllers;

use App\Support\ExamTrack;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function choose(Request $request): Response|RedirectResponse
    {
        if ($request->user()) {
            return redirect($request->user()->isStaff() ? '/dashboard' : '/exam/'.$request->user()->exam_track);
        }

return Inertia::render('Portals');
    }

    public function show(Request $request): Response|RedirectResponse
    {
        if ($request->user()) {
            return redirect($request->user()->isStaff() ? '/dashboard' : '/exam/'.$request->user()->exam_track);
        }

        return Inertia::render('Login', ['portal' => $request->is('admin/*') ? 'admin' : 'exam']);
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        $admin = $request->is('admin/*');
        $track = ExamTrack::current();
        $key = 'login:'.($admin ? 'staff' : $track).':'.strtolower($data['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Thử lại sau '.RateLimiter::availableIn($key).' giây.']);
        }
        $credentials = ['email' => $data['email'], 'password' => $data['password'], 'is_active' => true, 'role' => $admin ? ['admin', 'teacher'] : ['student']];
        if (! $admin) {
            $credentials['exam_track'] = $track;
        }
        if (! Auth::attempt($credentials)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'Tài khoản hoặc mật khẩu không đúng, đã bị khóa hoặc không thuộc phần thi này.']);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->session()->forget('url.intended');

        return redirect($admin ? '/dashboard' : ($request->is('exam/login') ? '/exam' : '/exam/'.$track));
    }

    public function logout(Request $request): RedirectResponse
    {
        $url = $request->user()?->isStaff() ? '/admin/login' : '/exam/'.($request->user()?->exam_track ?? 'toeic').'/login';
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect($url);
    }
}
