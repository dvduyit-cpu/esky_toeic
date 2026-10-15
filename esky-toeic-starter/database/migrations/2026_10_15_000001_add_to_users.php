<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {public function up():void {Schema::table('users',function(Blueprint $t){$t->string('role',20)->default('student')->index();$t->boolean('is_active')->default(true);});} public function down():void{Schema::table('users',function(Blueprint $t){$t->dropIndex(['role']);$t->dropColumn(['role','is_active']);});}};
