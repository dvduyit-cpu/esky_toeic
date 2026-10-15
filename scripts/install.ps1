# Run this file from the extracted esky-toeic-starter directory.
$ErrorActionPreference = 'Stop'
$source = Split-Path -Parent $PSScriptRoot
$target = Join-Path (Split-Path -Parent $source) 'esky-toeic-app'
if (Test-Path $target) { throw "Folder $target already exists. Move it aside before installing to avoid overwriting data." }
composer create-project laravel/laravel $target '^13.0'
if ($LASTEXITCODE -ne 0) { throw 'Composer project creation failed' }
Push-Location $target
try {
 composer require inertiajs/inertia-laravel
 if ($LASTEXITCODE -ne 0) { throw 'Composer require failed' }
 npm install vue '@inertiajs/vue3' '@vitejs/plugin-vue'
 if ($LASTEXITCODE -ne 0) { throw 'npm install failed' }
 foreach ($folder in @('app','database','resources','routes','tests')) { Copy-Item (Join-Path $source $folder '*') (Join-Path $target $folder) -Recurse -Force }
 Copy-Item (Join-Path $source 'vite.config.js') (Join-Path $target 'vite.config.js') -Force
 $boot = 'bootstrap/app.php'
 $text = Get-Content $boot -Raw
 $needle = '->withMiddleware(function (Middleware $middleware): void {'
 if (-not $text.Contains($needle)) { throw 'Laravel bootstrap signature changed: manually add ShareInertia and active.user middleware. See scripts/INSTALL_IN_BOOTSTRAP.txt' }
 $inject = @'
->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [\App\Http\Middleware\ShareInertia::class]);
        $middleware->alias(['active.user' => \App\Http\Middleware\EnsureActiveUser::class]);
'@
 $inject = $inject.Replace('\', '')
 $text = $text.Replace($needle, $inject)
 Set-Content $boot $text
 Write-Host 'Source installed. Configure .env MySQL and ESKY_ADMIN_PASSWORD, then: php artisan migrate --seed; npm run build; php artisan serve'
} finally { Pop-Location }
