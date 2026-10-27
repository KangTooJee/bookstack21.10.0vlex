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

Các ảnh placeholder `ShapeN` không có kích thước và hộp chữ nổi `FrameN` do LibreOffice xuất từ lớp vẽ được bỏ qua khi nhập. Các đối tượng này phụ thuộc vị trí trên trang Word; khi chuyển thành HTML, chúng có thể bị dồn thành ảnh/chữ thừa trước nội dung. Ảnh nhúng thông thường và văn bản trong luồng chính vẫn được giữ. Chú thích nằm trong hộp chữ nổi có thể cần bổ sung lại trong trình soạn thảo web.

Với DOCX, bước chuẩn bị bản sao tài liệu bỏ đối tượng OLE và ảnh biểu tượng/xem trước của chúng (ví dụ biểu tượng gói cài đặt nhúng); ảnh GIF thông thường vẫn được nhập. Độ thụt lề danh sách được lấy từ cấu hình numbering, cấu hình ghi đè và định dạng trực tiếp của đoạn Word, rồi chuyển thành CSS inline. Danh sách lồng nhau dùng độ lệch tương đối để tránh cộng dồn sai. File Word gốc không bị thay đổi.

## Xuất Word

Sau khi lưu nội dung trang, mở menu **Kết xuất / Export → Tài liệu Word (.docx)**. Chức năng xuất dùng nội dung hiện đang lưu trên web, gồm các chỉnh sửa sau import; hỗ trợ tiêu đề, văn bản có định dạng, danh sách nhiều cấp và thụt lề, bảng/ô gộp, liên kết và ảnh nhúng. Quyền `content-export` và quyền xem trang được áp dụng như các định dạng xuất sẵn có. Hiện xuất từng trang, chưa xuất toàn bộ sách/chương.

Ảnh trong kho BookStack được đóng gói vào DOCX để mở độc lập; ảnh ngoài chưa đưa vào kho ảnh sẽ báo lỗi để tránh thiếu ảnh âm thầm. Ảnh quá rộng được thu vừa chiều ngang trang A4. Bố cục ngắt trang, font thay thế và đối tượng nổi phức tạp có thể khác Word gốc. Nội dung chưa lưu trong trình soạn thảo cần lưu trang trước khi xuất.

Khi xuất, chiều rộng phần trăm được quy đổi theo khối chứa: bảng vừa vùng in, ảnh trong ô vừa phần lòng ô, bảng con vừa ô cha. Tỷ lệ cột, colspan/rowspan và tỷ lệ khung hình được giữ; lưới bảng DOCX dùng bố cục cố định để ảnh không tự đẩy rộng cột. Hàng/ô có chiều cao cố định và nowrap được gỡ trong bản xuất để không cắt mất nội dung. Ảnh quá cao được thu vừa trang có chừa chỗ cho tiêu đề/chú thích; bảng dài vẫn có thể tiếp tục ở trang sau. Chuỗi liền quá dài được bổ sung điểm ngắt vô hình trong bản xuất để Word xuống dòng, không đổi HTML đã lưu. `check-export-layout.php` kiểm tra ảnh trong bảng 100%, tỷ lệ cột, bảng lồng, ô gộp, ảnh cao và nội dung dài; cần render DOCX để kiểm tra cả vị trí thực tế trên trang.

Kiểm tra xuất: chạy `tests/word-import/check-export.php` trong container sau kiểm tra service để xác minh DOCX, nội dung chỉnh sửa, bảng, ảnh và vòng xuất/nhập danh sách. `check-http.ps1` kiểm tra tải DOCX qua web, ảnh đóng gói, dữ liệu đã lưu và chặn người chưa đăng nhập.

Font được tính theo kế thừa HTML, định dạng trực tiếp và thẻ font cũ; đổi `px` sang `pt` theo tỷ lệ 0,75, hỗ trợ `em`/`rem`/phần trăm và từ khóa kích thước. Font mặc định/cỡ tiêu đề theo CSS của web (Windows: Segoe UI 14px), không ép về Arial 11pt. DOCX lưu đúng tên font được chỉ định; máy mở file vẫn cần có font đó để tránh font thay thế. Cỡ chữ được làm tròn theo nửa point của Word. Viền bảng/ô được ghi trực tiếp bằng thuộc tính DOCX, giữ từng cạnh, kiểu nét, màu và độ dày; ô khai báo không viền vẫn không có viền. `check-export-appearance.php` xác minh font và viền từ XML đầu ra thay vì chỉ kiểm tra nội dung chữ.

## Nhập và xuất Excel

Chọn **Nhập Excel (.xls/.xlsx)** trong menu sách/chương/trang để nhập bảng tính tối đa **2.048 KB và 5 sheet (kể cả sheet ẩn)** vào bản nháp, sửa rồi lưu trang. Các giới hạn được kiểm tra trước khi gọi bộ chuyển đổi. Chọn **Kết xuất → Bảng tính Excel (.xlsx)** để tải nội dung đã lưu, gồm văn bản, bảng/ô gộp, font/màu/viền cơ bản và ảnh nhúng. Nhiều sheet nhập trở thành các bảng trong một trang; xuất thành một sheet `Nội dung`. Công thức được nhập dưới dạng giá trị hiển thị; các ô xuất là text để giữ `00123`, ngày tháng và chuỗi giống công thức. Không giữ macro, pivot và cấu hình workbook gốc.

Docker bổ sung LibreOffice Calc cho import; export ghi XLSX trực tiếp bằng extension PHP sẵn có, không đổi Composer lock hay phiên bản ứng dụng. Build lại image bằng `docker compose up -d --build`. Xem [phạm vi, file thay đổi và cách kiểm tra Excel](docs/excel-import-export-changes.md).

Trang Excel hiển thị hết chiều rộng vùng nội dung và cuộn ngang khi bảng rộng. Giữ chuột trái trên bảng rồi kéo để cuộn; giữ Shift để chọn chữ. Đã tối ưu bước lọc HTML gây timeout với bảng lớn; giới hạn nhập một trang là 10 MB HTML/100.000 phần tử HTML. Workbook lớn hơn cần tách sheet/vùng dữ liệu; không bảo đảm chuyển mọi workbook thành một Page đồng bộ.

Màn sửa Excel áp dụng cùng bố cục rộng bên trong TinyMCE: bỏ max-width 840px, có thanh cuộn ngang và giữ chuột kéo ngang. Click để sửa ô; giữ Shift để chọn chữ.

## Kiểm tra môi trường và import

`tests/check-runtime.php` xác minh phiên bản, toàn bộ thư viện production khớp lockfile, kết nối MySQL và việc không có Composer ở runtime. `tests/word-import/` chứa kiểm tra chuyển Word, lọc HTML, file lỗi và luồng tải lên → bản nháp → xuất bản → tải ảnh/giữ điểm neo mục lục. Bài kiểm tra HTTP tạo sách thử riêng rồi đưa vào thùng rác.

Kết quả kiểm tra ngày 22/10/2026: PHP 8.0.30, Laravel 8.70.2, BookStack v21.11.1, MySQL 8.0.36; 104 thư viện production khớp lockfile gốc và không có Composer executable trong container web. Chuyển đổi DOC/DOCX và luồng nhập trên HTTP đều qua, gồm tiếng Việt, bảng/ô gộp, ảnh và điểm neo mục lục; file sai định dạng trả HTTP 422, người chưa đăng nhập được chuyển về trang đăng nhập. Sau kiểm tra đã khởi tạo lại database và volumes trống để bàn giao, không giữ sách/trang/ảnh kiểm thử.

## Đối chiếu khi áp dụng lên Vlex thật

Xem [bảng kê file import/export Word và hướng dẫn kiểm tra tương thích](docs/word-import-export-changes.md). Snapshot trạng thái Git và SHA256 từng file được lưu trong [manifest](docs/word-import-export-manifest.json).
