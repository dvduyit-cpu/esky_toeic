# Install dependencies in this project; existing source and .env are preserved.
$ErrorActionPreference = 'Stop'
Push-Location (Split-Path -Parent $PSScriptRoot)
try {
    foreach ($tool in @('php', 'composer', 'npm.cmd')) {
        if (-not (Get-Command $tool -ErrorAction SilentlyContinue)) {
            throw "Missing $tool. Run from the Laragon terminal with PHP, Composer and Node.js installed."
        }
    }
    if (-not (Test-Path .env)) { Copy-Item .env.example .env }
    & composer install --no-interaction
    if ($LASTEXITCODE -ne 0) { throw 'Composer install failed.' }
    $envText = Get-Content .env -Raw
    if ($envText -notmatch '(?m)^APP_KEY=.+') {
        & php artisan key:generate
        if ($LASTEXITCODE -ne 0) { throw 'Application key generation failed.' }
    }
    & php artisan config:clear
    if ($LASTEXITCODE -ne 0) { throw 'Configuration clear failed.' }
    & php artisan migrate --no-interaction
    if ($LASTEXITCODE -ne 0) { throw 'Migration failed. Start Laragon MySQL and create the database configured in .env.' }
    & npm.cmd ci
    if ($LASTEXITCODE -ne 0) { throw 'npm ci failed.' }
    & npm.cmd run build
    if ($LASTEXITCODE -ne 0) { throw 'Frontend build failed.' }
    Write-Host 'Ready. See README.md for Laragon virtual host and admin account setup.'
} finally {
    Pop-Location
}
