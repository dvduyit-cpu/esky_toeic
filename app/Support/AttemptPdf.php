<?php

namespace App\Support;

use App\Models\ExamAttempt;
use App\Models\SoftwareSetting;
use Dompdf\Dompdf;
use Dompdf\Options;

final class AttemptPdf
{
    public static function render(ExamAttempt $attempt): string
    {
        $options = new Options;
        $options->setDefaultFont('DejaVu Sans');
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $options->setIsFontSubsettingEnabled(true);
        $options->setTempDir(sys_get_temp_dir());
        $options->setFontCache(storage_path('framework/cache'));
        $images = [];
        foreach ($attempt->questions_snapshot as $question) {
            $path = $question['media_path'] ?? null;
            if (!is_string($path) || !preg_match('/\.(png|jpe?g|gif|webp)$/i', $path)) continue;
            $base = realpath(storage_path('app/public'));
            $file = realpath(storage_path('app/public/'.$path));
            if (!$base || !$file || !str_starts_with(str_replace('\\', '/', $file), str_replace('\\', '/', $base).'/')) continue;
            $mime = @getimagesize($file)['mime'] ?? null;
            if (in_array($mime, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)) {
                $images[$path] = 'data:'.$mime.';base64,'.base64_encode(file_get_contents($file));
            }
        }
        $pdf = new Dompdf($options);
        $pdf->setPaper('A4');
        $pdf->loadHtml(view('pdf.attempt', [
            'attempt' => $attempt, 'branding' => SoftwareSetting::current(),
            'images' => $images, 'exportedAt' => now('Asia/Bangkok'),
        ])->render(), 'UTF-8');
        $pdf->render();
        $pdf->getCanvas()->page_text(475, 810, '{PAGE_NUM} / {PAGE_COUNT}', 'DejaVu Sans', 8, [0.35, 0.4, 0.5]);
        return $pdf->output();
    }
}