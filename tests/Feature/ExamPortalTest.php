<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExamPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_pages_are_separate_and_guests_go_to_the_correct_portal(): void
    {
        $this->get('/')->assertRedirect('/exam/login');
        $this->get('/admin/login')->assertInertia(fn (Assert $page) => $page->component('Login')->where('portal', 'admin'));
        $this->get('/exam/login')->assertInertia(fn (Assert $page) => $page->component('Portals'));
        $this->get('/exam/toeic/login')->assertInertia(fn (Assert $page) => $page->component('Login')->where('portal', 'exam'));
        $this->get('/exam')->assertRedirect('/exam/login');
        $this->get('/dashboard')->assertRedirect('/admin/login');
    }

    public function test_each_portal_only_accepts_its_own_roles(): void
    {
        $student = User::factory()->create(['role' => 'student', 'password' => 'StudentPassword123']);
        $admin = User::factory()->create(['role' => 'admin', 'password' => 'AdminPassword123']);
        $this->post('/admin/login', ['email' => $student->email, 'password' => 'StudentPassword123'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/exam/login', ['email' => $admin->email, 'password' => 'AdminPassword123'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/exam/login', ['email' => $student->email, 'password' => 'StudentPassword123'])->assertRedirect('/exam');
        $this->get('/dashboard')->assertForbidden();
        $this->get('/exam-accesses')->assertForbidden();
        $this->post('/logout')->assertRedirect('/exam/toeic/login');
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'AdminPassword123'])->assertRedirect('/dashboard');
        $this->get('/exam')->assertForbidden();
        $this->post('/logout')->assertRedirect('/admin/login');
    }

    public function test_disabled_account_is_rejected_and_existing_session_is_ended(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => false]);
        $this->post('/exam/login', ['email' => $student->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->actingAs($student)->get('/exam')->assertRedirect('/exam/login');
        $this->assertGuest();
    }

    public function test_admin_can_manage_staff_and_student_accounts_but_cannot_lock_self(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post('/users', ['name' => 'Teacher', 'email' => 'teacher@example.test', 'password' => 'InitialPassword123', 'role' => 'teacher'])->assertSessionHasNoErrors();
        $teacher = User::where('email', 'teacher@example.test')->firstOrFail();
        $this->put('/users/'.$teacher->id, ['name' => 'Updated Teacher', 'role' => 'admin', 'is_active' => true, 'password' => 'ChangedPassword123'])->assertSessionHasNoErrors();
        $this->assertTrue(password_verify('ChangedPassword123', $teacher->fresh()->password));
        $this->put('/users/'.$admin->id, ['name' => $admin->name, 'role' => 'admin', 'is_active' => false])->assertSessionHasErrors('role');
        $this->assertTrue($admin->fresh()->is_active);
        $student = User::factory()->create(['role' => 'student']);
        $this->put('/users/'.$student->id, ['name' => $student->name, 'role' => 'admin', 'is_active' => true])->assertSessionHasErrors('role');
        $this->assertSame('student', $student->fresh()->role);
    }
}
