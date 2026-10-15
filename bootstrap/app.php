<?php

use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureExamTrack;
use App\Http\Middleware\EnsurePortalRole;
use App\Http\Middleware\ShareInertia;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [ShareInertia::class]);
        $middleware->alias([
            'active.user' => EnsureActiveUser::class,
            'portal' => EnsurePortalRole::class,
            'track' => EnsureExamTrack::class,
        ]);
        $middleware->redirectGuestsTo(function (Request $request) {
            foreach (['toeic', 'vstep', 'course'] as $track) {
                if ($request->is('exam/'.$track, 'exam/'.$track.'/*')) {
                    return '/exam/'.$track.'/login';
                }
            }

return $request->is('exam', 'exam/*') ? '/exam/login' : '/admin/login';
        });
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()->isStaff() ? '/dashboard' : '/exam');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
