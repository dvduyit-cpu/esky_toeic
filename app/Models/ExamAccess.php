<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamAccess extends Model
{
    protected $fillable = ['user_id', 'exam_id', 'exam_date', 'access_mode', 'session_name', 'start_time', 'end_time', 'max_attempts', 'is_active', 'created_by'];

    public function opensAt(): Carbon
    {
        return Carbon::parse($this->exam_date->toDateString().' '.($this->access_mode === 'session' ? $this->start_time : '00:00:00'), 'Asia/Bangkok');
    }

    public function closesAt(): Carbon
    {
        return Carbon::parse($this->exam_date->toDateString().' '.($this->access_mode === 'session' ? $this->end_time : '23:59:59'), 'Asia/Bangkok');
    }

    protected function casts(): array
    {
        return ['exam_date' => 'date:Y-m-d', 'is_active' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }
}
