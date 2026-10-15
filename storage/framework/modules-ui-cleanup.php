<?php
require dirname(__DIR__,2).'/vendor/autoload.php';$app=require dirname(__DIR__,2).'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$meta=json_decode(file_get_contents(__DIR__.'/portal-check-data.json'),true,512,JSON_THROW_ON_ERROR);
if(!preg_match('/^ui-portal-check-[0-9]+$/',$meta['marker'])||$meta['email']!==$meta['marker'].'@example.test')throw new RuntimeException('Invalid test identity.');
Illuminate\Support\Facades\DB::transaction(function()use($meta){
 $users=App\Models\User::where('email',$meta['email'])->where('name',$meta['marker'])->get();
 foreach($users as $user){$accesses=App\Models\ExamAccess::where('user_id',$user->id)->pluck('id');foreach(App\Models\ExamAttempt::whereIn('exam_access_id',$accesses)->get() as $attempt){foreach($attempt->recordings??[] as $path)Illuminate\Support\Facades\Storage::disk('local')->delete($path);$attempt->delete();}App\Models\ExamAccess::whereIn('id',$accesses)->delete();Illuminate\Support\Facades\DB::table('sessions')->where('user_id',$user->id)->delete();$user->delete();}
 foreach(['toeic','vstep','course'] as $track){foreach(App\Models\Exam::where('title',$meta['marker'].'-'.$track)->get() as $exam){$exam->questions()->detach();$exam->delete();}foreach(['mcq','essay','speaking'] as $kind)App\Models\Question::where('content',$meta['marker'].'-'.$track.'-'.$kind)->delete();}
 foreach(['temporary','edited'] as $suffix)App\Models\Question::where('content',$meta['marker'].'-'.$suffix)->delete();
 App\Models\Subject::where('code',$meta['marker'])->where('name',$meta['marker'])->delete();
});echo "Module browser test data removed.\n";
