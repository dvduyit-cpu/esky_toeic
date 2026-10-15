<?php

namespace App\Support;

final class ExamTrack
{
    public static function all(): array
    {
        return [
            'toeic' => ['slug' => 'toeic', 'name' => 'TOEIC', 'description' => 'Listening & Reading · Ngân hàng câu hỏi Part 1–7'],
            'vstep' => ['slug' => 'vstep', 'name' => 'VSTEP', 'description' => 'Nghe · Đọc · Viết · Nói'],
            'course' => ['slug' => 'course', 'name' => 'Thi kết thúc môn', 'description' => 'Đề thi và ngân hàng câu hỏi theo môn học'],
        ];
    }

    public static function current(): string
    {
        return request()->attributes->get('exam_track', 'toeic');
    }

    public static function adminBase(): string
    {
        return request()->attributes->get('admin_base', '');
    }

    public static function candidateBase(): string
    {
        return request()->attributes->get('candidate_base', '/exam');
    }

    public static function context(): array
    {
        return self::all()[self::current()] + ['admin_base' => self::adminBase(), 'candidate_base' => self::candidateBase()];
    }
}
