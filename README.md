# BookStack v21.11.1 — mô phỏng VLEX

**Hướng phát triển đã chốt:** cải biên trực tiếp BookStack 21.11.1, không cập nhật phiên bản hay thêm thư viện ứng dụng. Đã bổ sung đường dẫn mã nguồn theo cây VLEX để tiếp nhận từng file thật; chức năng chính tiếp tục chạy bằng BookStack. Đọc [hướng dẫn chép mã VLEX](docs/vlex-local-development.md), [bảng đối chiếu](docs/vlex-file-map.md) và [cây đường dẫn](docs/vlex-source-tree.txt). Prototype Workspace riêng đã được lưu tại `archive/workspace-prototype`, không còn route xử lý; `/vlex` chuyển về `/books`. Dữ liệu prototype được giữ nguyên trong volume và database.

Môi trường được dựng mới từ `C:\Vlex\BookStack-21.11.1.zip`, giữ chức năng nhập Word đã bổ sung. Dữ liệu và ba volumes của bản 21.10.1 đã được xóa theo yêu cầu. Mã nguồn 21.10.1 vẫn có trong lịch sử Git.

## Cấu hình cố định

| Thành phần | Phiên bản |
| --- | --- |
| BookStack | v21.11.1 |
| PHP | 8.0.30 |
| Laravel Framework | 8.70.2 |
| Database | MySQL 8.0.36 |
| Web server | Apache, trong image PHP |
| Chuyển Word | LibreOffice Writer |
| Múi giờ ứng dụng | Asia/Ho_Chi_Minh |

ZIP SHA256: `C5CDEA43E9D7211F30ED421E24F64A75E125923A030948C9FCE362DF564CF134`.

`composer.lock` gốc SHA256: `081369FDFE308E94E3A852E5FC0FF1A37C682A01DCAB9B269B4AACB87F6772E0`.

## Truy cập và chạy

- URL: http://localhost:8080
- Tài khoản ban đầu: `admin@admin.com`
- Mật khẩu ban đầu: `password`
- Chỉ mở trên `127.0.0.1`, dành cho mô phỏng tại máy này.

Chạy PowerShell trong thư mục chứa README, Docker Desktop ở chế độ Linux containers:

```powershell
./init-env.ps1
docker compose up -d --build
docker compose ps
docker compose logs --tail 100 app
docker compose stop
docker compose start
```

Script `init-env.ps1` tạo khóa và mật khẩu riêng, giữ nguyên `.env` nếu file đã tồn tại. Không commit `.env`.

Database, storage và ảnh lưu trong volumes của project `bookstack-21111-lab`. Chỉ dùng `docker compose down --volumes` khi chủ động muốn xóa toàn bộ dữ liệu thử nghiệm.

## Thư viện cố định, không tự cập nhật

ZIP nguồn không chứa thư viện `vendor`. Docker dùng Composer 2.2 được ghim digest chỉ trong build stage để chạy `composer install --no-dev` theo đúng `composer.lock`. Không chạy `composer update`, không thay file khóa. Build kiểm tra hash file khóa, yêu cầu nền tảng và Laravel đúng 8.70.2. Container chạy web chứa `vendor` đã cài, không chứa chương trình Composer và không cài thư viện lúc khởi động.

CSS/JS có sẵn trong ZIP, không chạy npm install hoặc build lại frontend. Chạy web sau khi build không cần truy cập kho Composer. Lần build đầu vẫn cần mạng để tải image, gói hệ điều hành và thư viện đã khóa.

## Phạm vi mô phỏng dự án khách hàng

PHP 8.0.30 và Laravel 8.70.2 khớp yêu cầu. MySQL 8.0.36 là phiên bản được chọn cho mô phỏng; chưa có số phiên bản database thực của khách hàng. Mã nguồn nền là BookStack 21.11.1 gốc cùng phần nhập Word đã phát triển tại đây.

Ảnh chụp dự án khách hàng cho thấy có thêm PHPWord và cấu trúc `Modules`; ZIP gốc không có các tùy biến đó. Chưa bổ sung hay tự chọn phiên bản thư viện khác ngoài lockfile. Để mô phỏng toàn bộ dự án, cần `composer.lock`, cấu hình và mã tùy biến thực của khách hàng. Chức năng nhập Word hiện dùng LibreOffice, không phải PHPWord của khách hàng.

## Nhập Word

Tạo sách hoặc chương, sau đó chọn **Nhập Word (.doc/.docx)** ở menu hành động. File tối đa 20 MB. Nội dung được chuyển thành HTML trong trang nháp mới; kiểm tra rồi bấm **Lưu trang**.

Hỗ trợ văn bản tiếng Việt, tiêu đề, danh sách, bảng/ô gộp, ảnh nhúng và liên kết mục lục. Ảnh được lưu vào kho BookStack. Mục lục Word là nội dung HTML tĩnh; không tự tính lại số trang in. Bố cục trang in, đầu/chân trang, kiểu chữ và đối tượng Word phức tạp có thể khác bản gốc. Ảnh bên ngoài phải nhúng vào Word trước khi nhập. Không hỗ trợ file có mật khẩu.

Yêu cầu quyền tạo trang và quyền tạo ảnh nếu tài liệu có ảnh. Giới hạn chuyển đổi 60 giây, dọn thư mục tạm sau khi xử lý. Email ghi log trong môi trường thử nghiệm.

## Kiểm tra

`tests/check-runtime.php` xác minh phiên bản, toàn bộ thư viện production khớp lockfile, kết nối MySQL và việc không có Composer ở runtime. `tests/word-import/` chứa kiểm tra chuyển Word, lọc HTML, file lỗi và luồng tải lên → bản nháp → xuất bản → tải ảnh/giữ điểm neo mục lục. Bài kiểm tra HTTP tạo sách thử riêng rồi đưa vào thùng rác.

Kết quả kiểm tra ngày 22/10/2026: PHP 8.0.30, Laravel 8.70.2, BookStack v21.11.1, MySQL 8.0.36; 104 thư viện production khớp lockfile gốc và không có Composer executable trong container web. Chuyển đổi DOC/DOCX và luồng nhập trên HTTP đều qua, gồm tiếng Việt, bảng/ô gộp, ảnh và điểm neo mục lục; file sai định dạng trả HTTP 422, người chưa đăng nhập được chuyển về trang đăng nhập. Sau kiểm tra đã khởi tạo lại database và volumes trống để bàn giao, không giữ sách/trang/ảnh kiểm thử.
