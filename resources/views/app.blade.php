<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    @php
        $branding = \App\Models\SoftwareSetting::publicSettings();
        $fonts = ['system' => 'Inter, "Segoe UI", Arial, sans-serif', 'arial' => 'Arial, sans-serif', 'georgia' => 'Georgia, "Times New Roman", serif'];
    @endphp
    <link rel="icon" href="{{ $branding['logo_url'] ?? asset('favicon.svg') }}">
    <title inertia>{{ $branding['software_name'] }}</title>
    @vite(['resources/js/app.js','resources/css/app.css'])
    <style>:root{--primary:{{ $branding['primary_color'] }};--on-primary:{{ $branding['on_primary'] }};--accent:{{ $branding['accent_color'] }};--app-font:{!! $fonts[$branding['font_family']] !!};--app-font-size:{{ $branding['font_size'] }}px;--corner-radius:{{ $branding['corner_radius'] }}px;--font-scale:{{ $branding['font_size'] / 15 }}}</style>
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
