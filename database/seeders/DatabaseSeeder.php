<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\User;
class DatabaseSeeder extends Seeder {
public function run():void {
 $password=env('ESKY_ADMIN_PASSWORD');
 if (!$password || strlen($password)<12) { $this->command?->warn('Bỏ qua tạo admin. Đặt ESKY_ADMIN_PASSWORD (tối thiểu 12 ký tự) trong .env rồi chạy php artisan db:seed.'); return; }
 User::updateOrCreate(['email'=>'admin@esky.local'],['name'=>'Quản trị E-SKY','password'=>$password,'role'=>'admin','is_active'=>true]);
 $this->command?->info('Đã tạo/cập nhật admin@esky.local');
}
}
