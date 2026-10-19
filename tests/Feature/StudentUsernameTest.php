<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentUsernameTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_be_created_and_login_with_username_and_default_password(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post('/manage/toeic/users', [
            'name' => 'Thí sinh 001', 'email' => 'TS001', 'role' => 'student',
        ])->assertSessionHasNoErrors();
        $student = User::where('email', 'TS001')->firstOrFail();
        $this->assertTrue(Hash::check('123456', $student->password));
        $this->post('/logout');
        $this->post('/exam/toeic/login', ['email' => 'TS001', 'password' => '123456'])->assertRedirect('/exam/toeic');
        $this->assertAuthenticatedAs($student);
        $this->post('/logout');
        $this->post('/exam/vstep/login', ['email' => 'TS001', 'password' => '123456'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_short_student_password_can_be_set_without_weakening_staff_validation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post('/manage/toeic/users', [
            'name' => 'Thí sinh 002', 'email' => 'TS002', 'password' => '4321', 'role' => 'student',
        ])->assertSessionHasNoErrors();
        $student = User::where('email', 'TS002')->firstOrFail();
        $this->put('/manage/toeic/users/'.$student->id, [
            'name' => $student->name, 'role' => 'student', 'is_active' => true, 'password' => '5678',
        ])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('5678', $student->fresh()->password));
        $this->post('/manage/toeic/users', ['name' => 'Duplicate', 'email' => 'TS002', 'role' => 'student'])->assertSessionHasErrors('email');
        $this->post('/users', ['name' => 'Staff', 'email' => 'staff@example.com', 'password' => '123456', 'role' => 'teacher'])->assertSessionHasErrors('password');
        $this->post('/users', ['name' => 'Staff', 'email' => 'not-an-email', 'password' => 'LongPassword123', 'role' => 'teacher'])->assertSessionHasErrors('email');
    }
}
