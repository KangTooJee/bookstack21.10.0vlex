# Nhập/xuất Excel trên BookStack 21.11.1

Luồng sử dụng giống Word: ở menu hành động của sách, chương hoặc trang, chọn **Nhập Excel (.xls/.xlsx)**. Tải bảng tính tối đa 20 MB, kiểm tra/chỉnh sửa bản nháp rồi lưu trang. Sau khi lưu, chọn **Kết xuất → Bảng tính Excel (.xlsx)** để tải nội dung hiện tại.

## Phạm vi chuyển đổi

- Nhập `.xls` Excel 97–2003 và `.xlsx`; nhiều sheet trở thành các bảng kèm tên sheet trong cùng một trang. Giữ chữ tiếng Việt, giá trị hiển thị, ô gộp, màu nền, định dạng chữ cơ bản và ảnh nhúng. Chỉ những sheet/nội dung Calc xuất được thành HTML mới được nhập; cần kiểm tra bản nháp.
- Công thức nhập trở thành giá trị hiển thị do Calc tính/đọc; không giữ công thức, macro, pivot, bộ lọc, validation, biểu đồ gốc hoặc cấu hình in. Không hỗ trợ file đặt mật khẩu, `.xlsm`, `.xlsb`, CSV. Macro trong XLS không được phép chạy; XLSX có `vbaProject.bin` bị từ chối.
- Xuất từng trang đã lưu thành **một sheet `Nội dung`**, lần lượt gồm tên trang, văn bản và các bảng. Không khôi phục các tab sheet gốc. Bảng lồng trong ô được chuyển thành chữ trong ô cha. Các liên kết được xuất dưới dạng chữ hiển thị.
- Các ô xuất dùng kiểu **text** để giữ đúng nội dung web: `00123`, ngày tháng, dấu thập phân, chuỗi bắt đầu bằng `=`. Không tự suy đoán kiểu số/ngày hoặc chạy chuỗi như công thức; muốn tính toán cần đổi kiểu ô trong Excel sau khi tải.
- Giữ colspan/rowspan, font/cỡ chữ px/pt và thẻ font cũ, in đậm/nghiêng/gạch chân, màu chữ/nền, căn chỉnh, xuống dòng và viền cơ bản/từng cạnh. CSS phức tạp, chiều rộng cột gốc và vị trí đối tượng nổi có thể khác. Cột xuất có chiều rộng cố định; font trên máy mở file có thể bị thay nếu thiếu font.
- Ảnh trong kho BookStack hoặc data URI được nhúng thành PNG; không tải ảnh từ URL tùy ý. Ảnh được thu vừa cột, giữ tỷ lệ. Nhiều ảnh cùng một ô có thể chồng lên nhau; nên bố trí mỗi ảnh vào một ô riêng khi chỉnh sửa web.
- Giới hạn xuất: 30 MB, 10.000 hàng, 256 cột, 100.000 ô có nội dung, 200 ảnh, 32.767 ký tự/ô. Bảng vượt giới hạn hoặc ô gộp chồng lấn trả thông báo lỗi. Không sửa file gốc hoặc HTML đã lưu.

## File tích hợp

Gốc ứng dụng: `source/BookStack-21.11.1/`.

| File | Vai trò |
| --- | --- |
| `app/Http/Controllers/ExcelImportController.php` | Quyền tạo trang/ảnh, form, upload, tạo bản nháp trong transaction |
| `app/Uploads/ExcelImportService.php` | Kiểm tra định dạng/ZIP, gọi Calc, chuẩn hóa màu nền, lọc HTML |
| `app/Uploads/ExcelConversion.php` | Profile LibreOffice riêng, tắt macro/cập nhật liên kết, timeout 60 giây |
| `app/Uploads/ExcelExportService.php` | Render trang đã lưu, lấy ảnh qua ImageService, lọc HTML và xuất XLSX |
| `app/Uploads/ExcelWorkbookWriter.php` | Ghi gói OOXML bằng DOM/ZipArchive sẵn có: text, rich text, style, ô gộp và ảnh nhúng |
| `app/Uploads/WordImportService.php` | Mở `cleanHtml()` thành public để Excel dùng chung bộ lọc; thông báo ảnh dùng từ “tài liệu” |
| `app/Http/Controllers/PageExportController.php` | Action `excel()`, MIME XLSX, xử lý lỗi, middleware `content-export` sẵn có |
| `routes/web.php` | GET/POST nhập theo sách/chương; GET xuất từng trang; throttle 10 lần/phút |
| `resources/views/pages/import-excel.blade.php` | Form upload và giới hạn chuyển đổi |
| `resources/views/pages/parts/excel-import-action.blade.php` | Nút nhập theo quyền tạo trang |
| `resources/views/{books,chapters,pages}/show.blade.php` | Gắn nút nhập Excel |
| `resources/views/entities/export-menu.blade.php` | Gắn menu xuất XLSX cho Page |
| `resources/lang/{en,vi}/entities.php` | Nhãn xuất Excel |
| `Dockerfile` (gốc repository) | Thêm gói hệ điều hành `libreoffice-calc` cạnh Writer |

Không thay `composer.json`, `composer.lock`, phiên bản PHP/Laravel hay thêm thư viện PHP. Cần build lại image để có Calc: `docker compose up -d --build`. Các volume dữ liệu được giữ nguyên. Chưa xác minh tương thích với tùy biến Vlex thật; cần đối chiếu routing, phân quyền, editor và kho ảnh ở hệ thống đích.

## Kiểm tra

Trong PowerShell tại gốc repository, sau khi chạy ứng dụng:

```powershell
docker compose cp tests/excel-import/. app:/tmp/excel-import-test/
docker compose exec -T app chown -R www-data:www-data /tmp/excel-import-test
docker compose exec -T -u www-data app php /tmp/excel-import-test/check-service.php
docker compose exec -T -u www-data app php /tmp/excel-import-test/check-permissions.php
docker compose cp app:/tmp/excel-import-test/fixture.xlsx tests/excel-import/fixture.xlsx
docker compose cp app:/tmp/excel-import-test/multi.xls tests/excel-import/multi.xls
./tests/excel-import/check-http.ps1
```

Fixture đều là dữ liệu tổng hợp do kiểm thử sinh ra, không chứa dữ liệu khách hàng. Kiểm tra HTTP dùng tài khoản admin mặc định của lab, tạo sách/chương/trang riêng rồi đưa sách vào thùng rác; kiểm tra quyền dùng transaction rollback. Không dùng thông tin đăng nhập này trên môi trường thật.

`check-service.php`: XLS/XLSX, nhiều sheet, giá trị công thức, Unicode, ô gộp, font/màu, ảnh nhúng, kiểu text, dữ liệu lỗi, từ chối ảnh không hợp lệ, dọn thư mục tạm. `check-permissions.php`: chặn thiếu quyền tạo trang/ảnh, thiếu quyền xuất hoặc không được xem trang. `check-http.ps1`: menu/form, upload vào sách/chương, nháp → sửa → lưu → tải XLSX, ảnh kho nội bộ, HTTP 422 và chặn người chưa đăng nhập.
