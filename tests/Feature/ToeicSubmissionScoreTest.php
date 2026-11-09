<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamAccess;
use App\Models\ExamAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ToeicSubmissionScoreTest extends TestCase
{
    use RefreshDatabase;

    public static function readingCases(): array
    {
        return [[0, 5], [1, 5], [2, 5], [3, 10], [25, 120], [80, 395], [99, 490], [100, 495]];
    }

    private function attempt(int $count = 100): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student', 'exam_track' => 'toeic']);
        $exam = Exam::create(['title' => 'TOEIC score test', 'exam_track' => 'toeic', 'duration_minutes' => 120, 'status' => 'published', 'created_by' => $admin->id]);
        $access = ExamAccess::create(['user_id' => $student->id, 'exam_id' => $exam->id, 'exam_date' => now('Asia/Bangkok')->toDateString(), 'access_mode' => 'day', 'max_attempts' => 1, 'is_active' => true, 'created_by' => $admin->id]);
        $questions = [];
        for ($id = 1; $id <= $count; $id++) {
            $questions[] = ['id' => $id, 'part' => $count === 200 && $id <= 100 ? 1 : 5, 'content' => 'Question '.$id, 'kind' => 'mcq', 'max_points' => 1, 'correct_option' => 'A', 'options' => ['A' => 'Correct', 'B' => 'Incorrect']];
        }
        $attempt = ExamAttempt::create(['exam_access_id' => $access->id, 'attempt_number' => 1, 'questions_snapshot' => $questions, 'answers' => [], 'started_at' => now(), 'expires_at' => now()->addHour()]);
        return [$admin, $student, $attempt];
    }

    #[DataProvider('readingCases')]
    public function test_submission_immediately_exposes_reading_score_and_counts_to_admin(int $correct, int $scaled): void
    {
        [$admin, $student, $attempt] = $this->attempt();
        $answers = [];
        for ($id = 1; $id <= $correct; $id++) $answers[$id] = 'A';
        // Remaining unanswered questions must count as incorrect.
        $this->actingAs($student)->post('/exam/toeic/attempts/'.$attempt->id.'/submit', ['answers' => $answers])->assertRedirect();
        $attempt->refresh();
        $this->assertNotNull($attempt->submitted_at);
        $this->assertNotNull($attempt->graded_at);
        $this->assertSame($correct, $attempt->correct_count);
        $this->assertSame((float) $correct, $attempt->total_points);
        $hidden = ['correct_count', 'total_points', 'listening_result', 'reading_result', 'toeic_total_score'];
        $this->get('/exam/toeic/attempts/'.$attempt->id)->assertInertia(function (Assert $page) use ($hidden) {
            $page->component('Candidate/Attempt');
            foreach ($hidden as $field) $page->missing('attempt.'.$field);
        });
        $this->get('/exam/toeic')->assertInertia(function (Assert $page) use ($hidden) {
            $page->component('Candidate/Home')->has('attempts', 1);
            foreach ($hidden as $field) $page->missing('attempts.0.'.$field);
        });
        $this->actingAs($admin)->get('/manage/toeic/results')->assertInertia(fn (Assert $page) => $page
            ->component('Results')->where('results.data.0.correct_count', $correct)
            ->where('results.data.0.reading_result.correct', $correct)
            ->where('results.data.0.reading_result.total', 100)
            ->where('results.data.0.reading_result.scaled', $scaled)
            ->where('results.data.0.toeic_total_score', null)
            ->has('results.data.0.questions', 100));
    }

    public function test_full_test_totals_sections_and_repeat_submission_does_not_change_score(): void
    {
        [$admin, $student, $attempt] = $this->attempt(200);
        $answers = [];
        for ($id = 1; $id <= 200; $id++) $answers[$id] = $id <= 76 || ($id > 100 && $id <= 180) ? 'A' : 'B';
        $this->actingAs($student)->post('/exam/toeic/attempts/'.$attempt->id.'/submit', ['answers' => $answers])->assertRedirect();
        $this->post('/exam/toeic/attempts/'.$attempt->id.'/submit', ['answers' => []])->assertRedirect();
        $hidden = ['correct_count', 'total_points', 'listening_result', 'reading_result', 'toeic_total_score'];
        $this->get('/exam/toeic/attempts/'.$attempt->id)->assertInertia(function (Assert $page) use ($hidden) {
            $page->component('Candidate/Attempt');
            foreach ($hidden as $field) $page->missing('attempt.'.$field);
        });
        $this->get('/exam/toeic')->assertInertia(function (Assert $page) use ($hidden) {
            $page->component('Candidate/Home')->has('attempts', 1);
            foreach ($hidden as $field) $page->missing('attempts.0.'.$field);
        });
        $this->actingAs($admin)->get('/manage/toeic/results')->assertInertia(fn (Assert $page) => $page
            ->where('results.data.0.correct_count', 156)
            ->where('results.data.0.listening_result.correct', 76)
            ->where('results.data.0.listening_result.scaled', 395)
            ->where('results.data.0.reading_result.correct', 80)
            ->where('results.data.0.reading_result.scaled', 395)
            ->where('results.data.0.toeic_total_score', 790));
    }
}