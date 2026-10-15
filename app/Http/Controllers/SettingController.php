<?php

namespace App\Http\Controllers;

use App\Models\SoftwareSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SettingController extends Controller
{
    private function admin(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
    }

    public function index()
    {
        $this->admin();

        return Inertia::render('Settings', ['settings' => SoftwareSetting::publicSettings(), 'defaults' => collect(SoftwareSetting::defaults())->except('logo_path')]);
    }

    public function update(Request $request)
    {
        $this->admin();
        $section = $request->validate(['section' => 'required|in:software,appearance'])['section'];
        $rules = $section === 'software' ? [
            'software_name' => 'required|string|max:80', 'tagline' => 'required|string|max:100', 'organization_name' => 'nullable|string|max:160',
            'footer_text' => 'nullable|string|max:255', 'footer_note' => 'nullable|string|max:255', 'login_heading' => 'required|string|max:250', 'login_description' => 'nullable|string|max:600', 'support_email' => 'nullable|email|max:255',
            'logo' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048|dimensions:max_width=2048,max_height=2048', 'remove_logo' => 'sometimes|boolean',
        ] : [
            'primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'], 'font_family' => ['required', Rule::in(['system', 'arial', 'georgia'])], 'font_size' => 'required|integer|between:14,18', 'corner_radius' => 'required|integer|between:4,16',
        ];
        $data = $request->validate($rules);
        $newPath = null;
        if ($request->hasFile('logo')) {
            $newPath = $request->file('logo')->store('branding', 'local');
            abort_unless($newPath, 503, 'Chưa lưu được logo.');
        }
        $oldPath = null;
        try {
            DB::transaction(function () use ($data, $request, $newPath, &$oldPath) {
                DB::table('software_settings')->insertOrIgnore(['id' => 1, 'data' => json_encode(SoftwareSetting::defaults()), 'created_at' => now(), 'updated_at' => now()]);
                $settings = SoftwareSetting::whereKey(1)->lockForUpdate()->firstOrFail();
                $values = array_replace(SoftwareSetting::defaults(), $settings->data);
                $oldPath = $values['logo_path'];
                $changes = collect($data)->except(['logo', 'remove_logo'])->map(fn ($value) => $value ?? '')->all();
                if ($newPath) {
                    $changes['logo_path'] = $newPath;
                } elseif ($request->boolean('remove_logo')) {
                    $changes['logo_path'] = null;
                }
                $settings->update(['data' => array_replace($values, $changes)]);
            });
        } catch (\Throwable $exception) {
            if ($newPath) {
                Storage::disk('local')->delete($newPath);
            }throw $exception;
        }
        if ($oldPath && ($newPath || $request->boolean('remove_logo'))) {
            Storage::disk('local')->delete($oldPath);
        }

        return back()->with('success', 'Đã cập nhật cấu hình. Các trang dùng chung nhận cấu hình khi mở hoặc chuyển trang.');
    }

    public function logo(string $version)
    {
        $path = SoftwareSetting::current()['logo_path'];
        abort_unless($path && hash_equals(basename($path), $version) && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'public, max-age=31536000, immutable', 'X-Content-Type-Options' => 'nosniff']);
    }
}
