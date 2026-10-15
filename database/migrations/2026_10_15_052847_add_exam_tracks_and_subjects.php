<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('department')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->string('exam_track', 20)->default('toeic')->index();
            $table->json('managed_tracks')->nullable();
            $table->string('login_scope', 20)->default('staff');
            $table->dropUnique(['email']);
            $table->unique(['email', 'login_scope']);
        });
        DB::table('users')->where('role', 'student')->update(['login_scope' => 'toeic']);
        foreach (['questions', 'question_groups', 'exams'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('exam_track', 20)->default('toeic')->index();
                $table->foreignId('subject_id')->nullable()->constrained('subjects')->restrictOnDelete();
            });
        }
        Schema::table('questions', function (Blueprint $table) {
            $table->string('kind', 20)->default('mcq');
            $table->string('skill', 20)->nullable();
            $table->decimal('max_points', 6, 2)->default(1);
        });
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->json('manual_scores')->nullable();
            $table->decimal('total_points', 8, 2)->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('recordings')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('graded_by');
            $table->dropColumn(['manual_scores', 'total_points', 'graded_at', 'recordings']);
        });
        Schema::table('questions', fn (Blueprint $table) => $table->dropColumn(['kind', 'skill', 'max_points']));
        foreach (['questions', 'question_groups', 'exams'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('subject_id');
                $table->dropColumn('exam_track');
            });
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email', 'login_scope']);
            $table->dropColumn(['exam_track', 'managed_tracks', 'login_scope']);
            $table->unique('email');
        });
        Schema::dropIfExists('subjects');
    }
};
