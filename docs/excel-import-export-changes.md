# Nhập/xuất Excel trên BookStack 21.11.1

Luồng sử dụng giống Word: ở menu hành động của sách, chương hoặc trang, chọn **Nhập Excel (.xls/.xlsx)**. Tải bảng tính tối đa **2.048 KB (2.097.152 byte) và 5 sheet**, tính cả sheet ẩn; kiểm tra/chỉnh sửa bản nháp rồi lưu trang. Sau khi lưu, chọn **Kết xuất → Bảng tính Excel (.xlsx)** để tải nội dung hiện tại.

## Phạm vi chuyển đổi

Trang Excel dùng hết chiều rộng vùng nội dung (bỏ giới hạn 840/940px), giữ các cột bảng và cho cuộn ngang bên trong bằng chuột/bàn phím; không làm tràn toàn trang. Các trang nhập mới có anchor `bookstack-excel-content`; trang Calc cũ được nhận diện bằng thuộc tính bảng và colgroup. Trang Word thông thường giữ bố cục cũ.

Giữ chuột trái trên bảng và kéo để cuộn ngang; ngưỡng kéo 5px tránh nhầm thao tác click. Giữ Shift để chọn/copy chữ, liên kết và điều khiển vẫn click bình thường. Khi thả chuột, hủy pointer hoặc cửa sổ mất focus, chế độ kéo kết thúc. Thiết bị cảm ứng tiếp tục dùng thao tác vuốt tự nhiên.

Từ 27/10/2026, màn **Edit** của Excel cũng dùng hết chiều rộng vùng soạn thảo và cuộn ngang ngay trong iframe TinyMCE. Dùng hook `editor-tinymce::pre-init` để nạp CSS riêng và class cho body iframe, hook `editor-tinymce::setup` để gắn xử lý kéo khi editor khởi tạo. Bỏ giới hạn 840px ở cả body nội dung và ô tên trang. Kéo chuột ngang không ghi lại wrapper/style vào HTML tài liệu, không đổi dữ liệu; click vẫn đặt được con trỏ sửa ô, giữ Shift để chọn chữ. Chỉ tải các asset này cho trang được nhận diện là Excel, áp dụng cả bản nháp. Không sửa CSS hoặc cấu hình chung của editor Word.

- Nhập `.xls` Excel 97–2003 và `.xlsx`; nhiều sheet trở thành các bảng kèm tên sheet trong cùng một trang. Giữ chữ tiếng Việt, giá trị hiển thị, ô gộp, màu nền, định dạng chữ cơ bản và ảnh nhúng. Chỉ những sheet/nội dung Calc xuất được thành HTML mới được nhập; cần kiểm tra bản nháp.
- Công thức nhập trở thành giá trị hiển thị do Calc tính/đọc; không giữ công thức, macro, pivot, bộ lọc, validation, biểu đồ gốc hoặc cấu hình in. Không hỗ trợ file đặt mật khẩu, `.xlsm`, `.xlsb`, CSV. Macro trong XLS không được phép chạy; XLSX có `vbaProject.bin` bị từ chối.
- Xuất từng trang đã lưu thành **một sheet `Nội dung`**, lần lượt gồm tên trang, văn bản và các bảng. Không khôi phục các tab sheet gốc. Bảng lồng trong ô được chuyển thành chữ trong ô cha. Các liên kết được xuất dưới dạng chữ hiển thị.
- Các ô xuất dùng kiểu **text** để giữ đúng nội dung web: `00123`, ngày tháng, dấu thập phân, chuỗi bắt đầu bằng `=`. Không tự suy đoán kiểu số/ngày hoặc chạy chuỗi như công thức; muốn tính toán cần đổi kiểu ô trong Excel sau khi tải.
- Giữ colspan/rowspan, font/cỡ chữ px/pt và thẻ font cũ, in đậm/nghiêng/gạch chân, màu chữ/nền, căn chỉnh, xuống dòng và viền cơ bản/từng cạnh. CSS phức tạp, chiều rộng cột gốc và vị trí đối tượng nổi có thể khác. Cột xuất có chiều rộng cố định; font trên máy mở file có thể bị thay nếu thiếu font.
- Ảnh trong kho BookStack hoặc data URI được nhúng thành PNG; không tải ảnh từ URL tùy ý. Ảnh được thu vừa cột, giữ tỷ lệ. Nhiều ảnh cùng một ô có thể chồng lên nhau; nên bố trí mỗi ảnh vào một ô riêng khi chỉnh sửa web.
- Giới hạn xuất: 30 MB, 10.000 hàng, 256 cột, 100.000 ô có nội dung, 200 ảnh, 32.767 ký tự/ô. Bảng vượt giới hạn hoặc ô gộp chồng lấn trả thông báo lỗi. Không sửa file gốc hoặc HTML đã lưu.
- Giới hạn nhập bổ sung cho khả năng chỉnh sửa trên một trang: tối đa 10 MB HTML do Calc tạo và 100.000 phần tử HTML trước khi đưa vào editor. File dưới 2.048 KB vẫn có thể vượt giới hạn sau chuyển đổi. Không tăng timeout hoặc bộ nhớ để ép nhập workbook bất kỳ.

## Chặn sớm và sửa lỗi Excel repair

`ExcelImportPolicy` là nơi đặt giới hạn `MAX_KB = 2048`, `MAX_SHEETS = 5`. Trình duyệt chặn file quá dung lượng khi chọn, server kiểm tra lại khi nhận upload. Server đọc metadata trước khi tạo thư mục chuyển đổi/gọi Calc: XLSX đếm sheet từ `xl/workbook.xml`, XLS đọc directory/FAT/MiniFAT của container OLE và các record BIFF BOUNDSHEET. Tính cả sheet ẩn và các tab đặc biệt. Nếu không đọc được metadata XLS, từ chối và yêu cầu lưu lại thành XLSX; không gọi Calc để đoán số tab.

File export được báo lỗi có một inline string chứa lẫn `<t/>` và các rich-text run `<r>`, đồng thời có nhiều `<t>` trực tiếp trong cùng `<is>`. XML vẫn well-formed nhưng vi phạm cấu trúc SpreadsheetML, khiến Excel thay thế `sheet1.xml`. Đã sửa để bookmark/span/ảnh rỗng không chèn `<t/>` vào giữa các rich-text run; chỉ ô hoàn toàn rỗng mới dùng một `<t/>`. Test hồi quy kiểm tra cả cấu trúc inline-string thay vì chỉ parse XML/đọc lại bằng Calc.

Xuất lại trang gây lỗi: 359 ô, toàn bộ giá trị text khớp với file cũ, 0 inline string trộn cấu trúc. Đã kiểm tra bằng phân tích XML và mở thành công bằng Microsoft Excel 16.0 qua `Workbooks.Open` ở chế độ đọc, tải bình thường (không yêu cầu repair/recovery), rồi đóng không lưu. Macro bị tắt, không cập nhật liên kết ngoài. Bản xuất mới cần tải lại, file tải trước bản sửa không tự thay đổi. [Microsoft mô tả chế độ mở qua object model](https://learn.microsoft.com/en-us/office/vba/api/excel.workbooks.open).

## Điều tra lỗi nhiều sheet/file lớn ngày 26/10/2026

Log Apache ghi `Maximum execution time of 120+2 seconds exceeded` tại `WordImportService.php:293`, bước bỏ đối tượng vẽ nổi. Hai thư mục tạm của lần lỗi đều đã có `workbook.html` 3.508.560 byte từ XLSX 412.260 byte: Calc đã hoàn thành chuyển đổi, không phải mọi lỗi đều nằm ở LibreOffice.

Đã thay duyệt danh sách DOM sống bằng tập node XPath tĩnh; bước tìm shape/frame chỉ truy vấn ứng viên, bỏ việc dò anchor danh sách khi không có dữ liệu thụt lề. Chuẩn hóa màu nền chỉ duyệt node có bgcolor và giải phóng DOM trung gian trước bước lọc tiếp theo.

Kiểm tra có giới hạn, không chuyển đổi lại file gốc nhiều lần:

- Một lần lọc mẫu tổng hợp hai sheet / 20.000 ô / 1.478.081 byte: 0,349 giây, bộ nhớ đỉnh PHP 28,0 MiB.
- Một lần lọc HTML đã có từ lần lỗi: 0,927 giây, giữ đủ 16.394 ô, bộ nhớ đỉnh PHP 34,4 MiB. Không tạo/sửa trang từ nội dung file đó.
- Đây là số đo bước lọc HTML trên máy lab, không phải thời gian end-to-end cho mọi workbook; bộ nhớ báo bởi PHP không bao gồm toàn bộ bộ nhớ native/libreoffice.

Việc gom workbook lớn tùy ý thành một Page HTML đồng bộ không khả thi để bảo đảm ổn định: còn giới hạn thời gian Calc, DOM/editor và bộ nhớ trình duyệt. Hướng tiếp theo phù hợp là cho chọn sheet/vùng dữ liệu, chia mỗi sheet thành Page riêng; nếu cần file rất lớn thì chuyển sang job nền có tiến độ và phân trang dữ liệu. Các luồng này chưa được triển khai. Không tự cắt mất ô hoặc sheet để ép chuyển đổi thành công.

## File tích hợp

### Chiều cao hàng khi xuất XLSX (27/10/2026)

Bỏ ước lượng `15 * ceil(số ký tự / 24)` và `customHeight="1"` cho tất cả hàng chữ. Cách cũ không tính chiều rộng ô gộp/font, khiến nội dung dài tạo hàng cố định tới 409pt. Hàng chữ mới không ghi `ht`/`customHeight`, vẫn giữ `wrapText="1"`; không lấy chiều cao cố định từ HTML. Chỉ hàng có drawing ảnh dành chiều cao theo ảnh vì drawing không tham gia AutoFit. Không thay đổi HTML đã lưu.

`check-row-heights.php` kiểm tra text dài gộp ngang, gộp dọc, xuống dòng, HTML có height lớn, giữ wrap/ảnh, cấu trúc inline string và tái sử dụng writer không rò chiều cao ảnh. Kiểm tra service XLS/XLSX nhập/xuất cũng qua. Chiều cao tự động khi mở, đặc biệt AutoFit của ô gộp, do ứng dụng bảng tính quyết định; XLSX không có CSS `max-content` tương đương trình duyệt.

### Sửa lệch hàng sau ô gộp (27/10/2026)

TinyMCE 4 xóa `<tr></tr>` không có ID. Khi tiêu đề gộp dọc phủ toàn bộ cột, xóa hàng này nhưng vẫn giữ `rowspan` khiến dòng kế tiếp bị đẩy sang ngoài lưới. `ExcelHtmlLayout` gắn ID cho hàng trống, phục hồi hàng bị mất trong nội dung Excel cũ dựa trên metadata cột và vùng rowspan, không thêm ô giả. Chuẩn hóa được áp dụng khi nhập, xem, mở editor và xuất; không tự ghi đè nội dung trang đã lưu.

Giữ `colgroup span`, độ rộng cột và dùng bố cục bảng cố định theo tổng độ rộng nguồn, thay vì để nội dung tự kéo giãn cột. Độ rộng inline mà người dùng đã sửa vẫn được giữ. Ghi chú `<comment>` từ Calc được chuyển thành thuộc tính `title` khi nhập mới để không trộn metadata vào giá trị hiển thị. CSS/JS Excel có hash nội dung trong URL để trình duyệt nhận bản cập nhật dù phiên bản BookStack không đổi.

Đối chiếu với workbook người dùng cung cấp: hai bảng, 40 hàng, 350 ô; hàng “0” nằm đúng cột A–J. Sau thao tác Edit → Lưu trên sách kiểm thử riêng, cả 350 giá trị ô không đổi và độ rộng 10 cột giữ theo metadata nguồn. Không ghi đè trang người dùng đang sửa và không đưa workbook vào repository. Đây là kiểm tra lưới bảng của file này, không mở rộng cam kết sang macro/pivot hay toàn bộ đối tượng Excel.

`tests/excel-import/check-table-layout.php` kiểm tra hàng gộp phủ toàn lưới, phục hồi hàng thiếu, rowspan một phần, span/độ rộng cột, tính lặp không đổi, chú thích và HTML Word không bị sửa. Kiểm tra HTTP XLS/XLSX nhập → sửa → lưu → xuất và kiểm tra service Word đều qua.

Gốc ứng dụng: `source/BookStack-21.11.1/`.

| File | Vai trò |
| --- | --- |
| `app/Http/Controllers/ExcelImportController.php` | Quyền tạo trang/ảnh, form, upload, tạo bản nháp trong transaction |
| `app/Uploads/ExcelImportService.php` | Kiểm tra định dạng/ZIP, gọi Calc, chuẩn hóa màu nền, lọc HTML |
| `app/Uploads/ExcelImportPolicy.php` | Kiểm tra 2.048 KB/5 sheet của XLSX và XLS trước chuyển đổi, không gọi LibreOffice |
| `app/Uploads/ExcelConversion.php` | Profile LibreOffice riêng, tắt macro/cập nhật liên kết, timeout 60 giây |
| `app/Uploads/ExcelExportService.php` | Render trang đã lưu, lấy ảnh qua ImageService, lọc HTML và xuất XLSX |
| `app/Uploads/ExcelWorkbookWriter.php` | Ghi gói OOXML bằng DOM/ZipArchive sẵn có: text, rich text, style, ô gộp và ảnh nhúng |
| `app/Uploads/ExcelPageLayout.php` | Nhận diện trang Excel mới/cũ để áp dụng bố cục rộng |
| `public/dist/excel-content.css` | CSS riêng cho Excel: bỏ max-width, giữ bảng rộng, cuộn ngang trong nội dung; không cần npm build |
| `public/dist/excel-content.js` | Giữ chuột kéo ngang, xử lý capture/thả chuột; script có CSP nonce |
| `public/dist/excel-import.js` | Chặn file quá dung lượng ngay khi chọn trên form |
| `resources/views/pages/edit.blade.php` | Nhận diện Excel và nạp CSS/JS riêng cho màn sửa |
| `public/dist/excel-editor.css` | Bỏ max-width trong iframe, giữ bảng rộng và thanh cuộn ngang; title dùng hết chiều rộng |
| `public/dist/excel-editor.js` | Tích hợp qua hook TinyMCE có sẵn; giữ chuột kéo ngang trong iframe và dọn listener khi editor đóng |
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

`check-preflight.php`: chấp nhận 5 sheet, từ chối 6 sheet gồm sheet ẩn cho cả XLSX và XLS thực sinh bởi Calc; từ chối file quá 2.048 KB, xác minh không tạo thư mục chuyển đổi. Test HTTP bổ sung kiểm tra 422 cho file quá dung lượng/quá tab. Kiểm tra chuột trên trình duyệt: kéo 400px cuộn đúng 400px, không chọn chữ thừa và kết thúc trạng thái kéo khi thả chuột.

Kiểm tra màn sửa trong trình duyệt ngày 27/10/2026: body iframe có `max-width:none`, rộng 1.217px theo viewport soạn thảo; nội dung cuộn rộng 5.660px. Kéo ngang 400px đổi scrollLeft từ 0 thành 400, thả chuột kết thúc trạng thái kéo, giữ đủ 350 ô của bản nháp đang mở và toàn bộ text không đổi. Click sau khi kéo vẫn đặt con trỏ `Caret` trong editor. Không lưu/publish thay đổi vào nội dung người dùng trong quá trình kiểm tra.
