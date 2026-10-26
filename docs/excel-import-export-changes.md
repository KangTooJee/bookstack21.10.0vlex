# Nhập/xuất Excel trên BookStack 21.11.1

Luồng sử dụng giống Word: ở menu hành động của sách, chương hoặc trang, chọn **Nhập Excel (.xls/.xlsx)**. Tải bảng tính tối đa 20 MB, kiểm tra/chỉnh sửa bản nháp rồi lưu trang. Sau khi lưu, chọn **Kết xuất → Bảng tính Excel (.xlsx)** để tải nội dung hiện tại.

## Phạm vi chuyển đổi

Trang Excel dùng hết chiều rộng vùng nội dung (bỏ giới hạn 840/940px), giữ các cột bảng và cho cuộn ngang bên trong bằng chuột/bàn phím; không làm tràn toàn trang. Các trang nhập mới có anchor `bookstack-excel-content`; trang Calc cũ được nhận diện bằng thuộc tính bảng và colgroup. Trang Word thông thường giữ bố cục cũ.

- Nhập `.xls` Excel 97–2003 và `.xlsx`; nhiều sheet trở thành các bảng kèm tên sheet trong cùng một trang. Giữ chữ tiếng Việt, giá trị hiển thị, ô gộp, màu nền, định dạng chữ cơ bản và ảnh nhúng. Chỉ những sheet/nội dung Calc xuất được thành HTML mới được nhập; cần kiểm tra bản nháp.
- Công thức nhập trở thành giá trị hiển thị do Calc tính/đọc; không giữ công thức, macro, pivot, bộ lọc, validation, biểu đồ gốc hoặc cấu hình in. Không hỗ trợ file đặt mật khẩu, `.xlsm`, `.xlsb`, CSV. Macro trong XLS không được phép chạy; XLSX có `vbaProject.bin` bị từ chối.
- Xuất từng trang đã lưu thành **một sheet `Nội dung`**, lần lượt gồm tên trang, văn bản và các bảng. Không khôi phục các tab sheet gốc. Bảng lồng trong ô được chuyển thành chữ trong ô cha. Các liên kết được xuất dưới dạng chữ hiển thị.
- Các ô xuất dùng kiểu **text** để giữ đúng nội dung web: `00123`, ngày tháng, dấu thập phân, chuỗi bắt đầu bằng `=`. Không tự suy đoán kiểu số/ngày hoặc chạy chuỗi như công thức; muốn tính toán cần đổi kiểu ô trong Excel sau khi tải.
- Giữ colspan/rowspan, font/cỡ chữ px/pt và thẻ font cũ, in đậm/nghiêng/gạch chân, màu chữ/nền, căn chỉnh, xuống dòng và viền cơ bản/từng cạnh. CSS phức tạp, chiều rộng cột gốc và vị trí đối tượng nổi có thể khác. Cột xuất có chiều rộng cố định; font trên máy mở file có thể bị thay nếu thiếu font.
- Ảnh trong kho BookStack hoặc data URI được nhúng thành PNG; không tải ảnh từ URL tùy ý. Ảnh được thu vừa cột, giữ tỷ lệ. Nhiều ảnh cùng một ô có thể chồng lên nhau; nên bố trí mỗi ảnh vào một ô riêng khi chỉnh sửa web.
- Giới hạn xuất: 30 MB, 10.000 hàng, 256 cột, 100.000 ô có nội dung, 200 ảnh, 32.767 ký tự/ô. Bảng vượt giới hạn hoặc ô gộp chồng lấn trả thông báo lỗi. Không sửa file gốc hoặc HTML đã lưu.
- Giới hạn nhập bổ sung cho khả năng chỉnh sửa trên một trang: tối đa 10 MB HTML do Calc tạo và 100.000 phần tử HTML trước khi đưa vào editor. File dưới 20 MB vẫn có thể vượt giới hạn sau chuyển đổi. Không tăng timeout hoặc bộ nhớ để ép nhập workbook bất kỳ.

## Điều tra lỗi nhiều sheet/file lớn ngày 26/10/2026

Log Apache ghi `Maximum execution time of 120+2 seconds exceeded` tại `WordImportService.php:293`, bước bỏ đối tượng vẽ nổi. Hai thư mục tạm của lần lỗi đều đã có `workbook.html` 3.508.560 byte từ XLSX 412.260 byte: Calc đã hoàn thành chuyển đổi, không phải mọi lỗi đều nằm ở LibreOffice.

Đã thay duyệt danh sách DOM sống bằng tập node XPath tĩnh; bước tìm shape/frame chỉ truy vấn ứng viên, bỏ việc dò anchor danh sách khi không có dữ liệu thụt lề. Chuẩn hóa màu nền chỉ duyệt node có bgcolor và giải phóng DOM trung gian trước bước lọc tiếp theo.

Kiểm tra có giới hạn, không chuyển đổi lại file gốc nhiều lần:

- Một lần lọc mẫu tổng hợp hai sheet / 20.000 ô / 1.478.081 byte: 0,349 giây, bộ nhớ đỉnh PHP 28,0 MiB.
- Một lần lọc HTML đã có từ lần lỗi: 0,927 giây, giữ đủ 16.394 ô, bộ nhớ đỉnh PHP 34,4 MiB. Không tạo/sửa trang từ nội dung file đó.
- Đây là số đo bước lọc HTML trên máy lab, không phải thời gian end-to-end cho mọi workbook; bộ nhớ báo bởi PHP không bao gồm toàn bộ bộ nhớ native/libreoffice.

Việc gom workbook lớn tùy ý thành một Page HTML đồng bộ không khả thi để bảo đảm ổn định: còn giới hạn thời gian Calc, DOM/editor và bộ nhớ trình duyệt. Hướng tiếp theo phù hợp là cho chọn sheet/vùng dữ liệu, chia mỗi sheet thành Page riêng; nếu cần file rất lớn thì chuyển sang job nền có tiến độ và phân trang dữ liệu. Các luồng này chưa được triển khai. Không tự cắt mất ô hoặc sheet để ép chuyển đổi thành công.

## File tích hợp

Gốc ứng dụng: `source/BookStack-21.11.1/`.

| File | Vai trò |
| --- | --- |
| `app/Http/Controllers/ExcelImportController.php` | Quyền tạo trang/ảnh, form, upload, tạo bản nháp trong transaction |
| `app/Uploads/ExcelImportService.php` | Kiểm tra định dạng/ZIP, gọi Calc, chuẩn hóa màu nền, lọc HTML |
| `app/Uploads/ExcelConversion.php` | Profile LibreOffice riêng, tắt macro/cập nhật liên kết, timeout 60 giây |
| `app/Uploads/ExcelExportService.php` | Render trang đã lưu, lấy ảnh qua ImageService, lọc HTML và xuất XLSX |
| `app/Uploads/ExcelWorkbookWriter.php` | Ghi gói OOXML bằng DOM/ZipArchive sẵn có: text, rich text, style, ô gộp và ảnh nhúng |
| `app/Uploads/ExcelPageLayout.php` | Nhận diện trang Excel mới/cũ để áp dụng bố cục rộng |
| `public/dist/excel-content.css` | CSS riêng cho Excel: bỏ max-width, giữ bảng rộng, cuộn ngang trong nội dung; không cần npm build |
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

`check-html-scale.php`: đúng một mẫu HTML tổng hợp hai sheet/20.000 ô, hạn chạy 15 giây; kiểm tra ngân sách phần tử HTML và nhận diện bố cục Excel cũ/mới. Kiểm tra trình duyệt trên trang đã có: vùng nội dung rộng 778px, bảng rộng 5.645px, `overflow-x:auto`, không tràn chiều ngang document và cuộn bằng bàn phím được.
