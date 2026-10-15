# E-SKY TOEIC TEST SYSTEM — Giai đoạn 1

Bộ mã nguồn **overlay** cho Laravel 13 + Vue 3 + Inertia + MySQL: đăng nhập, 3 vai trò, dashboard, quản lý tài khoản và ngân hàng câu hỏi Part 1–7 (câu đơn hoặc nhóm), quản lý đề ở dạng nháp. Đây là **MVP phát triển**, chưa được xác nhận chạy end-to-end trong môi trường này vì chưa cài Composer/dependencies. **Chưa dùng để tổ chức kỳ thi chính thức**.

## Cài đặt Windows (PowerShell), cần PHP >= 8.3, Composer, Node.js >= 20 và MySQL

1. Cài Composer từ https://getcomposer.org/download/ ; xác minh `php -v`, `composer -V`, `node -v`.
2. Giải nén bộ mã nguồn này ra thư mục `esky-toeic-starter`.
3. Tại PowerShell chạy:

```powershell
composer create-project laravel/laravel esky-toeic-app "^13.0"
cd esky-toeic-app
composer require inertiajs/inertia-laravel
npm install vue @inertiajs/vue3 @vitejs/plugin-vue
Copy-Item ..\esky-toeic-starterpp\* .pp\ -Recurse -Force
Copy-Item ..\esky-toeic-starter\database\migrations\* .\database\migrations\ -Force
Copy-Item ..\esky-toeic-starter\database\seeders\DatabaseSeeder.php .\database\seeders\DatabaseSeeder.php -Force
Copy-Item ..\esky-toeic-starteresources\* .esources\ -Recurse -Force
Copy-Item ..\esky-toeic-starteroutes\web.php .outes\web.php -Force
Copy-Item ..\esky-toeic-starterite.config.js .ite.config.js -Force
Copy-Item ..\esky-toeic-starter	ests\Feature\* .	ests\Feature\ -Force
```

4. Tạo MySQL database `esky_toeic` (utf8mb4). Sửa `.env`:

```dotenv
APP_NAME="E-SKY TOEIC"
APP_LOCALE=vi
APP_FALLBACK_LOCALE=en
APP_DEBUG=true
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=esky_toeic
DB_USERNAME=root
DB_PASSWORD=
```

5. Tiếp tục:

```powershell
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve
```

Mở `http://127.0.0.1:8000`. Tài khoản Admin **chỉ tạo khi** đặt trước biến `ESKY_ADMIN_PASSWORD` trong `.env` rồi `php artisan db:seed`. Email: `admin@esky.local`. **Không đặt mật khẩu mẫu công khai**.

### Chạy phát triển frontend
`npm run dev` trong cửa sổ terminal thứ hai. Để dùng `@vitejs/plugin-vue`, Vite dùng tập tin `vite.config.js` ở gốc.

## Có gì trong giai đoạn này
- Login/logout với session, CSRF, rate limiter; tự vô hiệu hóa đăng ký công khai.
- Phân quyền admin / teacher / student qua middleware + policy kiểm tra tại controller.
- Admin tạo tài khoản, xem danh sách, khóa/mở, cập nhật vai trò, đặt lại mật khẩu.
- Quản lý ngân hàng câu hỏi: Part, dạng single/group, options, đáp án, giải thích; câu hỏi nhóm dùng `question_groups`.
- Quản lý đề nháp và danh sách câu hỏi gắn vào đề (mức nền tảng, chưa có trình sinh đề chuẩn 200 câu).
- Frontend Vue 3/Inertia đơn giản, responsive, tiếng Việt.

## Chưa hoàn thiện
- Chưa có engine thi 200 câu, auto-save, phát audio có kiểm soát, xếp lịch ca thi, chấm điểm TOEIC scaled và xuất báo cáo.
- Chưa có quy trình duyệt câu hỏi, import Excel, dịch vụ lưu âm thanh.
- Chưa kiểm thử tải đồng thời hoặc kiểm thử trình duyệt đầu-cuối trên môi trường triển khai thực.

## Chạy kiểm tra
`php artisan test` và `npm run build` sau khi cài dependencies. Trước production bắt buộc cấu hình HTTPS, APP_DEBUG=false, sao lưu, đăng nhập MFA admin, scheduler/queue và test vận hành.
