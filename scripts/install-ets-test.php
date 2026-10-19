<?php

// Run locally after extracting the supplied documents. No sample data is generated.
use App\Models\Exam;
use App\Models\Question;
use App\Models\QuestionGroup;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$directory = storage_path('app/imports/ets-2026-test-1');
$data = json_decode(file_get_contents($directory.'/parsed.json'), true, flags: JSON_THROW_ON_ERROR);
if (count($data['questions']) !== 200 || array_column($data['questions'], 'number') !== range(1, 200)) {
    throw new RuntimeException('Expected exactly 200 ordered questions.');
}
foreach ($data['groups'] as $group) {
    if ($group['media_path'] && !is_file(storage_path('app/public/'.$group['media_path']))) {
        throw new RuntimeException('Missing passage image.');
    }
}
$admin = DB::table('users')->where('role', 'admin')->orderBy('id')->value('id');
if (!$admin) throw new RuntimeException('An administrator is required.');

$summary = DB::transaction(function () use ($data, $directory, $admin) {
    // Preserve the exact original rows privately before deleting any demo records.
    $demoExams = DB::table('exams')->where('exam_track', 'toeic')->where('title', 'like', '%demo%')->pluck('id');
    $demoUsers = DB::table('users')->where('exam_track', 'toeic')->where('role', 'student')->where('email', 'like', 'toeic.demo%@esky.local')->pluck('id');
    $demoQuestions = DB::table('questions')->where('exam_track', 'toeic')->where('content', 'like', '[DEMO TOEIC %')->pluck('id');
    $demoGroups = DB::table('question_groups')->where('exam_track', 'toeic')->where('title', 'like', 'DEMO TOEIC - %')->pluck('id');
    $demoAccesses = DB::table('exam_accesses')->where(fn ($q) => $q->whereIn('exam_id', $demoExams)->orWhereIn('user_id', $demoUsers))->pluck('id');
    $backup = [];
    foreach ([
        'users' => DB::table('users')->whereIn('id', $demoUsers),
        'exams' => DB::table('exams')->whereIn('id', $demoExams),
        'questions' => DB::table('questions')->whereIn('id', $demoQuestions),
        'question_groups' => DB::table('question_groups')->whereIn('id', $demoGroups),
        'exam_questions' => DB::table('exam_questions')->where(fn ($q) => $q->whereIn('exam_id', $demoExams)->orWhereIn('question_id', $demoQuestions)),
        'exam_accesses' => DB::table('exam_accesses')->whereIn('id', $demoAccesses),
        'exam_attempts' => DB::table('exam_attempts')->whereIn('exam_access_id', $demoAccesses),
        'sessions' => DB::table('sessions')->whereIn('user_id', $demoUsers),
    ] as $table => $query) $backup[$table] = $query->get()->all();
    $backupPath = $directory.'/demo-backup-'.now()->format('Ymd-His').'.json';
    if (file_put_contents($backupPath, json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) === false) {
        throw new RuntimeException('Backup failed; no changes made.');
    }

    $full = Exam::where('exam_track', 'toeic')->where('title', $data['title'])->first();
    if (!$full) {
        $groupIds = [];
        foreach ($data['groups'] as $key => $group) {
            $groupIds[$key] = QuestionGroup::create([
                'exam_track' => 'toeic', 'part' => $group['part'],
                'title' => 'ETS 2026 Test 1 · '.$group['title'],
                'passage' => $group['passage'] ?: null, 'media_path' => $group['media_path'], 'created_by' => $admin,
            ])->id;
        }
        $full = Exam::create(['title' => $data['title'], 'exam_track' => 'toeic', 'duration_minutes' => 120, 'status' => 'published', 'created_by' => $admin,
            'description' => 'Đủ 200 câu Part 1–7, đáp án và tư liệu từ hai file Word được cung cấp. Audio Listening để trống theo yêu cầu. Mỗi câu 1 điểm, chưa quy đổi thang điểm TOEIC.']);
        foreach ($data['questions'] as $row) {
            $question = Question::create([
                'exam_track' => 'toeic', 'part' => $row['part'], 'kind' => 'mcq',
                'skill' => $row['part'] <= 4 ? 'listening' : 'reading',
                'content' => $row['number'].'. '.$row['content'], 'options' => $row['options'],
                'correct_option' => $row['correct_option'], 'question_group_id' => $row['group'] ? $groupIds[$row['group']] : null,
                'difficulty' => 'medium', 'status' => 'draft', 'max_points' => 1, 'created_by' => $admin,
            ]);
            $full->questions()->attach($question->id, ['position' => $row['number']]);
        }
    }
    if ($full->questions()->count() !== 200) throw new RuntimeException('Incomplete import; rolling back.');
    // Never remove demo-marked questions that are attached to a real exam.
    $shared = DB::table('exam_questions')->whereIn('question_id', $demoQuestions)->whereNotIn('exam_id', $demoExams)->exists();
    if ($shared) throw new RuntimeException('Demo questions are shared with a real exam; cleanup aborted.');
    DB::table('exam_attempts')->whereIn('exam_access_id', $demoAccesses)->delete();
    DB::table('exam_accesses')->whereIn('id', $demoAccesses)->delete();
    DB::table('exam_questions')->whereIn('exam_id', $demoExams)->delete();
    DB::table('exams')->whereIn('id', $demoExams)->delete();
    DB::table('questions')->whereIn('id', $demoQuestions)->delete();
    DB::table('question_groups')->whereIn('id', $demoGroups)->whereNotIn('id', DB::table('questions')->whereNotNull('question_group_id')->select('question_group_id'))->delete();
    DB::table('sessions')->whereIn('user_id', $demoUsers)->delete();
    DB::table('users')->whereIn('id', $demoUsers)->delete();
    return ['full_exam_id' => $full->id, 'full_status' => $full->status, 'questions' => 200,
        'parts' => $full->questions()->get()->groupBy('part')->map->count(),
        'deleted_demo_exams' => $demoExams->count(), 'deleted_demo_questions' => $demoQuestions->count(), 'deleted_demo_accounts' => $demoUsers->count(),
        'backup' => $backupPath];
});
echo json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
