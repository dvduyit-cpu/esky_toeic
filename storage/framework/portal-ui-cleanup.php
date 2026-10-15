<?php
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$meta = json_decode(file_get_contents(__DIR__.'/portal-check-data.json'), true, 512, JSON_THROW_ON_ERROR);
if (!str_starts_with($meta['marker'], 'ui-portal-check-') || $meta['email'] !== $meta['marker'].'@example.test') { throw new RuntimeException('Invalid test identity.'); }
Illuminate\Support\Facades\DB::transaction(function () use ($meta) {
    $user = App\Models\User::where('email', $meta['email'])->where('name', $meta['marker'])->first();
    if ($user) {
        $accesses = App\Models\ExamAccess::where('user_id', $user->id)->pluck('id');
        App\Models\ExamAttempt::whereIn('exam_access_id', $accesses)->delete();
        App\Models\ExamAccess::whereIn('id', $accesses)->delete();
        Illuminate\Support\Facades\DB::table('sessions')->where('user_id', $user->id)->delete();
        $user->delete();
    }
    foreach (App\Models\Exam::where('title', $meta['marker'])->get() as $exam) { $exam->questions()->detach(); $exam->delete(); }
    App\Models\Question::where('content', $meta['marker'])->delete();
});
echo "Temporary browser test data removed.\n";
