<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamAccess;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExamAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $student;

    private Exam $exam;

    private Question $question;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(3, 0)); // 10:00 in Vietnam.
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->student = User::factory()->create(['role' => 'student']);
        $this->exam = Exam::create(['title' => 'Reading test', 'duration_minutes' => 60, 'status' => 'published', 'created_by' => $this->admin->id]);
        $this->question = Question::create(['part' => 5, 'content' => 'Choose the correct answer.', 'options' => ['A' => 'One', 'B' => 'Two'], 'correct_option' => 'A', 'created_by' => $this->admin->id]);
        $this->exam->questions()->attach($this->question->id, ['position' => 1]);
    }

    private function grant(array $attributes = []): ExamAccess
    {
        return ExamAccess::create(array_merge(['user_id' => $this->student->id, 'exam_id' => $this->exam->id, 'exam_date' => '2026-10-15', 'access_mode' => 'day', 'max_attempts' => 1, 'created_by' => $this->admin->id], $attributes));
    }

    private function grantData(array $attributes = []): array
    {
        return array_merge(['user_id' => $this->student->id, 'exam_id' => $this->exam->id, 'exam_date' => '2026-10-15', 'access_mode' => 'day', 'max_attempts' => 2], $attributes);
    }

    public function test_only_admin_can_grant_day_or_multiple_separate_sessions(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $this->actingAs($teacher)->post('/exam-accesses', $this->grantData())->assertForbidden();
        $this->actingAs($this->student)->post('/exam-accesses', $this->grantData())->assertForbidden();
        $this->actingAs($this->admin)->post('/exam-accesses', $this->grantData())->assertSessionHasNoErrors();
        $this->post('/exam-accesses', $this->grantData())->assertSessionHasErrors('exam_date');
        foreach ([['08:00', '10:00'], ['14:00', '16:00']] as [$start, $end]) {
            $this->post('/exam-accesses', $this->grantData(['access_mode' => 'session', 'session_name' => 'Ca '.$start, 'start_time' => $start, 'end_time' => $end]))->assertSessionHasNoErrors();
        }
        $this->post('/exam-accesses', $this->grantData(['access_mode' => 'session', 'session_name' => 'Duplicate', 'start_time' => '08:00', 'end_time' => '10:00']))->assertSessionHasErrors('exam_date');
        $this->assertDatabaseCount('exam_accesses', 3);
    }

    public function test_grants_reject_invalid_date_window_role_and_draft_exam(): void
    {
        $this->actingAs($this->admin);
        $this->post('/exam-accesses', $this->grantData(['exam_date' => '2026-10-14']))->assertSessionHasErrors('exam_date');
        $this->post('/exam-accesses', $this->grantData(['max_attempts' => 0]))->assertSessionHasErrors('max_attempts');
        $this->post('/exam-accesses', $this->grantData(['user_id' => $this->admin->id]))->assertSessionHasErrors('user_id');
        $this->post('/exam-accesses', $this->grantData(['access_mode' => 'session', 'session_name' => 'Invalid', 'start_time' => '12:00', 'end_time' => '10:00']))->assertSessionHasErrors('end_time');
        $this->exam->update(['status' => 'draft']);
        $this->post('/exam-accesses', $this->grantData())->assertSessionHasErrors('exam_id');
    }

    public function test_login_does_not_consume_attempt_and_reopening_active_attempt_is_idempotent(): void
    {
        $access = $this->grant();
        $this->actingAs($this->student)->get('/exam')->assertOk();
        $this->assertDatabaseCount('exam_attempts', 0);
        $this->post('/exam/accesses/'.$access->id.'/start')->assertRedirect('/exam/attempts/1');
        $this->post('/exam/accesses/'.$access->id.'/start')->assertRedirect('/exam/attempts/1');
        $this->assertDatabaseCount('exam_attempts', 1);
        $this->get('/exam/attempts/1')->assertInertia(fn (Assert $page) => $page->component('Candidate/Attempt')->has('questions', 1)->missing('questions.0.correct_option')->missing('attempt.questions_snapshot'));
    }

    public function test_completed_attempt_consumes_quota_and_admin_can_increase_it(): void
    {
        $access = $this->grant();
        $this->actingAs($this->student)->post('/exam/accesses/'.$access->id.'/start');
        $this->post('/exam/attempts/1/submit', ['answers' => [$this->question->id => 'A']])->assertRedirect('/exam/attempts/1');
        $this->post('/exam/attempts/1/submit', ['answers' => [$this->question->id => 'B']]);
        $this->assertSame(1, ExamAttempt::first()->correct_count);
        $this->post('/exam/accesses/'.$access->id.'/start')->assertSessionHasErrors('access');
        $this->actingAs($this->admin)->put('/exam-accesses/'.$access->id, ['max_attempts' => 2, 'is_active' => true])->assertSessionHasNoErrors();
        $this->actingAs($this->student)->post('/exam/accesses/'.$access->id.'/start')->assertRedirect('/exam/attempts/2');
        $this->actingAs($this->admin)->put('/exam-accesses/'.$access->id, ['max_attempts' => 1, 'is_active' => true])->assertSessionHasErrors('max_attempts');
    }

    public function test_student_cannot_use_other_accounts_or_access_answer_keys(): void
    {
        $access = $this->grant();
        $this->actingAs($this->student)->post('/exam/accesses/'.$access->id.'/start');
        $other = User::factory()->create(['role' => 'student']);
        $this->actingAs($other)->post('/exam/accesses/'.$access->id.'/start')->assertForbidden();
        $this->get('/exam/attempts/1')->assertForbidden();
        $this->putJson('/exam/attempts/1/answers', ['answers' => []])->assertForbidden();
        $this->post('/exam/attempts/1/submit', ['answers' => []])->assertForbidden();
        $this->get('/exam')->assertInertia(fn (Assert $page) => $page->has('accesses', 0)->has('attempts', 0));
    }

    public function test_date_session_window_and_revocation_are_enforced_on_server(): void
    {
        $this->actingAs($this->student);
        foreach ([['exam_date' => '2026-10-14'], ['exam_date' => '2026-10-16'], ['is_active' => false], ['access_mode' => 'session', 'start_time' => '11:00', 'end_time' => '12:00'], ['access_mode' => 'session', 'start_time' => '08:00', 'end_time' => '10:00']] as $attributes) {
            $access = $this->grant($attributes);
            $this->post('/exam/accesses/'.$access->id.'/start')->assertSessionHasErrors('access');
        }
        $this->assertDatabaseCount('exam_attempts', 0);
        $access = $this->grant(['access_mode' => 'session', 'start_time' => '09:00', 'end_time' => '10:30']);
        $this->post('/exam/accesses/'.$access->id.'/start');
        $this->assertTrue(ExamAttempt::first()->expires_at->equalTo(now()->addMinutes(30)));
        $access->update(['is_active' => false]);
        $this->putJson('/exam/attempts/1/answers', ['answers' => []])->assertForbidden();
    }

    public function test_expired_attempt_rejects_new_answers_and_grades_saved_answers_only(): void
    {
        $access = $this->grant();
        $this->actingAs($this->student)->post('/exam/accesses/'.$access->id.'/start');
        $this->putJson('/exam/attempts/1/answers', ['answers' => [$this->question->id => 'A']])->assertOk();
        $this->putJson('/exam/attempts/1/answers', ['answers' => [99999 => 'A']])->assertUnprocessable();
        $this->question->update(['correct_option' => 'B']);
        $this->travel(61)->minutes();
        $this->putJson('/exam/attempts/1/answers', ['answers' => [$this->question->id => 'B']])->assertStatus(409);
        $this->post('/exam/attempts/1/submit', ['answers' => [$this->question->id => 'B']])->assertRedirect();
        $attempt = ExamAttempt::first();
        $this->assertSame(1, $attempt->correct_count);
        $this->assertSame('A', $attempt->answers[$this->question->id]);
        $this->assertNotNull($attempt->submitted_at);
        $this->post('/exam/accesses/'.$access->id.'/start')->assertSessionHasErrors('access');
    }

    public function test_day_access_uses_vietnam_date_and_caps_time_at_midnight(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(16, 50));
        $access = $this->grant();
        $this->actingAs($this->student)->post('/exam/accesses/'.$access->id.'/start')->assertRedirect();
        $this->assertSame('2026-10-15 16:59:59', ExamAttempt::first()->expires_at->format('Y-m-d H:i:s'));
        $this->travel(11)->minutes();
        $this->post('/exam/accesses/'.$access->id.'/start')->assertSessionHasErrors('access');
    }

    public function test_empty_exam_cannot_be_published_or_started(): void
    {
        $this->exam->questions()->detach();
        $this->exam->update(['status' => 'draft']);
        $this->actingAs($this->admin)->put('/exams/'.$this->exam->id.'/status', ['status' => 'published'])->assertSessionHasErrors('status');
        $this->exam->update(['status' => 'published']);
        $access = $this->grant();
        $this->actingAs($this->student)->post('/exam/accesses/'.$access->id.'/start')->assertSessionHasErrors('access');
        $this->assertDatabaseCount('exam_attempts', 0);
    }
}
