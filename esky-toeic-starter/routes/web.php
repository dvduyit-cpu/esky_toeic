<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{AuthController,DashboardController,UserController,QuestionController,ExamController};
Route::middleware('guest')->group(function(){Route::get('/login',[AuthController::class,'show'])->name('login');Route::post('/login',[AuthController::class,'login'])->name('login.submit');});
Route::middleware(['auth','active.user'])->group(function(){
 Route::get('/',fn()=>redirect('/dashboard'));
 Route::post('/logout',[AuthController::class,'logout'])->name('logout');
 Route::get('/dashboard',[DashboardController::class,'index'])->name('dashboard');
 Route::get('/users',[UserController::class,'index']);Route::post('/users',[UserController::class,'store']);Route::put('/users/{user}',[UserController::class,'update']);
 Route::get('/questions',[QuestionController::class,'index']);Route::post('/questions',[QuestionController::class,'store']);Route::post('/question-groups',[QuestionController::class,'storeGroup']);Route::delete('/questions/{question}',[QuestionController::class,'destroy']);
 Route::get('/exams',[ExamController::class,'index']);Route::post('/exams',[ExamController::class,'store']);Route::post('/exams/{exam}/questions',[ExamController::class,'attach']);
});
