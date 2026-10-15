<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamAttempt extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['questions_snapshot', 'recordings'];

    protected function casts(): array
    {
        return ['manual_scores' => 'array', 'recordings' => 'array', 'total_points' => 'float', 'graded_at' => 'datetime', 'questions_snapshot' => 'array', 'answers' => 'array', 'started_at' => 'datetime', 'expires_at' => 'datetime', 'submitted_at' => 'datetime'];
    }

    public function access(): BelongsTo
    {
        return $this->belongsTo(ExamAccess::class, 'exam_access_id');
    }

    public function finish(): void
    {
        if ($this->submitted_at) {
            return;
        }
        $this->submitted_at = now();
        $this->calculateScore();
    }

    public function calculateScore(): void
    {
        $correct = 0;
        $points = 0;
        $pending = false;
        foreach ($this->questions_snapshot as $question) {
            if (($question['kind'] ?? 'mcq') === 'mcq') {
                if (($this->answers[$question['id']] ?? null) === $question['correct_option']) {
                    $correct++;
                    $points += (float) ($question['max_points'] ?? 1);
                }
            } else {
                if (! array_key_exists($question['id'], $this->manual_scores ?? [])) {
                    $pending = true;
                } else {
                    $points += (float) $this->manual_scores[$question['id']];
                }
            }
        }
        $this->correct_count = $correct;
        $this->total_points = $pending ? null : $points;
        $this->graded_at = $pending ? null : now();
        $this->save();
    }
}
