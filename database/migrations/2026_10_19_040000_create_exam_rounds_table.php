<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_rounds', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('exam_track', 20);
            $table->foreignId('exam_id')->constrained()->restrictOnDelete();
            $table->date('exam_date');
            $table->string('access_mode', 20)->default('day');
            $table->string('session_name')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedSmallInteger('max_attempts')->default(1);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['exam_track', 'exam_date']);
        });
        Schema::table('exam_accesses', function (Blueprint $table) {
            $table->foreignId('exam_round_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('exam_accesses', fn (Blueprint $table) => $table->dropConstrainedForeignId('exam_round_id'));
        Schema::dropIfExists('exam_rounds');
    }
};
