<?php
namespace App\Http\Controllers;
use App\Models\{Exam,Question};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
class ExamController extends Controller {
 private function staff():void{abort_unless(auth()->user()->isStaff(),403);}
 public function index(){ $this->staff();return Inertia::render('Exams',['exams'=>Exam::withCount('questions')->latest()->paginate(15)]);}
 public function store(Request $r){$this->staff();$v=$r->validate(['title'=>'required|string|max:255','description'=>'nullable|string','duration_minutes'=>'required|integer|between:5,180']);Exam::create($v+['created_by'=>auth()->id(),'status'=>'draft']);return back()->with('success','Đã tạo đề nháp.');}
 public function attach(Request $r,Exam $exam){$this->staff();abort_unless(auth()->user()->role==='admin'||$exam->created_by===auth()->id(),403);abort_unless($exam->status==='draft',422);$v=$r->validate(['question_id'=>'required|exists:questions,id']);$q=Question::findOrFail($v['question_id']);if($exam->questions()->where('questions.id',$q->id)->exists())return back()->withErrors(['question_id'=>'Câu hỏi đã nằm trong đề.']);$pos=((int)$exam->questions()->max('exam_questions.position'))+1;$exam->questions()->attach($q->id,['position'=>$pos]);return back()->with('success','Đã thêm câu hỏi.');}
}
