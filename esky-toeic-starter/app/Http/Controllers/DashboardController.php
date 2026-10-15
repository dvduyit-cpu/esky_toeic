<?php
namespace App\Http\Controllers;
use App\Models\{User,Question,Exam};
use Inertia\Inertia;
class DashboardController extends Controller {
 public function index(){ $user=auth()->user();return Inertia::render('Dashboard',['stats'=>$user->role==='admin'?['users'=>User::count(),'questions'=>Question::count(),'exams'=>Exam::count()]:['questions'=>$user->isStaff()?Question::where('created_by',$user->id)->count():0,'exams'=>$user->isStaff()?Exam::where('created_by',$user->id)->count():0]]); }
}
