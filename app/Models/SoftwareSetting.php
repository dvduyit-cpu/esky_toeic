<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SoftwareSetting extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    public static function defaults(): array
    {
        return [
            'software_name' => 'E-SKY EXAM', 'tagline' => 'HỆ THỐNG KHẢO THÍ', 'organization_name' => '',
            'footer_text' => 'E-SKY EXAM · Nền tảng khảo thí', 'footer_note' => 'TOEIC · VSTEP · Thi kết thúc môn',
            'login_heading' => "Mỗi ngày học.\nMột bước tiến.\nCùng chinh phục mục tiêu.",
            'login_description' => 'Không gian chung để quản lý học liệu, xây dựng ngân hàng câu hỏi và chuẩn bị cho những kỳ thi.',
            'support_email' => '', 'primary_color' => '#2563eb', 'font_family' => 'system', 'font_size' => 15, 'corner_radius' => 10, 'logo_path' => null,
        ];
    }

    public static function current(): array
    {
        return array_replace(self::defaults(), self::find(1)?->data ?? []);
    }

    public static function publicSettings(): array
    {
        $data = self::current();
        $path = $data['logo_path'];
        unset($data['logo_path']);
        $data['logo_url'] = $path ? '/branding/logo/'.basename($path) : null;
        $hex = ltrim($data['primary_color'], '#');
        $rgb = array_map(fn ($channel) => hexdec($channel) / 255, str_split($hex, 2));
        $linear = array_map(fn ($v) => $v <= 0.04045 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4, $rgb);
        $luminance = $linear[0] * 0.2126 + $linear[1] * 0.7152 + $linear[2] * 0.0722;
        $data['on_primary'] = $luminance > 0.179 ? '#111827' : '#ffffff';
        $data['accent_color'] = $luminance > 0.179 ? sprintf('#%02x%02x%02x', ...array_map(fn ($v) => (int) round($v * 255 * 0.55), $rgb)) : $data['primary_color'];

        return $data;
    }
}
