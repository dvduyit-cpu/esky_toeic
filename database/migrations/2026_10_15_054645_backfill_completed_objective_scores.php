<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('exam_attempts')->whereNotNull('submitted_at')->whereNull('total_points')->orderBy('id')->chunkById(100, function ($attempts) {
            foreach ($attempts as $attempt) {
                $questions = json_decode($attempt->questions_snapshot, true);
                $answers = json_decode($attempt->answers ?? '[]', true) ?? [];
                if (! $questions || collect($questions)->contains(fn ($question) => ($question['kind'] ?? 'mcq') !== 'mcq')) {
                    continue;
                }
                $points = 0;
                foreach ($questions as $question) {
                    if (($answers[$question['id']] ?? null) === $question['correct_option']) {
                        $points += (float) ($question['max_points'] ?? 1);
                    }
                }
                DB::table('exam_attempts')->where('id', $attempt->id)->update(['total_points' => $points, 'graded_at' => $attempt->submitted_at]);
            }
        });
    }

    public function down(): void {}
};
