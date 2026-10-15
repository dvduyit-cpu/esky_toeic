<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user()?->is_active) {
            $login = $request->user()?->isStaff() ? '/admin/login' : '/exam/login';
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect($login);
        }

return $next($request);
    }
}
