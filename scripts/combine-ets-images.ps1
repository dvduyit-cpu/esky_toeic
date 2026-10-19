$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Drawing
$workspace = Split-Path $PSScriptRoot -Parent
$payload = Get-Content -LiteralPath (Join-Path $workspace 'storage/app/imports/ets-2026-test-1/parsed.json') -Raw -Encoding UTF8 | ConvertFrom-Json
$mediaRoot = Join-Path $workspace 'storage/app/public/exam-media/ets-2026-test-1'
foreach ($property in $payload.groups.PSObject.Properties) {
    $group = $property.Value
    if (!$group.source_images.Count) { continue }
    $images = @($group.source_images | ForEach-Object { [Drawing.Image]::FromFile((Join-Path $mediaRoot $_)) })
    try {
        $width = ($images | Measure-Object Width -Maximum).Maximum
        $height = ($images | Measure-Object Height -Sum).Sum + 24 * ($images.Count - 1)
        $canvas = [Drawing.Bitmap]::new([int]$width, [int]$height)
        $graphics = [Drawing.Graphics]::FromImage($canvas)
        try {
            $graphics.Clear([Drawing.Color]::White)
            $top = 0
            foreach ($image in $images) {
                $graphics.DrawImageUnscaled($image, [int](($width - $image.Width) / 2), $top)
                $top += $image.Height + 24
            }
            $target = Join-Path $workspace ('storage/app/public/' + $group.media_path)
            $canvas.Save($target, [Drawing.Imaging.ImageFormat]::Png)
        } finally { $graphics.Dispose(); $canvas.Dispose() }
    } finally { foreach ($image in $images) { $image.Dispose() } }
}
Write-Output 'Combined all passage images without resizing.'
