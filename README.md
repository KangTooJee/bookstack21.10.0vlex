# BookStack v21.10.1 — môi trường thử nghiệm Docker

Mã nguồn được giải nén từ `C:\Vlex\BookStack-21.10.1.zip`.
SHA256: `450223B9F408B102D4CF6B1FD3A1A924763086953077AB9E7E4E7FA80B7D06AD`.
File `source/BookStack-21.10.1/version` xác nhận `v21.10.1`.

## Truy cập

- URL mặc định: http://localhost:8080 (xem `APP_PORT` trong `.env`).
- Tài khoản ban đầu: `admin@admin.com`.
- Mật khẩu ban đầu: `password`.
- Dịch vụ chỉ lắng nghe trên `127.0.0.1`, dành cho thử nghiệm tại máy này.

## Chạy và dừng

Mở PowerShell trong thư mục chứa README này. Docker Desktop phải đang chạy với Linux containers.

```powershell
./init-env.ps1
docker compose up -d --build
docker compose ps
docker compose logs --tail 100 app
docker compose stop
docker compose start
```

Lần dựng mới trên Windows, chạy `init-env.ps1` để tạo `.env` với khóa và mật khẩu ngẫu nhiên. Script giữ nguyên cấu hình nếu `.env` đã tồn tại. `.env.example` liệt kê các biến cần thiết; không commit `.env` lên Git.

PHP 7.4 và Composer 2.2 được dùng để tương thích với mã nguồn, các thư viện PHP được cài theo `composer.lock`. CSS/JS đã được đóng gói trong ZIP nên không cần Node.js. Database dùng MariaDB 10.6, tự khởi tạo và chạy migration khi ứng dụng bắt đầu.

Database, tệp tải lên và storage được lưu trong ba Docker volumes của project `bookstack-21101-lab`. Dừng hoặc tạo lại container vẫn giữ dữ liệu. Không dùng `docker compose down -v` nếu muốn giữ dữ liệu.

Khóa ứng dụng và mật khẩu database nằm trong `.env`, đã được tạo riêng cho lần cài này. Giữ file này cùng với bản sao lưu dữ liệu. Email thử nghiệm ghi vào log trong container, không gửi ra ngoài.

## Thử chức năng

1. Đăng nhập và tạo một Shelf (kệ sách), Book (sách), Chapter (chương), Page (trang).
2. Thử trình soạn thảo, ảnh đính kèm, tìm kiếm và lịch sử chỉnh sửa.
3. Trong phần quản trị, xem Roles/Users và thử phân quyền trên một sách mẫu.

Đây là bản 2021 theo ZIP được cung cấp, phù hợp làm mốc đánh giá. Chưa cài một phiên bản mới để so sánh song song.

## Kiểm tra sau cài đặt (22/10/2026)

- Container ứng dụng chạy; database báo healthy; phiên bản trong container là `v21.10.1`.
- Đăng nhập bằng tài khoản ban đầu thành công.
- Các đường dẫn `/books`, `/shelves`, `/settings`, `/settings/users`, `/dist/styles.css`, `/dist/app.js` đều trả HTTP 200.
- Luồng tạo nội dung và tải Word đã được kiểm tra bên dưới. Chưa kiểm tra xuất PDF.

## Nhập Word thành nội dung trang

Trong menu hành động của sách, chương hoặc trang, bấm **Nhập Word (.doc/.docx)**. Chọn tài liệu tối đa 20 MB và nhập tên trang nếu muốn. Nội dung mở trong trình soạn thảo dưới dạng trang nháp mới; bấm **Lưu trang** để xuất bản vào sách/chương tương ứng.

Nội dung nhập gồm văn bản tiếng Việt, tiêu đề, in đậm/nghiêng, danh sách, bảng, ô gộp, ảnh nhúng và các liên kết mục lục có trong tài liệu. Ảnh được lưu vào kho ảnh BookStack và gắn với trang. Tiêu đề tạo thanh điều hướng trang của BookStack. Mục lục Word được nhập như nội dung HTML, không tự cập nhật số trang in. Ảnh chỉ liên kết tới ổ đĩa hoặc website bên ngoài cần được nhúng vào Word trước khi nhập. File có mật khẩu và file đổi đuôi không đúng định dạng sẽ bị từ chối.

Chức năng tạo trang nháp mới trong cùng sách/chương, không thay nội dung trang đang xem. Yêu cầu quyền tạo trang tại đích; nếu có ảnh thì cần thêm quyền tạo ảnh. Thời gian chuyển đổi giới hạn 60 giây. Thư mục tạm được dọn sau mỗi lần nhập.

Docker image đã bổ sung LibreOffice Writer để chuyển Word sang HTML. Bố cục trang in, đầu/chân trang, kiểu chữ và đối tượng Word phức tạp có thể khác tài liệu gốc. Đây là chức năng bổ sung vào bản Docker, chưa áp dụng lên website thực của bạn.

### Kiểm thử nhập Word

File mẫu và mã kiểm tra nằm trong `tests/word-import/`. Đã kiểm tra cả `.doc` và `.docx` chứa tiếng Việt, ảnh nhúng, danh sách, bảng có ô gộp và liên kết mục lục: chuyển đổi, mở bản nháp, xuất bản và tải ảnh thành công. Đã kiểm tra từ chối file sai định dạng, lọc script/thuộc tính HTML không an toàn và chuyển người chưa đăng nhập về trang đăng nhập. Bài kiểm tra HTTP tạo sách tạm rồi chuyển sách thử vào thùng rác, không sửa nội dung người dùng.
