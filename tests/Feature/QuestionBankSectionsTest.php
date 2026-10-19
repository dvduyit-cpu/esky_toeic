<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QuestionBankSectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_toeic_bank_groups_by_part_even_without_skill_metadata(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        for ($part = 1; $part <= 7; $part++) {
            Question::create(['exam_track' => 'toeic', 'part' => $part, 'content' => 'Question Part '.$part, 'options' => ['A' => 'One'], 'correct_option' => 'A', 'created_by' => $admin->id]);
        }
        Question::create(['exam_track' => 'vstep', 'part' => 1, 'skill' => 'listening', 'content' => 'Other track', 'options' => ['A' => 'One'], 'correct_option' => 'A', 'created_by' => $admin->id]);
        $this->actingAs($admin)->get('/manage/toeic/questions')->assertInertia(fn (Assert $page) => $page
            ->where('filters.section', 'listening')->has('questions.data', 4)->where('section_counts.listening', 4)->where('section_counts.reading', 3));
        $this->get('/manage/toeic/questions?section=reading')->assertInertia(fn (Assert $page) => $page
            ->where('filters.section', 'reading')->has('questions.data', 3)->where('questions.data', fn ($rows) => collect($rows)->pluck('part')->sort()->values()->all() === [5, 6, 7]));
        $this->get('/manage/toeic/questions?section=reading&part=6')->assertInertia(fn (Assert $page) => $page
            ->has('questions.data', 1)->where('questions.data.0.part', 6));
        $this->get('/manage/toeic/questions?part=5')->assertInertia(fn (Assert $page) => $page
            ->where('filters.section', 'reading')->has('questions.data', 1)->where('questions.data.0.part', 5));
        $this->get('/manage/toeic/questions?section=listening&search=Part%202')->assertInertia(fn (Assert $page) => $page
            ->has('questions.data', 1)->where('questions.data.0.part', 2));
        $this->get('/manage/toeic/questions?section=listening&part=7')->assertInertia(fn (Assert $page) => $page
            ->missing('filters.part')->has('questions.data', 4));
        $this->get('/manage/vstep/questions?skill=listening')->assertInertia(fn (Assert $page) => $page
            ->has('questions.data', 1)->where('questions.data.0.exam_track', 'vstep'));
    }
}
