<?php
namespace App\Http\Controllers;
use App\Models\{Question,QuestionGroup};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
class QuestionController extends Controller {
 private function staff():void{abort_unless(auth()->user()->isStaff(),403);}
 public function index(Request $r){$this->staff();return Inertia::render('Questions',['questions'=>Question::query()->select('id','part','content','difficulty','status','question_group_id','created_by')->when($r->query('part'),fn($q,$v)=>$q->where('part',$v))->latest()->paginate(15)->withQueryString(),'groups'=>QuestionGroup::orderByDesc('id')->limit(100)->get(['id','part','title']),'part'=>$r->query('part','')]);}
 public function storeGroup(Request $r){$this->staff();$v=$r->validate(['part'=>'required|integer|in:1,2,3,4,6,7','title'=>'nullable|string|max:255','passage'=>'nullable|string|max:60000','media_path'=>'nullable|string|max:500']);QuestionGroup::create($v+['created_by'=>auth()->id()]);return back()->with('success','Đã thêm nhóm câu hỏi.');}
 public function store(Request $r){$this->staff();$v=$r->validate(['part'=>'required|integer|between:1,7','question_group_id'=>'nullable|integer|exists:question_groups,id','content'=>'required|string|max:10000','options'=>'required|array','options.A'=>'required|string|max:10000','options.B'=>'required|string|max:10000','options.C'=>'nullable|string|max:10000','options.D'=>'nullable|string|max:10000','correct_option'=>['required',Rule::in(['A','B','C','D'])],'explanation'=>'nullable|string|max:20000','difficulty'=>['required',Rule::in(['easy','medium','hard'])]]);
 if(!array_key_exists($v['correct_option'],$v['options'])||!filled($v['options'][$v['correct_option']]))return back()->withErrors(['correct_option'=>'Đáp án đúng phải có nội dung.']);
 if($r->filled('question_group_id') && QuestionGroup::findOrFail($v['question_group_id'])->part!== (int)$v['part'])return back()->withErrors(['question_group_id'=>'Nhóm câu hỏi không cùng Part.']);
 if(in_array((int)$v['part'],[3,4,6,7],true)&&empty($v['question_group_id']))return back()->withErrors(['question_group_id'=>'Part này cần gắn câu hỏi vào nhóm tư liệu.']);
 Question::create($v+['created_by'=>auth()->id(),'status'=>'draft']);return back()->with('success','Đã tạo câu hỏi nháp.');}
 public function destroy(Question $question){$this->staff();abort_unless(auth()->user()->role==='admin'||$question->created_by===auth()->id(),403);abort_if($question->exams()->exists(),422,'Câu hỏi đã sử dụng trong đề.');$question->delete();return back();}
}
