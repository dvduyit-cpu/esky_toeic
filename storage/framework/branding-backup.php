<?php
require dirname(__DIR__,2).'/vendor/autoload.php';$app=require dirname(__DIR__,2).'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$row=App\Models\SoftwareSetting::find(1);$path=App\Models\SoftwareSetting::current()['logo_path'];
file_put_contents(__DIR__.'/branding-backup.json',json_encode(['row'=>$row?->data,'logo_path'=>$path,'logo_contents'=>$path?base64_encode(Illuminate\Support\Facades\Storage::disk('local')->get($path)):null]));
echo "Branding settings backed up.\n";
