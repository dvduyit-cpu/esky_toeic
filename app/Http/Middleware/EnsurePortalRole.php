<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePortalRole
{
    public function handle(Request $request, Closure $next, string $portal): Response
    {
        abort_unless($portal === 'exam' ? $request->user()->role === 'student' : $request->user()->isStaff(), 403);

        return $next($request);
    }
}
