<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class RoleAccessTest extends TestCase {
 use RefreshDatabase;
 public function test_guest_redirected_to_login():void{$this->get('/dashboard')->assertRedirect('/login');}
 public function test_student_cannot_access_admin_or_question_management():void{
  $student=User::factory()->create(['role'=>'student','is_active'=>true]);
  $this->actingAs($student)->get('/users')->assertForbidden();
  $this->actingAs($student)->get('/questions')->assertForbidden();
 }
 public function test_teacher_may_manage_questions_but_not_accounts():void{
  $teacher=User::factory()->create(['role'=>'teacher','is_active'=>true]);
  $this->actingAs($teacher)->get('/questions')->assertOk();
  $this->actingAs($teacher)->get('/users')->assertForbidden();
 }
 public function test_admin_accesses_account_list():void{
  $admin=User::factory()->create(['role'=>'admin','is_active'=>true]);
  $this->actingAs($admin)->get('/users')->assertOk();
 }
}
