<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class EnsureActiveUser {public function handle(Request $request,Closure $next){if(!$request->user()?->is_active){auth()->logout();$request->session()->invalidate();return redirect('/login');}return $next($request);}}
