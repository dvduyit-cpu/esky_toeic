<?php

namespace Tests\Unit;

use App\Models\Exam;
use App\Models\ExamAccess;
use App\Models\ExamAttempt;
use App\Support\ToeicListeningScore;
use Tests\TestCase;

class ToeicListeningScoreTest extends TestCase
{
    public function test_every_entry_matches_the_supplied_table(): void
    {
        $expected = [0,15,20,25,30,35,40,45,50,55,60,65,70,75,80,85,90,95,100,105,
            110,115,120,125,130,135,140,145,150,155,160,165,170,175,180,185,190,195,200,205,
            210,215,220,225,230,235,240,245,250,255,260,265,270,275,280,285,290,295,300,305,
            310,315,320,325,330,335,340,345,350,355,360,365,370,375,380,385,395,400,405,410,
            415,420,425,430,435,440,445,450,455,460,465,470,475,480,485,490,495,495,495,495,495];
        foreach ($expected as $raw => $scaled) $this->assertSame($scaled, ToeicListeningScore::fromRaw($raw), 'Raw '.$raw);
    }

    public function test_breakdown_counts_only_listening_and_does_not_leak_unsubmitted_scores(): void
    {
        $attempt = new ExamAttempt;
        $exam = new Exam(['exam_track' => 'toeic']);
        $access = new ExamAccess;
        $access->setRelation('exam', $exam);
        $attempt->setRelation('access', $access);
        $questions = []; $answers = [];
        for ($id = 1; $id <= 200; $id++) {
            $questions[] = ['id' => $id, 'part' => $id <= 100 ? (($id - 1) % 4 + 1) : 5, 'correct_option' => 'A'];
            if ($id <= 76 || $id > 100) $answers[$id] = 'A';
        }
        $attempt->questions_snapshot = $questions;
        $attempt->answers = $answers;
        $this->assertNull($attempt->listening_result);
        $attempt->submitted_at = '2026-10-19 07:00:00';
        $this->assertSame(['correct' => 76, 'total' => 100, 'scaled' => 395], $attempt->listening_result);
        $attempt->questions_snapshot = array_slice($questions, 0, 20);
        $this->assertSame(['correct' => 20, 'total' => 20, 'scaled' => null], $attempt->listening_result);
        $attempt->questions_snapshot = array_slice($questions, 100);
        $this->assertNull($attempt->listening_result);
        $exam->exam_track = 'vstep';
        $attempt->questions_snapshot = $questions;
        $this->assertNull($attempt->listening_result);
    }
}
