from pathlib import Path
p=Path('README.md');s=p.read_text(encoding='utf-8-sig');s=s.replace('# E-SKY TOEIC','# E-SKY EXAM').replace('Ngân hàng câu hỏi Part 1–7, câu đơn hoặc nhóm.','Ngân hàng câu hỏi riêng: TOEIC Part 1–7; VSTEP bốn kỹ năng; thi kết thúc môn theo môn học.').replace('Có cổng thi riêng, tự lưu đáp án, giới hạn thời gian và kết quả theo số câu đúng. Chưa có quy trình TOEIC chính thức 200 câu, chấm điểm scaled hoặc xuất báo cáo.','Có cổng đăng nhập riêng từng phân hệ, tự lưu đáp án, giới hạn thời gian, chấm trắc nghiệm tự động và chấm tự luận/bài nói thủ công. Điểm tính theo điểm từng câu; chưa quy đổi theo thang chứng chỉ TOEIC/VSTEP.');i=s.index('## Cấp tài khoản và quyền thi');s=s[:i]+'''## Quản trị ba phân hệ

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
''';p.write_text(s,encoding='utf-8')
