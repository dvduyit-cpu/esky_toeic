<?php

namespace App\Http\Middleware;

use App\Models\SoftwareSetting;
use App\Support\ExamTrack;
use Illuminate\Http\Request;
use Inertia\Middleware;

class ShareInertia extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => ['user' => $request->user()?->only(['id', 'name', 'email', 'role', 'exam_track', 'managed_tracks'])],
            'context' => fn () => ExamTrack::context(), 'modules' => array_values(ExamTrack::all()),
            'branding' => fn () => SoftwareSetting::publicSettings(),
            'flash' => ['success' => $request->session()->get('success')],
        ]);
    }
}
