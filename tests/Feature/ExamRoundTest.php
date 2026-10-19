<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamAccess;
use App\Models\ExamRound;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExamRoundTest extends TestCase
{
    use RefreshDatabase;

    private function exam(string $track = 'toeic'): Exam
    {
        $exam = Exam::create(['title' => 'Full test', 'exam_track' => $track, 'status' => 'published', 'duration_minutes' => 120, 'created_by' => auth()->id()]);
        foreach ([1, 5, 7] as $part) {
            $q = Question::create(['exam_track' => $track, 'part' => $part, 'content' => 'Question', 'options' => ['A' => 'One', 'B' => 'Two'], 'correct_option' => 'A', 'created_by' => auth()->id()]);
            $exam->questions()->attach($q->id, ['position' => $part]);
        }
        return $exam;
    }

    public function test_creating_account_with_round_grants_full_exam_and_preserves_login_without_consuming_attempt(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        $exam = $this->exam();
        $this->post('/manage/toeic/exam-rounds', ['name' => 'October round', 'exam_id' => $exam->id, 'exam_date' => now('Asia/Bangkok')->toDateString(), 'access_mode' => 'day', 'max_attempts' => 2])->assertSessionHasNoErrors();
        $round = ExamRound::firstOrFail();
        $this->post('/manage/toeic/users', ['name' => 'Candidate', 'email' => 'TS100', 'role' => 'student', 'exam_round_id' => $round->id])->assertSessionHasNoErrors();
        $student = User::where('email', 'TS100')->firstOrFail();
        $access = ExamAccess::where('user_id', $student->id)->firstOrFail();
        $this->assertEquals($round->id, $access->exam_round_id);
        $this->assertEquals(2, $access->max_attempts);
        $this->get('/manage/toeic/users')->assertInertia(fn (Assert $page) => $page->has('exam_rounds', 1)->where('users.data.0.exam_accesses.0.round.name', 'October round'));
        $this->post('/logout');
        $this->post('/exam/toeic/login', ['email' => 'TS100', 'password' => '123456'])->assertRedirect('/exam/toeic');
        $this->get('/exam/toeic')->assertInertia(fn (Assert $page) => $page->where('accesses.0.round.name', 'October round'));
        $this->assertDatabaseCount('exam_attempts', 0);
        $this->post('/exam/toeic/accesses/'.$access->id.'/start')->assertSessionHasNoErrors();
        $this->assertCount(3, $access->attempts()->firstOrFail()->questions_snapshot);
        $this->actingAs($admin);
        for ($i = 0; $i < 2; $i++) $this->put('/manage/toeic/users/'.$student->id, ['name' => 'Candidate', 'role' => 'student', 'is_active' => true, 'exam_round_id' => $round->id])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('exam_accesses', 1);
        $this->assertDatabaseCount('exam_attempts', 1);
    }

    public function test_round_validation_and_assignment_are_scoped_and_atomic(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $exam = $this->exam('vstep');
        $payload = ['name' => 'Round', 'exam_id' => $exam->id, 'exam_date' => now('Asia/Bangkok')->toDateString(), 'access_mode' => 'session', 'session_name' => 'Morning', 'start_time' => '10:00', 'end_time' => '09:00', 'max_attempts' => 1];
        $this->post('/manage/toeic/exam-rounds', $payload)->assertSessionHasErrors(['exam_id', 'end_time']);
        $this->post('/manage/vstep/exam-rounds', array_replace($payload, ['access_mode' => 'day']))->assertSessionHasNoErrors();
        $round = ExamRound::firstOrFail();
        $account = ['name' => 'Candidate', 'email' => 'TS101', 'role' => 'student', 'exam_round_id' => $round->id];
        $this->post('/manage/toeic/users', $account)->assertSessionHasErrors('exam_round_id');
        $this->assertDatabaseMissing('users', ['email' => 'TS101']);
        $round->update(['exam_date' => now('Asia/Bangkok')->subDay()->toDateString()]);
        $this->post('/manage/vstep/users', $account)->assertSessionHasErrors('exam_round_id');
        $this->assertDatabaseMissing('users', ['email' => 'TS101']);
        $this->actingAs(User::factory()->create(['role' => 'teacher']));
        $this->post('/manage/toeic/exam-rounds', $payload)->assertForbidden();
    }

    public function test_account_can_be_created_first_then_assigned_and_expired_rounds_are_archived(): void
    {
        $this->travelTo(now()->setTime(6, 0));
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        $exam = $this->exam();
        $this->post('/manage/toeic/users', ['name' => 'Candidate', 'email' => 'TS200', 'role' => 'student'])->assertSessionHasNoErrors();
        $student = User::where('email', 'TS200')->firstOrFail();
        $this->assertDatabaseCount('exam_accesses', 0);
        $round = ExamRound::create(['name' => 'Current round', 'exam_track' => 'toeic', 'exam_id' => $exam->id, 'exam_date' => now('Asia/Bangkok')->toDateString(), 'access_mode' => 'day', 'max_attempts' => 1, 'created_by' => $admin->id]);
        $this->put('/manage/toeic/users/'.$student->id, ['name' => $student->name, 'role' => 'student', 'is_active' => true, 'exam_round_id' => $round->id])->assertSessionHasNoErrors();
        $this->get('/manage/toeic/users')->assertInertia(fn (Assert $page) => $page->has('exam_rounds', 1)->where('users.data.0.exam_accesses.0.archived', false));
        $this->travel(1)->days();
        $this->get('/manage/toeic/users')->assertInertia(fn (Assert $page) => $page->has('exam_rounds', 0)->where('users.data.0.exam_accesses.0.archived', true));
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('exam_accesses', 1);
    }
}
