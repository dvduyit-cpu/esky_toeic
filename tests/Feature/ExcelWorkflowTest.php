<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamAccess;
use App\Models\ExamAttempt;
use App\Models\ExamRound;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ExcelWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function upload(array $rows): UploadedFile
    {
        $book = new Spreadsheet;
        foreach (array_merge([['ho_ten', 'ten_dang_nhap', 'mat_khau']], $rows) as $i => $row) {
            foreach ($row as $j => $value) $book->getActiveSheet()->setCellValueExplicit([$j+1, $i+1], (string) $value, DataType::TYPE_STRING);
        }
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        try { (new Xlsx($book))->save($path); return UploadedFile::fake()->createWithContent('accounts.xlsx', file_get_contents($path)); }
        finally { unlink($path); $book->disconnectWorksheets(); }
    }

    private function readDownload($response): Spreadsheet
    {
        $response->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        try { file_put_contents($path, $response->streamedContent()); return IOFactory::load($path); }
        finally { unlink($path); }
    }

    public function test_preview_does_not_create_accounts_and_import_preserves_usernames_and_default_password(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $rows = [['Nguyễn Văn A', '000123', ''], ['Trần Thị B', 'TS002', '4321']];
        $this->postJson('/manage/toeic/users/import', ['file' => $this->upload($rows), 'preview' => true])
            ->assertOk()->assertJsonPath('count', 2)->assertJsonPath('rows.0.username', '000123')->assertJsonMissingPath('rows.0.password');
        $this->assertDatabaseCount('users', 1);
        $this->post('/manage/toeic/users/import', ['file' => $this->upload($rows)])->assertSessionHasNoErrors();
        $a = User::where('email', '000123')->firstOrFail();
        $b = User::where('email', 'TS002')->firstOrFail();
        $this->assertTrue(Hash::check('123456', $a->password));
        $this->assertTrue(Hash::check('4321', $b->password));
        $this->assertSame('student', $a->role);
        $this->assertSame('toeic', $a->login_scope);
        $this->assertDatabaseCount('exam_accesses', 0);
        $this->post('/manage/toeic/users/import', ['file' => $this->upload($rows)])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('users', 3);
    }

    public function test_duplicates_and_invalid_rows_reject_the_entire_file_with_line_numbers(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->post('/manage/toeic/users/import', ['file' => $this->upload([['A', 'TS001', ''], ['B', 'ts001', '']])])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('users', 1);
        $this->postJson('/manage/toeic/users/import', ['file' => $this->upload([['A', 'GOOD', ''], ['', 'BAD', '1']]), 'preview' => true])
            ->assertUnprocessable()->assertJsonPath('errors.file.0', fn ($message) => str_contains($message, 'Dòng 3'));
        $this->assertDatabaseCount('users', 1);
        $this->post('/manage/toeic/users/import', ['file' => UploadedFile::fake()->createWithContent('broken.xlsx', 'invalid')])->assertSessionHasErrors('file');
    }

    public function test_template_is_valid_xlsx_and_non_admin_cannot_import(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $book = $this->readDownload($this->get('/manage/toeic/users/import-template'));
        $this->assertSame([['ho_ten', 'ten_dang_nhap', 'mat_khau']], $book->getActiveSheet()->toArray());
        $book->disconnectWorksheets();
        $this->actingAs(User::factory()->create(['role' => 'teacher']));
        $this->get('/manage/toeic/users/import-template')->assertForbidden();
        $this->post('/manage/toeic/users/import', ['file' => $this->upload([['A', 'TS001', '']])])->assertForbidden();
    }

    public function test_import_can_assign_round_and_expired_round_rolls_back_all_accounts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        $exam = Exam::create(['exam_track' => 'toeic', 'title' => 'Test', 'duration_minutes' => 120, 'status' => 'published', 'created_by' => $admin->id]);
        $question = Question::create(['part' => 5, 'content' => 'Test', 'options' => ['A' => 'Yes'], 'correct_option' => 'A', 'created_by' => $admin->id]);
        $exam->questions()->attach($question->id, ['position' => 1]);
        $round = ExamRound::create(['name' => 'Round', 'exam_id' => $exam->id, 'exam_track' => 'toeic', 'exam_date' => now('Asia/Bangkok')->toDateString(), 'access_mode' => 'day', 'max_attempts' => 2, 'created_by' => $admin->id]);
        $rows = [['A', 'TS001', ''], ['B', 'TS002', '']];
        $this->post('/manage/toeic/users/import', ['file' => $this->upload($rows), 'exam_round_id' => $round->id])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('exam_accesses', 2);
        $this->assertDatabaseHas('exam_accesses', ['exam_round_id' => $round->id, 'max_attempts' => 2]);
        $round->update(['exam_date' => now('Asia/Bangkok')->subDay()->toDateString()]);
        $this->post('/manage/toeic/users/import', ['file' => $this->upload([['C', 'TS003', ''], ['D', 'TS004', '']]), 'exam_round_id' => $round->id])->assertSessionHasErrors('exam_round_id');
        $this->assertDatabaseMissing('users', ['email' => 'TS003']);
        $this->assertDatabaseMissing('users', ['email' => 'TS004']);
        $this->post('/manage/vstep/users/import', ['file' => $this->upload([['C', 'TS003', '']]), 'exam_round_id' => $round->id])->assertSessionHasErrors('exam_round_id');
    }

    public function test_results_export_includes_all_pages_and_correct_scaled_scores_and_filters(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student', 'email' => '000123', 'name' => '=HYPERLINK("https://example.com")']);
        $exam = Exam::create(['exam_track' => 'toeic', 'title' => 'Full test', 'duration_minutes' => 120, 'status' => 'published', 'created_by' => $admin->id]);
        $other = Exam::create(['exam_track' => 'vstep', 'title' => 'Private VSTEP', 'duration_minutes' => 120, 'status' => 'published', 'created_by' => $admin->id]);
        $round = ExamRound::create(['name' => 'Export round', 'exam_id' => $exam->id, 'exam_track' => 'toeic', 'exam_date' => now('Asia/Bangkok')->toDateString(), 'access_mode' => 'day', 'max_attempts' => 20, 'created_by' => $admin->id]);
        foreach ([$exam, $other] as $current) {
            $access = ExamAccess::create(['user_id' => $student->id, 'exam_id' => $current->id, 'exam_date' => now('Asia/Bangkok')->toDateString(), 'max_attempts' => 20, 'exam_round_id' => $current->id === $exam->id ? $round->id : null, 'created_by' => $admin->id]);
            for ($number = 1; $number <= ($current->id === $exam->id ? 17 : 1); $number++) {
                $questions = []; $answers = [];
                for ($id = 1; $id <= 200; $id++) {
                    $questions[] = ['id' => $id, 'part' => $id <= 100 ? 1 : 5, 'correct_option' => 'A'];
                    if ($id <= 76 || ($id > 100 && $id <= 180)) $answers[$id] = 'A';
                }
                $attempt = ExamAttempt::create(['exam_access_id' => $access->id, 'attempt_number' => $number, 'questions_snapshot' => $questions, 'answers' => $answers, 'started_at' => now(), 'expires_at' => now()->addHours(2)]);
                $attempt->finish();
            }
        }
        $this->actingAs($admin);
        $book = $this->readDownload($this->get('/manage/toeic/results/export?exam_id='.$exam->id.'&exam_round_id='.$round->id));
        $sheet = $book->getActiveSheet();
        $this->assertSame(18, $sheet->getHighestDataRow());
        $this->assertSame('000123', $sheet->getCell('C2')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('B2')->getDataType());
        $this->assertSame('Export round', $sheet->getCell('D2')->getValue());
        $this->assertSame(395, $sheet->getCell('M2')->getValue());
        $this->assertSame(395, $sheet->getCell('O2')->getValue());
        $this->assertSame(790, $sheet->getCell('P2')->getValue());
        $book->disconnectWorksheets();
        $this->get('/manage/toeic/results/export?exam_id='.$other->id)->assertSessionHasErrors('exam_id');
        $teacher = User::factory()->create(['role' => 'teacher']);
        $this->actingAs($teacher);
        $empty = $this->readDownload($this->get('/manage/toeic/results/export'));
        $this->assertSame(1, $empty->getActiveSheet()->getHighestDataRow());
        $empty->disconnectWorksheets();
        $this->actingAs($student)->get('/manage/toeic/results/export')->assertForbidden();
    }
}
