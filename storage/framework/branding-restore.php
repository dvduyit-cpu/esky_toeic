<?php
require dirname(__DIR__,2).'/vendor/autoload.php';$app=require dirname(__DIR__,2).'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$backup=json_decode(file_get_contents(__DIR__.'/branding-backup.json'),true,512,JSON_THROW_ON_ERROR);$current=App\Models\SoftwareSetting::current()['logo_path'];
if($current&&$current!==$backup['logo_path'])Illuminate\Support\Facades\Storage::disk('local')->delete($current);
if($backup['logo_path']&&$backup['logo_contents'])Illuminate\Support\Facades\Storage::disk('local')->put($backup['logo_path'],base64_decode($backup['logo_contents']));
if($backup['row']===null)App\Models\SoftwareSetting::whereKey(1)->delete();else App\Models\SoftwareSetting::updateOrCreate(['id'=>1],['data'=>$backup['row']]);
$meta=json_decode(file_get_contents(__DIR__.'/branding-test-data.json'),true,512,JSON_THROW_ON_ERROR);if(!preg_match('/^ui-branding-[0-9]+@example\.test$/',$meta['email']))throw new RuntimeException('Invalid test identity.');
$users=App\Models\User::where('email',$meta['email'])->where('role','student')->where('name','Branding test')->get();foreach($users as $user){Illuminate\Support\Facades\DB::table('sessions')->where('user_id',$user->id)->delete();$user->delete();}
echo "Original branding restored; temporary student removed.\n";
