<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamAccess;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TrackIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function question(string $track = 'vstep', array $extra = []): Question
    {
        return Question::create(array_merge(['exam_track' => $track, 'part' => 5, 'kind' => 'mcq', 'skill' => $track === 'vstep' ? 'reading' : null, 'content' => 'Question '.$track, 'options' => ['A' => 'Yes', 'B' => 'No'], 'correct_option' => 'A', 'max_points' => 2, 'created_by' => $this->admin->id], $extra));
    }

    private function exam(string $track = 'vstep', array $extra = []): Exam
    {
        return Exam::create(array_merge(['exam_track' => $track, 'title' => 'Exam '.$track, 'duration_minutes' => 60, 'status' => 'published', 'created_by' => $this->admin->id], $extra));
    }

    private function access(User $student, Exam $exam): ExamAccess
    {
        return ExamAccess::create(['user_id' => $student->id, 'exam_id' => $exam->id, 'exam_date' => now('Asia/Bangkok')->toDateString(), 'access_mode' => 'day', 'max_attempts' => 2, 'created_by' => $this->admin->id]);
    }

    public function test_banks_and_candidate_lists_are_separate_by_track(): void
    {
        foreach (['toeic', 'vstep', 'course'] as $track) {
            $this->question($track);
            User::factory()->create(['exam_track' => $track]);
        }
        foreach (['toeic', 'vstep', 'course'] as $track) {
            $this->actingAs($this->admin)->get('/manage/'.$track.'/questions')->assertInertia(fn (Assert $page) => $page->component('Questions')->has('questions.data', 1)->where('questions.data.0.exam_track', $track));
            $this->get('/manage/'.$track.'/users')->assertInertia(fn (Assert $page) => $page->component('Users')->has('users.data', 1)->where('users.data.0.exam_track', $track));
        }
    }

    public function test_same_email_can_have_independent_accounts_and_passwords_for_each_track(): void
    {
        foreach (['toeic', 'vstep', 'course'] as $track) {
            $this->actingAs($this->admin)->post('/manage/'.$track.'/users', ['name' => 'Student '.$track, 'email' => 'same@example.test', 'password' => 'PasswordFor-'.$track.'123', 'role' => 'student'])->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('users', 4);
        auth()->logout();
        $this->post('/exam/vstep/login', ['email' => 'same@example.test', 'password' => 'PasswordFor-toeic123'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/exam/vstep/login', ['email' => 'same@example.test', 'password' => 'PasswordFor-vstep123'])->assertRedirect('/exam/vstep');
        $this->get('/exam/toeic')->assertForbidden();
        $this->get('/exam/course')->assertForbidden();
    }

    public function test_cross_track_ids_cannot_be_updated_deleted_attached_or_granted(): void
    {
        $foreign = $this->question('toeic');
        $exam = $this->exam('vstep', ['status' => 'draft']);
        $student = User::factory()->create(['exam_track' => 'toeic']);
        $this->actingAs($this->admin)->delete('/manage/vstep/questions/'.$foreign->id)->assertNotFound();
        $this->put('/manage/vstep/users/'.$student->id, ['name' => 'Changed', 'role' => 'student', 'is_active' => false])->assertNotFound();
        $this->post('/manage/vstep/exams/'.$exam->id.'/questions', ['question_id' => $foreign->id])->assertSessionHasErrors('question_id');
        $exam->update(['status' => 'published']);
        $this->post('/manage/vstep/exam-accesses', ['user_id' => $student->id, 'exam_id' => $exam->id, 'exam_date' => now('Asia/Bangkok')->toDateString(), 'access_mode' => 'day', 'max_attempts' => 1])->assertSessionHasErrors('user_id');
        $vstudent = User::factory()->create(['exam_track' => 'vstep']);
        $texam = $this->exam('toeic');
        $this->post('/manage/vstep/exam-accesses', ['user_id' => $vstudent->id, 'exam_id' => $texam->id, 'exam_date' => now('Asia/Bangkok')->toDateString(), 'access_mode' => 'day', 'max_attempts' => 1])->assertSessionHasErrors('exam_id');
        $this->assertSame('toeic', $foreign->fresh()->exam_track);
        $this->assertTrue($student->fresh()->is_active);
    }

    public function test_teacher_only_manages_assigned_tracks(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'managed_tracks' => ['vstep']]);
        $this->actingAs($teacher)->get('/manage/vstep/questions')->assertOk();
        $this->get('/manage/toeic/questions')->assertForbidden();
        $this->get('/manage/course/subjects')->assertForbidden();
        $this->get('/manage/vstep/users')->assertForbidden();
    }

    public function test_subjects_and_questions_must_match_course_exam(): void
    {
        $one = Subject::create(['code' => 'ONE', 'name' => 'One']);
        $two = Subject::create(['code' => 'TWO', 'name' => 'Two']);
        $q = $this->question('course', ['subject_id' => $two->id]);
        $exam = $this->exam('course', ['subject_id' => $one->id, 'status' => 'draft']);
        $this->actingAs($this->admin)->post('/manage/course/exams/'.$exam->id.'/questions', ['question_id' => $q->id])->assertSessionHasErrors('question_id');
        $this->delete('/manage/course/subjects/'.$two->id)->assertSessionHasErrors('delete');
        $this->assertDatabaseHas('subjects', ['id' => $two->id]);
    }

    public function test_questions_can_be_edited_and_deleted_until_used_in_an_exam(): void
    {
        $q = $this->question();
        $this->actingAs($this->admin)->put('/manage/vstep/questions/'.$q->id, ['part' => 5, 'kind' => 'essay', 'skill' => 'writing', 'content' => 'Updated prompt', 'max_points' => 10, 'difficulty' => 'medium'])->assertSessionHasNoErrors();
        $this->assertSame('essay', $q->fresh()->kind);
        $exam = $this->exam();
        $exam->questions()->attach($q->id, ['position' => 1]);
        $this->delete('/manage/vstep/questions/'.$q->id)->assertSessionHasErrors('delete');
        $unused = $this->question();
        $this->delete('/manage/vstep/questions/'.$unused->id)->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('questions', ['id' => $unused->id]);
    }

    public function test_vstep_essay_is_saved_submitted_and_manually_graded_with_server_limits(): void
    {
        $student = User::factory()->create(['exam_track' => 'vstep']);
        $q = $this->question('vstep', ['kind' => 'essay', 'skill' => 'writing', 'options' => [], 'correct_option' => '', 'max_points' => 10]);
        $exam = $this->exam();
        $exam->questions()->attach($q->id, ['position' => 1]);
        $access = $this->access($student, $exam);
        $this->actingAs($student)->post('/exam/vstep/accesses/'.$access->id.'/start')->assertRedirect('/exam/vstep/attempts/1');
        $this->putJson('/exam/vstep/attempts/1/answers', ['answers' => [$q->id => 'My written answer.']])->assertOk();
        $this->post('/exam/vstep/attempts/1/submit', ['answers' => [$q->id => 'My written answer.']])->assertRedirect();
        $attempt = ExamAttempt::first();
        $this->assertNull($attempt->total_points);
        $this->get('/exam/vstep/attempts/1')->assertInertia(fn (Assert $page) => $page->missing('questions.0.correct_option')->where('attempt.graded_at', null));
        $this->actingAs($this->admin)->put('/manage/vstep/results/1', ['scores' => [$q->id => 11]])->assertSessionHasErrors('scores');
        $this->put('/manage/vstep/results/1', ['scores' => [$q->id => 8.5]])->assertSessionHasNoErrors();
        $this->assertSame(8.5, $attempt->fresh()->total_points);
        $this->assertNotNull($attempt->fresh()->graded_at);
        $this->put('/manage/toeic/results/1', ['scores' => [$q->id => 10]])->assertNotFound();
    }

    public function test_recordings_are_private_and_only_accepted_for_active_speaking_attempts(): void
    {
        Storage::fake('local');
        $student = User::factory()->create(['exam_track' => 'vstep']);
        $q = $this->question('vstep', ['kind' => 'speaking', 'skill' => 'speaking', 'options' => [], 'correct_option' => '']);
        $exam = $this->exam();
        $exam->questions()->attach($q->id, ['position' => 1]);
        $access = $this->access($student, $exam);
        $this->actingAs($student)->post('/exam/vstep/accesses/'.$access->id.'/start');
        $this->putJson('/exam/vstep/attempts/1/answers', ['answers' => [$q->id => 'recorded']])->assertUnprocessable();
        $this->postJson('/exam/vstep/attempts/1/recordings/'.$q->id, ['recording' => UploadedFile::fake()->create('voice.webm', 100, 'audio/webm')])->assertOk();
        $attempt = ExamAttempt::first();
        Storage::disk('local')->assertExists($attempt->recordings[$q->id]);
        $this->get('/exam/vstep/attempts/1/recordings/'.$q->id)->assertOk();
        $other = User::factory()->create(['exam_track' => 'vstep']);
        $this->actingAs($other)->get('/exam/vstep/attempts/1/recordings/'.$q->id)->assertForbidden();
        $this->actingAs($student)->post('/exam/vstep/attempts/1/submit', ['answers' => [$q->id => 'recorded']]);
        $this->postJson('/exam/vstep/attempts/1/recordings/'.$q->id, ['recording' => UploadedFile::fake()->create('voice.webm', 100, 'audio/webm')])->assertStatus(409);
    }

    public function test_used_accounts_and_grants_are_retained_but_unused_entries_can_be_deleted(): void
    {
        $student = User::factory()->create(['exam_track' => 'vstep']);
        $exam = $this->exam();
        $q = $this->question();
        $exam->questions()->attach($q->id, ['position' => 1]);
        $access = $this->access($student, $exam);
        $this->actingAs($student)->post('/exam/vstep/accesses/'.$access->id.'/start');
        $this->actingAs($this->admin)->delete('/manage/vstep/exam-accesses/'.$access->id)->assertSessionHasErrors('delete');
        $this->delete('/manage/vstep/users/'.$student->id)->assertSessionHasErrors('delete');
        $unused = User::factory()->create(['exam_track' => 'vstep']);
        $this->delete('/manage/vstep/users/'.$unused->id)->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('users', ['id' => $unused->id]);
    }

    public function test_legacy_results_are_migrated_without_marking_written_answers_as_graded(): void
    {
        $student = User::factory()->create();
        $access = $this->access($student, $this->exam('toeic'));
        $legacy = ExamAttempt::create(['exam_access_id' => $access->id, 'attempt_number' => 1, 'questions_snapshot' => [['id' => 7, 'correct_option' => 'A']], 'answers' => [7 => 'A'], 'started_at' => now(), 'expires_at' => now()->addHour(), 'submitted_at' => now(), 'correct_count' => 1]);
        $written = ExamAttempt::create(['exam_access_id' => $access->id, 'attempt_number' => 2, 'questions_snapshot' => [['id' => 8, 'kind' => 'essay', 'correct_option' => '']], 'answers' => [8 => 'An essay.'], 'started_at' => now(), 'expires_at' => now()->addHour(), 'submitted_at' => now(), 'correct_count' => 0]);
        $migration = require database_path('migrations/2026_10_15_054645_backfill_completed_objective_scores.php');
        $migration->up();
        $this->assertSame(1.0, $legacy->fresh()->total_points);
        $this->assertNotNull($legacy->fresh()->graded_at);
        $this->assertNull($written->fresh()->total_points);
        $this->assertNull($written->fresh()->graded_at);
    }
}
