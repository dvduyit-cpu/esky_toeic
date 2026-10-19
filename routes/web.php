<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CandidateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExamAccessController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\ResultController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/branding/logo/{version}', [SettingController::class, 'logo']);

Route::get('/', fn () => redirect('/exam/login'));
Route::get('/login', fn () => redirect('/exam/login'))->name('login');
Route::get('/admin/login', [AuthController::class, 'show'])->name('admin.login');
Route::post('/admin/login', [AuthController::class, 'login'])->middleware('guest')->name('admin.login.submit');
Route::get('/exam/login', [AuthController::class, 'choose'])->name('exam.login');
Route::post('/exam/login', [AuthController::class, 'login'])->middleware(['guest', 'track:toeic'])->name('exam.login.submit');
foreach (['toeic', 'vstep', 'course'] as $track) {
    Route::get('/exam/'.$track.'/login', [AuthController::class, 'show'])->middleware('track:'.$track);
    Route::post('/exam/'.$track.'/login', [AuthController::class, 'login'])->middleware(['guest', 'track:'.$track]);
}
$management = function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users/import-template', [UserController::class, 'importTemplate']);
    Route::post('/users/import', [UserController::class, 'import']);
    Route::post('/exam-rounds', [UserController::class, 'storeRound']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);
    Route::get('/exam-accesses', [ExamAccessController::class, 'index']);
    Route::post('/exam-accesses', [ExamAccessController::class, 'store']);
    Route::put('/exam-accesses/{examAccess}', [ExamAccessController::class, 'update']);
    Route::delete('/exam-accesses/{examAccess}', [ExamAccessController::class, 'destroy']);
    Route::get('/questions', [QuestionController::class, 'index']);
    Route::post('/questions', [QuestionController::class, 'store']);
    Route::put('/questions/{question}', [QuestionController::class, 'update']);
    Route::delete('/questions/{question}', [QuestionController::class, 'destroy']);
    Route::post('/question-groups', [QuestionController::class, 'storeGroup']);
    Route::put('/question-groups/{questionGroup}', [QuestionController::class, 'updateGroup']);
    Route::delete('/question-groups/{questionGroup}', [QuestionController::class, 'destroyGroup']);
    Route::get('/exams', [ExamController::class, 'index']);
    Route::post('/exams', [ExamController::class, 'store']);
    Route::put('/exams/{exam}', [ExamController::class, 'update']);
    Route::delete('/exams/{exam}', [ExamController::class, 'destroy']);
    Route::post('/exams/{exam}/questions', [ExamController::class, 'attach']);
    Route::delete('/exams/{exam}/questions/{question}', [ExamController::class, 'detach']);
    Route::put('/exams/{exam}/status', [ExamController::class, 'status']);
    Route::get('/results', [ResultController::class, 'index']);
    Route::get('/results/export', [ResultController::class, 'export']);
    Route::put('/results/{examAttempt}', [ResultController::class, 'grade']);
    Route::get('/results/{examAttempt}/recordings/{questionId}', [ResultController::class, 'recording']);
};
$candidate = function () {
    Route::get('', [CandidateController::class, 'index']);
    Route::post('/accesses/{examAccess}/start', [CandidateController::class, 'start']);
    Route::get('/attempts/{examAttempt}', [CandidateController::class, 'show']);
    Route::put('/attempts/{examAttempt}/answers', [CandidateController::class, 'save']);
    Route::post('/attempts/{examAttempt}/submit', [CandidateController::class, 'submit']);
    Route::post('/attempts/{examAttempt}/recordings/{questionId}', [CandidateController::class, 'record']);
    Route::get('/attempts/{examAttempt}/recordings/{questionId}', [CandidateController::class, 'recording']);
};
Route::middleware(['auth', 'active.user'])->group(function () use ($management, $candidate) {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::middleware('portal:admin')->group(function () use ($management) {
        Route::get('/settings', [SettingController::class, 'index'])->name('settings');
        Route::post('/settings', [SettingController::class, 'update']);
        Route::get('/admin', fn () => redirect('/dashboard'));
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::middleware('track:legacy')->group($management);
        foreach (['toeic', 'vstep', 'course'] as $track) {
            Route::prefix('/manage/'.$track)->middleware('track:'.$track)->group($management);
        }
        Route::prefix('/manage/course')->middleware('track:course')->group(function () {
            Route::get('/subjects', [SubjectController::class, 'index']);
            Route::post('/subjects', [SubjectController::class, 'store']);
            Route::put('/subjects/{subject}', [SubjectController::class, 'update']);
            Route::delete('/subjects/{subject}', [SubjectController::class, 'destroy']);
        });
    });
    Route::middleware('portal:exam')->group(function () use ($candidate) {
        Route::prefix('/exam')->middleware('track:legacy')->group($candidate);
        foreach (['toeic', 'vstep', 'course'] as $track) {
            Route::prefix('/exam/'.$track)->middleware('track:'.$track)->group($candidate);
        }
    });
});
