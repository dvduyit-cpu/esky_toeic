# E-SKY EXAM

Ứng dụng Laravel 13 + Vue 3 + Inertia + MySQL, đã bổ sung đầy đủ bộ khung để chạy trực tiếp trong thư mục này.

## Chạy trên máy hiện tại với Laragon

1. Mở Laragon, chọn **Start All** (Apache và MySQL).
2. Mở **http://toeic.localhost/admin/login** để quản trị; thí sinh dùng **http://toeic.localhost/exam/login**.
3. Đăng nhập bằng email `admin@esky.local`. Mật khẩu được tạo riêng trên máy, xem file `LOCAL-ACCESS.txt` ở thư mục dự án. File này và `.env` không đưa lên Git.

Đã cấu hình Apache tại `C:\laragon\etc\apache2\sites-enabled\toeic.localhost.conf`, trỏ vào `D:\code\TOEIC\public`. Tên miền `.localhost` được Chrome/Edge phân giải về máy cục bộ. Không cần chuyển mã nguồn vào `C:\laragon\www`, chạy `php artisan serve` hoặc bật Vite mỗi lần sử dụng.

Database: `esky_toeic`, host `127.0.0.1`, port `3306`, user `root`, mật khẩu trống theo MySQL Laragon hiện tại. Cấu hình nằm trong `.env`.

Nếu đổi vị trí dự án, sửa đường dẫn trong cấu hình Apache rồi dùng Laragon **Stop / Start All**. Mẫu cấu hình nằm ở `scripts/laragon-vhost.conf`.

Nếu trình duyệt không phân giải được tên miền, có thể chạy `php artisan serve --host=127.0.0.1 --port=8000` và mở http://127.0.0.1:8000 khi MySQL Laragon đang bật.

## Cài lại dependencies hoặc cài trên máy khác

Cần PHP >= 8.3 với PDO MySQL, Composer, Node.js >= 20.19 (hoặc >= 22.12) và MySQL. Trong terminal Laragon tại thư mục dự án:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\install.ps1
```

Trước lần cài mới, tạo database `esky_toeic` bằng HeidiSQL của Laragon. Script giữ nguyên `.env` nếu đã tồn tại. Chỉnh kết nối MySQL trong `.env` trước khi chạy nếu máy dùng cấu hình khác.

Để tạo admin trên máy mới, đặt `ESKY_ADMIN_PASSWORD` trong `.env` (ít nhất 12 ký tự), sau đó:

```powershell
php artisan db:seed
```

Seeder sẽ cập nhật mật khẩu admin nếu chạy lại. Máy hiện tại đã có admin, không cần seed lại.

## Phát triển và kiểm tra

```powershell
npm run dev
```

Chạy lệnh trên khi chỉnh giao diện. Khi dùng bình thường, dừng Vite rồi chạy `npm run build` để Apache dùng bản đã biên dịch.

```powershell
php artisan test
npm run build
```

## Chức năng hiện có

- Đăng nhập, đăng xuất, phân quyền admin / teacher / student.
- Dashboard, quản lý tài khoản và khóa/mở tài khoản.
- Ngân hàng câu hỏi riêng: TOEIC Part 1–7; VSTEP bốn kỹ năng; thi kết thúc môn theo môn học.
- Tạo đề, gắn câu hỏi, mở/đóng đề để cấp quyền thi.

Có cổng đăng nhập riêng từng phân hệ, tự lưu đáp án, giới hạn thời gian, chấm trắc nghiệm tự động và chấm tự luận/bài nói thủ công. Điểm tính theo điểm từng câu; chưa quy đổi theo thang chứng chỉ TOEIC/VSTEP.

## Quản trị ba phân hệ

- Quản trị chung: `/admin/login`, dùng tài khoản admin hiện có.
- Cổng chọn phần thi: `/exam/login`.
- TOEIC: `/exam/toeic/login`, quản trị tại `/manage/toeic/questions`.
- VSTEP: `/exam/vstep/login`, quản trị tại `/manage/vstep/questions`.
- Thi kết thúc môn: `/exam/course/login`, quản trị tại `/manage/course/subjects`.

Ngân hàng, tư liệu, đề và tài khoản thí sinh được tách theo phân hệ ở máy chủ. Có thể dùng cùng email để tạo tài khoản riêng ở các phân hệ, mỗi tài khoản có mật khẩu, lịch và lượt thi độc lập. Tài khoản quản trị dùng chung; giáo viên được chọn phân hệ quản lý khi tạo/sửa tài khoản.

Các thao tác thêm/sửa/xóa, mở/đóng đề, cấp/thu hồi quyền và chấm bài đều mở modal. Dữ liệu đã dùng trong đề hoặc phát sinh bài làm được giữ lại; khóa tài khoản hoặc thu hồi quyền để ngừng sử dụng.

## Cấp tài khoản và quyền thi

1. Trong menu quản trị, mở đúng nhóm TOEIC, VSTEP hoặc Thi kết thúc môn.
2. Với thi môn học, tạo môn ở **Danh mục môn học** trước.
3. Thêm học liệu tại **Ngân hàng câu hỏi**. VSTEP chia Nghe/Đọc (trắc nghiệm), Viết (tự luận), Nói (ghi âm); thi môn học có thể kết hợp các dạng.
4. Vào **Đề thi**, thêm đề, thêm câu hỏi cùng phân hệ/môn rồi **Mở đề**.
5. Vào **Tài khoản thi**, tạo tài khoản riêng trong nhóm. Sau đó mở **Ngày / ca & lượt thi**, cấp đề và chọn ngày + số lượt hoặc ca có tên, giờ bắt đầu/kết thúc + số lượt.
6. Thí sinh đăng nhập đúng cổng. Đăng nhập không trừ lượt; bắt đầu bài mới tính một lượt; tiếp tục bài đang làm không tính thêm. Bài hết hạn tại thời điểm sớm hơn giữa hết thời lượng đề và hết ngày/ca (UTC+7).
7. Vào **Bài làm & kết quả** để xem bài hoặc chấm tự luận/bài nói theo điểm tối đa từng câu. Thí sinh xem điểm sau khi chấm.

Đề được chụp lại tại lúc bắt đầu; thay đổi học liệu về sau không đổi lượt thi đã bắt đầu. Đáp án đúng không gửi cho thí sinh. Bài ghi âm được lưu trong `storage/app/private`, chỉ chủ bài và người quản lý có quyền mới nghe được. Cần cho phép micro trong trình duyệt để ghi trực tiếp; cũng có thể tải tệp âm thanh. Dừng và lưu ghi âm trước khi hết giờ. Tệp tối đa 20 MB ở ứng dụng, còn phụ thuộc giới hạn upload của PHP.

Tư liệu đề (ảnh/âm thanh) dùng đường dẫn tương đối trong `storage/app/public`; chạy `php artisan storage:link` nếu sử dụng tư liệu này. Dữ liệu cũ được gán vào TOEIC khi chạy migration nâng cấp.

## Cấu hình phần mềm và giao diện

Quản trị viên mở **Cấu hình phần mềm** trong nhóm Quản trị chung, hoặc truy cập `/settings`.

- **Thông tin phần mềm → Chỉnh sửa**: đổi tên, dòng mô tả, đơn vị, logo, footer chính/phụ, lời chào đăng nhập và email hỗ trợ. Logo hỗ trợ PNG/JPG/WebP, tối đa 2 MB và 2048 × 2048 pixel. Có thể bỏ logo để dùng biểu tượng mặc định.
- **Giao diện đồng bộ → Đổi giao diện**: chọn màu chủ đạo, kiểu chữ, cỡ chữ và bo góc. Có xem trước và nút chọn lại giao diện xanh mặc định trước khi lưu.
- Cấu hình được lưu trong cơ sở dữ liệu và áp dụng chung cho quản trị, cổng chọn phần thi, các trang đăng nhập, cổng thí sinh, tên tab và biểu tượng trình duyệt. Các cửa sổ khác nhận thay đổi khi chuyển trang hoặc tải lại.
- Chỉ quản trị viên được thay đổi cấu hình toàn hệ thống. Logo lưu riêng trên máy chủ, không cần tạo storage link.
