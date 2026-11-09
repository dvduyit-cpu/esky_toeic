<!DOCTYPE html>
<html lang="vi"><head><meta charset="utf-8"><title>Bài làm #{{ $attempt->id }}</title>
<style>
@page { margin:32px 36px 40px; }
body { font-family:'DejaVu Sans',sans-serif; font-size:10px; line-height:1.55; color:#172b43; }
h1 { font-size:19px; margin:8px 0; } h2 { font-size:12px; margin:14px 0 6px; }
.brand { font-weight:bold; color:#24476b; font-size:13px; } .muted { color:#526580; }
.meta { width:100%; border-collapse:collapse; margin:12px 0; } .meta td { padding:5px 8px; border:1px solid #c6d3e0; vertical-align:top; }
.summary { padding:12px; background:#edf2f7; border:1px solid #c6d3e0; margin:12px 0; }
.question { border-top:1px solid #c6d3e0; padding-top:4px; } .text { white-space:pre-wrap; overflow-wrap:break-word; }
.answer { padding:8px 10px; background:#f1f5f9; margin:8px 0; white-space:pre-wrap; overflow-wrap:break-word; }
.option { margin:3px 0; } .correct { color:#126248; } .incorrect { color:#9c263c; }
img { max-width:100%; max-height:420px; } .footer { border-top:1px solid #c6d3e0; padding-top:10px; margin-top:18px; font-size:8px; }
</style></head><body>
<div class="brand">{{ $branding['software_name'] }} @if($branding['organization_name']) · {{ $branding['organization_name'] }} @endif</div>
<h1>BÀI LÀM CỦA THÍ SINH</h1><div>{{ $attempt->access->exam->title }}</div>
<table class="meta"><tr><td><strong>Họ và tên:</strong> {{ $attempt->access->user->name }}</td><td><strong>Tài khoản / SBD:</strong> {{ $attempt->access->user->email }}</td></tr>
<tr><td><strong>Mã bài:</strong> #{{ $attempt->id }} · Lượt {{ $attempt->attempt_number }}</td><td><strong>Khóa thi:</strong> {{ $attempt->access->round?->name ?? '—' }}</td></tr>
<tr><td><strong>Bắt đầu:</strong> {{ $attempt->started_at->timezone('Asia/Bangkok')->format('d/m/Y H:i:s') }}</td><td><strong>Nộp bài:</strong> {{ $attempt->submitted_at->timezone('Asia/Bangkok')->format('d/m/Y H:i:s') }} (UTC+7)</td></tr></table>
<div class="summary"><strong>Trạng thái:</strong> {{ $attempt->graded_at ? 'Đã chấm' : 'Chờ chấm tự luận / bài nói' }}<br>
<strong>Số câu đúng:</strong> {{ $attempt->correct_count }} / {{ count($attempt->questions_snapshot) }} · <strong>Điểm theo câu:</strong> {{ $attempt->total_points ?? 'Chờ chấm đủ' }}
@foreach(['Listening' => $attempt->listening_result, 'Reading' => $attempt->reading_result] as $name => $section)
@if($section)<br><strong>{{ $name }}:</strong> {{ $section['correct'] }} / {{ $section['total'] }} câu đúng · {{ $section['scaled'] !== null ? $section['scaled'].' / 495 điểm' : 'Chưa quy đổi (cần đủ 100 câu)' }}@endif
@endforeach
@if($attempt->toeic_total_score !== null)<br><strong>Tổng TOEIC: {{ $attempt->toeic_total_score }} / 990</strong>@endif
</div>
@foreach($attempt->questions_snapshot as $index => $question)
@php
 $kind = $question['kind'] ?? 'mcq';
 $answer = $attempt->answers[$question['id']] ?? null;
 $max = $question['max_points'] ?? 1;
 $correct = $kind === 'mcq' && $answer === ($question['correct_option'] ?? null);
 $manual = $attempt->manual_scores ?? [];
@endphp
<div class="question"><h2>Câu {{ $index+1 }} @if(isset($question['part'])) · Part {{ $question['part'] }} @endif · {{ $max }} điểm</h2>
@if(!empty($question['passage']))<div class="text muted">{{ $question['passage'] }}</div>@endif
@if(isset($images[$question['media_path'] ?? '']))<p><img src="{{ $images[$question['media_path']] }}" alt="Tư liệu câu hỏi"></p>
@elseif(!empty($question['media_path']))<p class="muted">Tư liệu nghe: {{ basename($question['media_path']) }}</p>@endif
<div class="text">{{ $question['content'] }}</div>
@if($kind === 'mcq')
@foreach(($question['options'] ?? []) as $letter => $option)
@if(filled($option))<div class="option text">{{ $letter }}. {{ $option }}</div>@endif
@endforeach
<div class="answer"><strong>Thí sinh chọn:</strong> {{ filled($answer) ? $answer : 'Chưa trả lời' }}</div>
<p><strong>Đáp án đúng:</strong> {{ $question['correct_option'] }} · <span class="{{ $correct ? 'correct' : 'incorrect' }}">{{ $correct ? 'Đúng' : 'Sai / chưa trả lời' }}</span> · Điểm: {{ $correct ? $max : 0 }} / {{ $max }}</p>
@elseif($kind === 'speaking')
<div class="answer">{{ $answer === 'recorded' ? 'Đã nộp bài ghi âm. Nghe bản ghi trong hệ thống quản trị.' : 'Chưa có bài ghi âm.' }}</div>
<p>Điểm chấm: {{ array_key_exists($question['id'], $manual) ? $manual[$question['id']].' / '.$max : 'Chờ chấm' }}</p>
@else
<div class="answer"><strong>Bài làm:</strong><br>{{ filled($answer) ? $answer : 'Chưa trả lời' }}</div>
<p>Điểm chấm: {{ array_key_exists($question['id'], $manual) ? $manual[$question['id']].' / '.$max : 'Chờ chấm' }}</p>
@endif
</div>
@endforeach
<div class="footer">Bản xuất dành cho quản trị viên · {{ $exportedAt->format('d/m/Y H:i:s') }} (UTC+7). Điểm phản ánh dữ liệu đã lưu tại thời điểm xuất.</div>
</body></html>