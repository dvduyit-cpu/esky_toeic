<?php
namespace App\Http\Controllers;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
class UserController extends Controller {
 private function admin():void{abort_unless(auth()->user()->role==='admin',403);}
 public function index(Request $r){$this->admin();return Inertia::render('Users',['users'=>User::query()->when($r->query('search'),fn($q,$v)=>$q->where(fn($query)=>$query->where('name','like','%'.$v.'%')->orWhere('email','like','%'.$v.'%')))->latest()->paginate(15)->withQueryString(),'search'=>$r->query('search','')]);}
 public function store(Request $r){$this->admin();$v=$r->validate(['name'=>'required|string|max:255','email'=>'required|email|max:255|unique:users,email','password'=>'required|string|min:12','role'=>['required',Rule::in(['admin','teacher','student'])]]);User::create($v+['is_active'=>true]);return back()->with('success','Đã tạo tài khoản.');}
 public function update(Request $r,User $user){$this->admin();$v=$r->validate(['name'=>'required|string|max:255','role'=>['required',Rule::in(['admin','teacher','student'])],'is_active'=>'required|boolean','password'=>'nullable|string|min:12']);if($user->id===auth()->id()&&($v['role']!=='admin'||!$v['is_active']))return back()->withErrors(['role'=>'Không thể tự hạ quyền hoặc khóa chính mình.']);if(empty($v['password']))unset($v['password']);$user->update($v);return back()->with('success','Đã cập nhật tài khoản.');}
}
