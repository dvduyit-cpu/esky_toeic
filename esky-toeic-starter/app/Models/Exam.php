<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Exam extends Model {
 protected $fillable=['title','description','duration_minutes','status','created_by'];
 public function questions(){return $this->belongsToMany(Question::class,'exam_questions')->withPivot('position')->orderByPivot('position');}
}
