# Bảng kê Word/Excel để tích hợp vào Vlex thật

Cập nhật: 27/10/2026. Nhánh: `files-curl`. Repository: `KangTooJee/bookstack21.10.0vlex`.

Mốc mã nguồn đã kiểm tra: `cd9c636` (đã push). Nền lab: BookStack 21.11.1, Laravel 8.70.2, PHP 8.0.30, MySQL 8.0.36. Đây là bảng kê để đối chiếu và ghép thay đổi; chưa xác nhận tương thích với mã nguồn Vlex thật.

Đường dẫn trong các bảng tính từ gốc repository `bookstack-lab`. Riêng bảng mã ứng dụng tính từ `source/BookStack-21.11.1/`. File `vlex-integration-manifest.json` kèm theo ghi đường dẫn đầy đủ trong repository, tác dụng, SHA256 và Git blob tại mốc mã nguồn. Không gồm tài liệu riêng của người dùng.

## Mã ứng dụng

“Chung” là file chứa điểm tích hợp cả Word và Excel. Cần ghép phần thay đổi vào file tương ứng của Vlex, giữ lại tùy biến hiện có.

| File | Phạm vi | Tác dụng |
| --- | --- | --- |
| `app/Http/Controllers/WordImportController.php` | Word | Form/upload DOC/DOCX, kiểm tra quyền trang/ảnh và tạo bản nháp trong transaction. |
| `app/Uploads/WordImportService.php` | Chung | LibreOffice Writer → HTML; lọc nội dung, ảnh, OLE, đối tượng nổi và thụt lề. Excel dùng chung `cleanHtml`; tối ưu duyệt DOM cho bảng lớn và giữ thuộc tính `span` của nhóm cột. |
| `app/Uploads/WordExportService.php` | Word | Đọc nội dung đã lưu, chuẩn bị HTML/ảnh, chuyển DOCX và đóng gói ảnh, dọn thư mục tạm. |
| `app/Uploads/WordExportLayout.php` | Word | Bố cục A4, lưới bảng/ô gộp, bảng lồng, thu ảnh, bỏ chiều cao cố định gây cắt nội dung. |
| `app/Uploads/WordExportAppearance.php` | Word | Tính font kế thừa và ghi font/cỡ chữ/viền từng cạnh vào DOCX. |
| `app/Http/Controllers/ExcelImportController.php` | Excel | Form/upload XLS/XLSX, kiểm tra quyền và tạo bản nháp; trả lỗi khi vượt giới hạn. |
| `app/Uploads/ExcelImportPolicy.php` | Excel | Chặn trước chuyển đổi: 2.048 KB, 5 sheet kể cả sheet ẩn; đọc metadata OOXML và OLE/BIFF. |
| `app/Uploads/ExcelConversion.php` | Excel | Chạy Calc headless với profile riêng, tắt macro/cập nhật liên kết, timeout và kiểm tra đầu ra. |
| `app/Uploads/ExcelImportService.php` | Excel | Kiểm tra file, Calc → HTML, màu/ảnh/bộ lọc dùng chung, ngân sách HTML, marker Excel và chuẩn hóa lưới. |
| `app/Uploads/ExcelHtmlLayout.php` | Excel | Giữ/phục hồi hàng trống bị ô gộp phủ hết để dòng sau không tràn lưới; giữ nhóm cột/độ rộng nguồn, fixed table layout; ghi chú Calc thành tooltip khi nhập. |
| `app/Uploads/ExcelPageLayout.php` | Excel | Nhận diện trang Excel mới/cũ và URL asset kèm hash để xóa ảnh hưởng cache trình duyệt. |
| `app/Uploads/ExcelExportService.php` | Excel | Đọc nội dung đã lưu, chuẩn hóa lưới cũ và ảnh, gọi writer XLSX, dọn file tạm. |
| `app/Uploads/ExcelWorkbookWriter.php` | Excel | Ghi gói OOXML trực tiếp: text/rich text, ô gộp, font/màu/viền và ảnh. Sửa cấu trúc inline string gây Excel repair; bỏ chiều cao cố định theo số ký tự cho hàng chữ, chỉ dành chỗ cho hàng ảnh. |
| `app/Http/Controllers/PageExportController.php` | Chung | Action xuất Word/Excel, quyền xem và `content-export`, MIME/tên tải xuống/thông báo lỗi. |
| `routes/web.php` | Chung | Route GET/POST import theo sách/chương, GET export Word/Excel theo trang, middleware/throttle. |
| `resources/views/pages/import-word.blade.php` | Word | Form import Word, CSRF, tên trang, file và lỗi upload. |
| `resources/views/pages/parts/word-import-action.blade.php` | Word | Nút import Word có kiểm tra quyền. |
| `resources/views/pages/import-excel.blade.php` | Excel | Form import Excel, thông báo giới hạn và nạp script chặn dung lượng. |
| `resources/views/pages/parts/excel-import-action.blade.php` | Excel | Nút import Excel có kiểm tra quyền. |
| `resources/views/books/show.blade.php` | Chung | Gắn hành động import tại trang sách. |
| `resources/views/chapters/show.blade.php` | Chung | Gắn hành động import tại trang chương. |
| `resources/views/pages/show.blade.php` | Chung | Hành động import/export tại trang; nhận diện Excel, nạp CSS/JS bố cục rộng và kéo ngang. |
| `resources/views/entities/export-menu.blade.php` | Chung | Mục tải DOCX/XLSX cho Page, theo quyền export hiện có. |
| `resources/views/pages/edit.blade.php` | Excel | Nhận diện trang/bản nháp Excel; nạp CSS/JS editor riêng và CSP nonce. |
| `resources/views/pages/parts/wysiwyg-editor.blade.php` | Excel | Chuẩn hóa lưới trước khi đưa HTML vào TinyMCE, phục hồi hàng thiếu của trang Excel cũ. |
| `resources/views/pages/parts/page-display.blade.php` | Excel | Chuẩn hóa lưới khi hiển thị nội dung đã lưu; không tự ghi lại database. |
| `public/dist/excel-content.css` | Excel | Nội dung rộng, cuộn ngang trong vùng trang, giữ độ rộng cột nguồn. |
| `public/dist/excel-content.js` | Excel | Giữ chuột kéo ngang ở màn xem; Shift chọn chữ, dọn trạng thái kéo khi thả/mất focus. |
| `public/dist/excel-editor.css` | Excel | Bỏ max-width 840px trong editor/iframe và tên trang, cuộn ngang và giữ lưới nguồn. |
| `public/dist/excel-editor.js` | Excel | Hook TinyMCE pre-init/setup để gắn CSS/class và kéo ngang trong iframe, dọn listener. |
| `public/dist/excel-import.js` | Excel | Chặn file quá 2.048 KB ngay khi chọn; server vẫn kiểm tra lại. |
| `resources/lang/en/entities.php` | Chung | Nhãn menu export Word/Excel tiếng Anh. |
| `resources/lang/vi/entities.php` | Chung | Nhãn menu export Word/Excel tiếng Việt. |

## Môi trường

| File | Phạm vi | Tác dụng |
| --- | --- | --- |
| `Dockerfile` | Môi trường | PHP/extensions/font và LibreOffice Writer + Calc. Khi chuyển sang Vlex cần đối chiếu runtime thực tế, không nhất thiết dùng image lab. |
| `compose.yaml` | Tham chiếu lab | App, MySQL, URL/port và volume lab; không phải cấu hình triển khai production Vlex. |
| `docker/entrypoint.sh` | Tham chiếu lab | Chuẩn bị môi trường và khởi động container; đối chiếu cách khởi động/config/cache trên máy đích. |

Không đổi Composer dependencies/lockfile hoặc nâng phiên bản ứng dụng cho tính năng này. Asset Excel là các file riêng, không cần npm build trong lab. Không chép thông tin đăng nhập hoặc `.env` của lab sang hệ thống thật.

## Kiểm tra hồi quy

Các đường dẫn sau tính từ gốc repository. Chạy trên môi trường kiểm thử có dữ liệu sao chép phù hợp; test HTTP tạo dữ liệu rồi đưa vào thùng rác, không chạy bằng tài khoản lab trên production.

| File | Phạm vi | Tác dụng |
| --- | --- | --- |
| `tests/word-import/check-service.php` | Test Word | DOC/DOCX, tiếng Việt, ảnh, danh sách/thụt lề, OLE/đối tượng nổi và HTML an toàn. |
| `tests/word-import/check-docx-layout.php` | Test Word | Kiểm tra bố cục/numbering trong nguồn DOCX và import. |
| `tests/word-import/check-export.php` | Test Word | DOCX từ nội dung đã sửa, bảng/ô gộp, ảnh nhúng và danh sách. |
| `tests/word-import/check-export-layout.php` | Test Word | Lưới cột/bảng lồng, ảnh lớn, ngắt nội dung dài và vùng in. |
| `tests/word-import/check-export-appearance.php` | Test Word | Font và viền từng cạnh từ XML DOCX. |
| `tests/word-import/check-http.ps1` | Test Word | Web upload → nháp → lưu → tải DOCX/ảnh, chặn chưa đăng nhập. |
| `tests/excel-import/check-service.php` | Test Excel | XLS/XLSX nhiều sheet, text/giá trị công thức, ô gộp, font/viền/ảnh; cấu trúc inline string, file lỗi và dọn tạm. |
| `tests/excel-import/check-permissions.php` | Test Excel | Quyền tạo trang/ảnh, xem trang và xuất nội dung. |
| `tests/excel-import/check-preflight.php` | Test Excel | Chặn dung lượng/số sheet kể cả tab ẩn trước chuyển đổi cho XLS và XLSX. |
| `tests/excel-import/check-html-scale.php` | Test Excel | Tốc độ bộ lọc với bảng tổng hợp lớn, ngân sách phần tử và nhận diện Excel. |
| `tests/excel-import/check-table-layout.php` | Test Excel | Hàng trống dưới ô gộp, phục hồi hàng thiếu, span/độ rộng cột, ghi chú và không đổi HTML Word. |
| `tests/excel-import/check-row-heights.php` | Test Excel | Hàng chữ không có ht/customHeight; giữ wrap, ô gộp và khoảng dành cho ảnh. |
| `tests/excel-import/check-http.ps1` | Test Excel | Web upload → nháp → sửa → lưu → tải XLSX; HTTP 422, giới hạn và đăng nhập. |

Fixture tổng hợp trong `tests/word-import/` (`fixture.html`, `fixture.png`, `fixture.doc`, `fixture.docx`) và `tests/excel-import/` (`fixture.xlsx`, `multi.xls`) phục vụ kiểm thử, không cần đưa vào ứng dụng production.

## Các điểm cần đối chiếu trên Vlex thật

1. **Model và nội dung:** namespace Page/Book/Chapter, PageRepo/PageContent, HTML lưu thực tế, bản nháp/autosave/revision và transaction có giống lab không. Chuẩn hóa lưới chỉ sửa bản hiển thị/editor/export, không chạy migration ghi đè trang cũ.
2. **Quyền và route:** đăng nhập, quyền tạo trang/ảnh theo sách/chương, quyền xem và `content-export`; prefix URL, middleware, throttle và route tùy biến. Ghép route/action/menu theo diff, không chép đè nguyên controller hay template Vlex.
3. **Editor và HTML:** lab dùng TinyMCE 4 với hook `editor-tinymce::pre-init` và `editor-tinymce::setup`. Đối chiếu hook/schema/bộ lọc Vlex có giữ ID của hàng trống, anchor Excel, `colgroup span`, width, rowspan/colspan không. Kiểm tra cả sau lưu và mở lại, không chỉ bản nháp đầu tiên.
4. **CSS/JS và CSP:** selector/container trang thật, cascade max-width/table-layout, iframe content_css, CSP nonce và cơ chế cache asset. Kiểm tra xem/sửa đều rộng đúng, cuộn nằm trong nội dung, kéo ngang/Shift/click sửa ô hoạt động.
5. **Ảnh và filesystem:** kho ảnh công khai/riêng, URL có prefix/proxy, quyền đọc ghi của web worker, GD/Zip/DOM/mbstring, thư mục tạm và dọn khi lỗi. Không tải ảnh ngoài tùy ý khi export.
6. **Bộ chuyển đổi:** đường dẫn LibreOffice, Writer/Calc và font, quyền chạy process/profile riêng, timeout/process bị hủy, không bật macro/cập nhật liên kết. Đối chiếu upload limit của proxy/web/PHP với giới hạn trong ứng dụng.
7. **Kết quả thực tế:** mở DOCX/XLSX bằng ứng dụng người dùng đang dùng, kiểm tra không repair; đối chiếu text, ảnh, ô gộp, cột và các trang có sửa sau import. AutoFit ô gộp của Excel khác bố cục CSS; file xuất chưa phục hồi đầy đủ workbook/công thức/tab gốc.

Luồng nghiệm thu tối thiểu: import vào sách và chương → kiểm tra bản nháp → sửa một ô/đoạn → lưu → mở lại → export → mở bằng Word/Excel. Thử thêm tài khoản thiếu quyền, file sai định dạng, quá 2.048 KB/quá 5 sheet, ô gộp toàn hàng, text dài/ảnh và một trang Word thường để phát hiện ảnh hưởng từ bộ lọc dùng chung.

## Mốc thay đổi để tra Git

| Commit | Nội dung |
| --- | --- |
| `b6eba14` | Hoàn thiện import Word và export DOCX với ảnh nhúng. |
| `a0e7591` | Thêm import/export Excel. |
| `12e2a7c` | Excel full width và sửa timeout lọc bảng lớn. |
| `7d49d77` | Sửa XLSX repair/rich text, kéo ngang và chặn dung lượng/sheet. |
| `776c088` | Excel editor full width, kéo ngang trong iframe. |
| `93bbb5f` | Giữ hàng dưới ô gộp, độ rộng cột nguồn và ghi chú. |
| `cd9c636` | Bỏ chiều cao hàng chữ cố định khi xuất XLSX. |

Ví dụ đối chiếu toàn bộ lớp Excel: `git diff b6eba14..cd9c636 -- source/BookStack-21.11.1/ Dockerfile`. So sánh riêng file bằng `git show cd9c636:source/BookStack-21.11.1/app/Uploads/ExcelWorkbookWriter.php`. Khi ghép sang Vlex, dùng mốc này để xem diff rồi sửa theo cấu trúc đích.

Chi tiết bổ sung: [Word](word-import-export-changes.md), [Excel](excel-import-export-changes.md). Các manifest Word/Excel cũ là snapshot theo thời điểm lập, có thể chứa trạng thái chưa commit hoặc hash cũ; ưu tiên manifest tổng hợp cùng tài liệu này cho mốc `cd9c636`.
