<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamRound extends Model
{
    protected $fillable = ['name', 'exam_track', 'exam_id', 'exam_date', 'access_mode', 'session_name', 'start_time', 'end_time', 'max_attempts', 'created_by'];

    protected function casts(): array
    {
        return ['exam_date' => 'date:Y-m-d'];
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }
}
