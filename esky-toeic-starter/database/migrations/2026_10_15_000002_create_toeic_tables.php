<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
public function up():void{
 Schema::create('question_groups',function(Blueprint $t){$t->id();$t->unsignedTinyInteger('part');$t->string('title')->nullable();$t->longText('passage')->nullable();$t->string('media_path')->nullable();$t->foreignId('created_by')->constrained('users');$t->timestamps();});
 Schema::create('questions',function(Blueprint $t){$t->id();$t->unsignedTinyInteger('part')->index();$t->foreignId('question_group_id')->nullable()->constrained()->nullOnDelete();$t->longText('content');$t->json('options');$t->char('correct_option',1);$t->text('explanation')->nullable();$t->string('difficulty',20)->default('medium');$t->string('status',20)->default('draft');$t->foreignId('created_by')->constrained('users');$t->timestamps();$t->index(['part','difficulty','status']);});
 Schema::create('exams',function(Blueprint $t){$t->id();$t->string('title');$t->text('description')->nullable();$t->unsignedSmallInteger('duration_minutes')->default(120);$t->string('status',20)->default('draft');$t->foreignId('created_by')->constrained('users');$t->timestamps();});
 Schema::create('exam_questions',function(Blueprint $t){$t->id();$t->foreignId('exam_id')->constrained()->cascadeOnDelete();$t->foreignId('question_id')->constrained()->restrictOnDelete();$t->unsignedSmallInteger('position');$t->unique(['exam_id','question_id']);$t->unique(['exam_id','position']);});
}
public function down():void{Schema::dropIfExists('exam_questions');Schema::dropIfExists('exams');Schema::dropIfExists('questions');Schema::dropIfExists('question_groups');}
};
