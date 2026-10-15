<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionGroup extends Model
{
    protected $fillable = ['part', 'title', 'passage', 'media_path', 'created_by', 'exam_track', 'subject_id'];

    public function questions()
    {
        return $this->hasMany(Question::class);
    }
}
