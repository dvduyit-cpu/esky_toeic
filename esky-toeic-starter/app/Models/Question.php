<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Question extends Model {
 protected $fillable=['part','question_group_id','content','options','correct_option','explanation','difficulty','status','created_by'];
 protected function casts():array{return ['options'=>'array'];}
 public function exams(){return $this->belongsToMany(Exam::class,'exam_questions');}
 public function group(){return $this->belongsTo(QuestionGroup::class,'question_group_id');}
}
