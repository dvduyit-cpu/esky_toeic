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

    public function getListeningResultAttribute(): ?array
    {
        return $this->sectionResult(1, 4, \App\Support\ToeicListeningScore::class);
    }

    public function getReadingResultAttribute(): ?array
    {
        return $this->sectionResult(5, 7, \App\Support\ToeicReadingScore::class);
    }

    public function getToeicTotalScoreAttribute(): ?int
    {
        $listening = $this->listening_result['scaled'] ?? null;
        $reading = $this->reading_result['scaled'] ?? null;
        return count($this->questions_snapshot ?? []) === 200 && $listening !== null && $reading !== null ? $listening + $reading : null;
    }

    private function sectionResult(int $firstPart, int $lastPart, string $conversion): ?array
    {
        if (!$this->submitted_at || $this->access->exam->exam_track !== 'toeic') return null;
        $questions = collect($this->questions_snapshot)->filter(fn ($q) =>
            isset($q['part']) && (int) $q['part'] >= $firstPart && (int) $q['part'] <= $lastPart && ($q['kind'] ?? 'mcq') === 'mcq');
        if ($questions->isEmpty()) return null;
        $correct = $questions->filter(fn ($q) => ($this->answers[$q['id']] ?? null) === $q['correct_option'])->count();
        return [
            'correct' => $correct, 'total' => $questions->count(),
            'scaled' => $questions->count() === 100 ? $conversion::fromRaw($correct) : null,
        ];
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
