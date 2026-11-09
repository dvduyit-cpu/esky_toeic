<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamAccess;
use App\Models\ExamAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttemptPdfTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student', 'exam_track' => 'toeic', 'name' => 'Nguyễn Văn An']);
        $exam = Exam::create(['title' => 'Đề kiểm tra Reading', 'exam_track' => 'toeic', 'duration_minutes' => 60, 'status' => 'published', 'created_by' => $admin->id]);
        $access = ExamAccess::create(['user_id' => $student->id, 'exam_id' => $exam->id, 'exam_date' => now()->toDateString(), 'access_mode' => 'day', 'max_attempts' => 1, 'created_by' => $admin->id]);
        $attempt = ExamAttempt::create(['exam_access_id' => $access->id, 'attempt_number' => 1,
            'questions_snapshot' => [
                ['id' => 1, 'part' => 5, 'content' => 'Choose <script>alert(1)</script>', 'correct_option' => 'A', 'options' => ['A' => 'First option', 'B' => 'Second option'], 'max_points' => 1],
                ['id' => 2, 'part' => 7, 'kind' => 'essay', 'content' => 'Viết đoạn văn.', 'max_points' => 5],
            ], 'answers' => [1 => 'A', 2 => 'Bài làm tiếng Việt.'], 'manual_scores' => [2 => 4], 'started_at' => now(), 'expires_at' => now()->addHour()]);
        $attempt->finish();
        return [$admin, $student, $attempt];
    }

    public function test_admin_can_preview_and_download_a_real_pdf(): void
    {
        [$admin, , $attempt] = $this->fixture();
        $this->actingAs($admin);
        foreach (['' => 'inline', '?download=1' => 'attachment'] as $query => $disposition) {
            $response = $this->get('/manage/toeic/results/'.$attempt->id.'/pdf'.$query);
            $response->assertOk()->assertHeader('Content-Type', 'application/pdf')
                ->assertHeader('Content-Disposition', $disposition.'; filename="bai-lam-'.$attempt->id.'.pdf"');
            $this->assertStringStartsWith('%PDF-', $response->getContent());
            $this->assertStringContainsString('%%EOF', $response->getContent());
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        }
    }

    public function test_document_contains_saved_answers_marks_and_escaped_question_content(): void
    {
        [, , $attempt] = $this->fixture();
        $html = view('pdf.attempt', ['attempt' => $attempt, 'branding' => \App\Models\SoftwareSetting::current(), 'images' => [], 'exportedAt' => now()])->render();
        $this->assertStringContainsString('Nguyễn Văn An', $html);
        $this->assertStringContainsString('Bài làm tiếng Việt.', $html);
        $this->assertStringContainsString('First option', $html);
        $this->assertStringContainsString('4 / 5', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_export_respects_staff_ownership_tracks_and_candidate_score_privacy(): void
    {
        [$admin, $student, $attempt] = $this->fixture();
        $url = '/manage/toeic/results/'.$attempt->id.'/pdf';
        $this->get($url)->assertRedirect('/admin/login');
        $this->actingAs($student)->get($url)->assertForbidden();
        $teacher = User::factory()->create(['role' => 'teacher', 'managed_tracks' => ['toeic']]);
        $this->actingAs($teacher)->get($url)->assertForbidden();
        $this->actingAs($admin)->get('/manage/vstep/results/'.$attempt->id.'/pdf')->assertNotFound();
        $attempt->update(['submitted_at' => null]);
        $this->get($url)->assertStatus(422);
    }
}