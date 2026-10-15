<?php

namespace Tests\Feature;

use App\Models\SoftwareSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SoftwareSettingTest extends TestCase
{
    use RefreshDatabase;

    private function software(array $changes = []): array
    {
        return array_merge(collect(SoftwareSetting::defaults())->only(['software_name', 'tagline', 'organization_name', 'footer_text', 'footer_note', 'login_heading', 'login_description', 'support_email'])->all(), ['section' => 'software'], $changes);
    }

    private function appearance(array $changes = []): array
    {
        return array_merge(['section' => 'appearance', 'primary_color' => '#0f766e', 'font_family' => 'arial', 'font_size' => 16, 'corner_radius' => 12], $changes);
    }

    public function test_only_admin_can_read_and_update_global_settings(): void
    {
        $this->get('/settings')->assertRedirect('/admin/login');
        foreach (['student', 'teacher'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get('/settings')->assertForbidden();
            $this->post('/settings', $this->appearance())->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/settings')->assertOk();
    }

    public function test_software_and_theme_sections_preserve_each_others_values_and_are_public(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post('/settings', $this->software(['software_name' => 'My Exam Suite', 'organization_name' => 'My University', 'footer_text' => 'My custom footer', 'support_email' => 'help@example.test']))->assertSessionHasNoErrors();
        $this->post('/settings', $this->appearance())->assertSessionHasNoErrors();
        $this->assertSame('My Exam Suite', SoftwareSetting::current()['software_name']);
        $this->assertSame('#0f766e', SoftwareSetting::current()['primary_color']);
        $this->post('/settings', $this->software(['software_name' => 'Updated suite']))->assertSessionHasNoErrors();
        $this->assertSame('#0f766e', SoftwareSetting::current()['primary_color']);
        auth()->logout();
        foreach (['/exam/login', '/admin/login', '/exam/toeic/login', '/exam/vstep/login', '/exam/course/login'] as $url) {
            $this->get($url)->assertInertia(fn (Assert $page) => $page->where('branding.software_name', 'Updated suite')->where('branding.primary_color', '#0f766e')->missing('branding.logo_path'));
        }
    }

    public function test_invalid_colors_fonts_files_and_lengths_do_not_change_saved_settings(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post('/settings', $this->appearance(['primary_color' => 'red; color: red', 'font_family' => 'injected-font', 'font_size' => 99, 'corner_radius' => 100]))->assertSessionHasErrors(['primary_color', 'font_family', 'font_size', 'corner_radius']);
        $this->post('/settings', $this->software(['software_name' => str_repeat('x', 81), 'support_email' => 'invalid']))->assertSessionHasErrors(['software_name', 'support_email']);
        $this->post('/settings', $this->software(['logo' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')]))->assertSessionHasErrors('logo');
        $this->assertSame(SoftwareSetting::defaults(), SoftwareSetting::current());
    }

    public function test_logos_can_be_uploaded_served_replaced_and_removed_without_public_storage_link(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->post('/settings', $this->software(['logo' => UploadedFile::fake()->image('logo.png', 100, 100)]))->assertSessionHasNoErrors();
        $first = SoftwareSetting::current()['logo_path'];
        Storage::disk('local')->assertExists($first);
        $url = SoftwareSetting::publicSettings()['logo_url'];
        $this->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get('/branding/logo/not-a-file.png')->assertNotFound();
        $this->post('/settings', $this->software(['logo' => UploadedFile::fake()->image('replacement.jpg', 100, 100)]))->assertSessionHasNoErrors();
        $second = SoftwareSetting::current()['logo_path'];
        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($second);
        $this->post('/settings', $this->software(['remove_logo' => true]))->assertSessionHasNoErrors();
        $this->assertNull(SoftwareSetting::publicSettings()['logo_url']);
        Storage::disk('local')->assertMissing($second);
    }

    public function test_invalid_replacement_keeps_the_current_logo_and_bright_colors_use_dark_button_text(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post('/settings', $this->software(['logo' => UploadedFile::fake()->image('logo.png', 100, 100)]));
        $original = SoftwareSetting::current()['logo_path'];
        $this->post('/settings', $this->software(['software_name' => '', 'remove_logo' => true]))->assertSessionHasErrors('software_name');
        $this->assertSame($original, SoftwareSetting::current()['logo_path']);
        Storage::disk('local')->assertExists($original);
        $this->post('/settings', $this->appearance(['primary_color' => '#ffee00']))->assertSessionHasNoErrors();
        $this->assertSame('#111827',SoftwareSetting::publicSettings()['on_primary']);
    }
}
