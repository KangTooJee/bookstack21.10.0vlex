# Bảng kê PDF để đối chiếu và tích hợp vào Vlex thật

Cập nhật: **28/10/2026**, múi giờ Asia/Saigon. Bản cuối: **nhập PDF gốc → chỉ xem → xuất nguyên file**, không phải bản trích chữ sang HTML trước đó.

## Mốc nguồn và phạm vi

- Repository local: `C:/Users/Vissoft_PC/Downloads/VLEX/bookstack-lab`.
- Gốc ứng dụng: `source/BookStack-21.11.1/`.
- Nhánh: `files-curl`; HEAD nền: `b7f6a3a22c4e35b9da6a5ce818309889b9960af5` (`Document Word and Excel file map for Vlex integration review`).
- **Các thay đổi PDF trong bảng này chưa commit tại thời điểm ghi nhận. HEAD trên không chứa toàn bộ tính năng PDF.** Không chỉ checkout HEAD đó để lấy tính năng này.
- [Manifest PDF](pdf-import-export-manifest.json) ghi đường dẫn đầy đủ trong repo, tác dụng, trạng thái Git và SHA-256 của bản file hiện tại. Hash này dùng đối chiếu mã nguồn, không phải hash tài liệu PDF của người dùng. Manifest không tự ghi hash của chính nó; cần tạo lại nếu sửa file sau snapshot.
- Nền đã kiểm tra: BookStack 21.11.1, PHP 8.0.30, Laravel 8.70.2, MySQL 8.0.36. Chưa xác nhận trên mã nguồn/deployment Vlex thật.
- [Lưu ý tương thích trình duyệt](pdf-browser-compatibility-notes.md) được tách riêng, gồm giới hạn preview, menu/toolbar và bảng kiểm thử cần thực hiện.

## Hành vi đã chốt

Nhập file `.pdf` tối đa 20 MB từ sách/chương hoặc hành động trên trang; tạo trang tài liệu và mở trực tiếp, không đi qua editor. File được lưu nguyên byte trong kho attachment riêng của BookStack. Sau khi ghi, so SHA-256 file lưu với file upload trước khi commit transaction. Không trích chữ, chuyển định dạng, giải mã hoặc render lại PDF ở máy chủ; không giới hạn 50 trang và không yêu cầu OCR.

Preview dùng iframe mở trực tiếp file nguồn. Cấu hình khung nhúng hiện tại là `#toolbar=1&navpanes=0&pagemode=none`: **giữ thanh công cụ, mặc định đóng menu bên cạnh** nếu viewer hỗ trợ. Nút mở tab mới dùng URL PDF không kèm các tham số này. Preview không bảo đảm hoạt động trên mọi trình duyệt; xem file lưu ý riêng.

Xuất PDF của từng trang trả file gốc, không chạy bộ tạo PDF từ HTML. Web giữ tên file gốc qua Content-Disposition hỗ trợ UTF-8. API xuất PDF cũng trả bytes nguồn. Trang PDF không có nút sửa/copy, menu xuất Word/Excel/HTML hoặc hành động khôi phục ở menu trang. Máy chủ chặn sửa nội dung, lưu nháp, khôi phục revision và thay thế/xóa riêng attachment, kể cả qua API. Người có quyền vẫn được di chuyển hoặc xóa trang tài liệu theo cơ chế BookStack; xóa vĩnh viễn trang vẫn dọn attachment theo cơ chế sẵn có.

Trang HTML/Word/Excel thông thường tiếp tục sửa và xuất như trước. Trình xem của trình duyệt có thể có chức năng ghi chú/lưu bản khác; chức năng đó không ghi ngược vào file ở BookStack.

## Từng file mã ứng dụng mới hoặc sửa

**Các đường dẫn trong bảng này tính từ gốc ứng dụng**, không phải gốc repo. “Mới/Sửa” là trạng thái so với HEAD nền nêu trên. Với file có sẵn, ghép riêng phần thay đổi PDF vào Vlex, giữ các tùy biến hiện hữu; không chép đè toàn bộ file.

| File | Trạng thái | Tác dụng và phần cần đối chiếu trên Vlex |
| --- | --- | --- |
| `database/migrations/2026_10_27_180000_add_pdf_attachment_to_pages.php` | Mới | Thêm cột nullable unsigned integer `pages.pdf_attachment_id`; rollback bỏ cột. Đối chiếu tên bảng, kiểu khóa attachment và cơ chế migration thật. Cột là dấu loại trang và liên kết file gốc, không suy đoán từ tên file/HTML. |
| `app/Entities/Models/Page.php` | Sửa | Thêm `isPdf()` và `originalPdf()`. Lấy attachment đúng `uploaded_to` của trang, đúng ID đã lưu, không phải link ngoài; thiếu file/record thì báo lỗi thay vì tạo PDF thay thế. |
| `app/Uploads/PdfImportService.php` | Mới | Chỉ kiểm tra extension `.pdf`, tối đa 20 MB, MIME `application/pdf`, header `%PDF-` và `%%EOF` trong 4096 byte cuối. Không còn hàm chuyển PDF sang HTML. Đây là kiểm tra cơ bản, không xác minh toàn bộ cấu trúc PDF. |
| `app/Http/Controllers/PdfImportController.php` | Mới | Form/import theo sách hoặc chương; kiểm tra đăng nhập, `page-create`, `attachment-create-all`; validate request; lưu attachment, tạo/publish trang, so hash rồi gắn ID PDF trong transaction. Khi lỗi sau khi đã có attachment thì dọn file. Action `preview()` kiểm tra quyền xem và trả đúng bytes với MIME PDF, inline, private/no-store, nosniff. |
| `routes/web.php` | Sửa | Thêm import PdfImportController; GET/POST `/books/{bookSlug}/import-pdf` và `/books/{bookSlug}/chapter/{chapterSlug}/import-pdf`; POST throttle 10 lần/phút. Thêm GET `/books/{bookSlug}/page/{pageSlug}/pdf-preview` trong nhóm route auth. Route export PDF từng trang đã có sẵn. |
| `resources/views/pages/import-pdf.blade.php` | Mới | Form tên trang/file, CSRF, lỗi validation và giới hạn 20 MB; giải thích lưu nguyên file, chỉ xem, hỗ trợ scan và phụ thuộc trình duyệt. Nút nhập mở trang xem, không mở bản nháp. |
| `resources/views/pages/parts/pdf-import-action.blade.php` | Mới | Nút “Nhập PDF (.pdf)” khi đăng nhập và có quyền tạo trang tại parent cùng quyền tạo attachment. |
| `resources/views/books/show.blade.php` | Sửa | Chèn partial nhập PDF trong menu sách, parent là sách hiện tại. |
| `resources/views/chapters/show.blade.php` | Sửa | Chèn partial nhập PDF trong menu chương, parent là chương hiện tại. |
| `resources/views/pages/show.blade.php` | Sửa | Trang PDF hiển thị tiêu đề, thông báo chỉ xem, iframe và nút mở/tải; khung giữ toolbar và mặc định đóng menu bên cạnh. Bỏ pointer sửa từng đoạn, nút edit/copy và mục revisions tại menu actions cho PDF. Thêm hành động nhập PDF vào parent của trang; giữ luồng hiển thị Excel/HTML cũ. |
| `resources/views/entities/export-menu.blade.php` | Sửa | Nếu entity là trang PDF chỉ hiển thị “Tải PDF gốc”; các loại trang/sách/chương khác giữ menu xuất hiện có. |
| `app/Http/Controllers/PageExportController.php` | Sửa | `pdf()` nhận diện PDF để đọc attachment và trả file nguyên vẹn, MIME PDF, tên gốc UTF-8 và private/no-store. Trang thường vẫn xuất từ HTML. Chặn xuất Word/Excel/HTML/plain text/Markdown của trang PDF. Middleware `content-export` và PageRepo kiểm tra khả năng xem vẫn được dùng. |
| `app/Entities/Tools/ExportFormatter.php` | Sửa | `pageToPdf()` trả bytes của attachment nếu là PDF; chặn xuất HTML chứa tài nguyên/plain text/Markdown cho PDF. Các hàm xuất trang thường và sách/chương vẫn tồn tại. Đây là điểm dùng chung với API nên không chỉ sửa riêng web controller. |
| `app/Http/Controllers/Api/PageExportApiController.php` | Sửa | Response xuất PDF có MIME `application/pdf` tường minh. API dùng `ExportFormatter::pageToPdf()` nên nhận file gốc cho trang PDF, giữ quyền view/export và tên tải theo slug của API. |
| `app/Http/Controllers/PageController.php` | Sửa | Action `edit()` trả 403 nếu trang là PDF, chặn người gõ URL editor trực tiếp. Các đường ghi nội dung được khóa tiếp tại repo/content. |
| `app/Entities/Repos/PageRepo.php` | Sửa | Chặn publish lại trang đã đánh dấu PDF, update, updatePageDraft, restoreRevision và copy PDF. Các endpoint web/API dùng repo cùng nhận giới hạn chỉ xem; di chuyển và xóa trang không bị chặn bởi thay đổi này. |
| `app/Entities/Tools/PageContent.php` | Sửa | `setNewHTML()` và `setNewMarkdown()` chặn trang PDF trước khi xử lý nội dung/ảnh; không chỉ dựa vào việc ẩn nút editor. |
| `app/Uploads/AttachmentService.php` | Sửa | Chặn `saveUpdatedUpload()` và `updateFile()` của attachment thuộc trang PDF, tránh thay bytes hoặc biến file gốc thành liên kết. Giữ `deleteFile()` nội bộ để rollback import và xóa vĩnh viễn trang vẫn dọn file được. |
| `app/Http/Controllers/AttachmentController.php` | Sửa | Chặn endpoint xóa riêng attachment của trang PDF. Thay file/sửa metadata ở web còn được chặn bởi AttachmentService. Luồng đọc/tải attachment sẵn có tiếp tục kiểm tra quyền xem trang. |
| `app/Http/Controllers/Api/AttachmentApiController.php` | Sửa | Chặn update PDF attachment **trước khi đổi `uploaded_to`**, tránh chuyển attachment sang trang khác để vượt giới hạn; chặn xóa riêng attachment PDF qua API. |

## File kiểm thử, tài liệu và cấu hình

**Các đường dẫn trong bảng này tính từ gốc repository `bookstack-lab`.**

| File | Trạng thái | Tác dụng |
| --- | --- | --- |
| `tests/pdf-import/check-service.php` | Mới | Tạo fixture tổng hợp bằng Dompdf sẵn có: chữ/bảng/ảnh, scan, trộn chữ/scan, mã hóa, 51 trang; kiểm tra file sai/cụt, extension, bytes không đổi và chặn sửa qua repo/content. Bootstrap lab `/var/www/html`, dùng `/tmp/pdf-import-test`. |
| `tests/pdf-import/check-http.ps1` | Mới | Đăng nhập lab, tạo sách thử; import cả 5 loại PDF; so SHA-256 nguồn với preview và export; thử edit, lưu nháp, sửa/thay/xóa attachment, xuất định dạng khác; kiểm tra 403/422, chặn khách và editor trang thường. Cuối cùng đưa sách thử vào thùng rác. Cần đổi URL/tài khoản trước khi chạy staging thật. |
| `tests/pdf-import/.gitignore` | Mới | Loại PDF, ảnh, text và HTML trung gian đã chỉ định khỏi Git; không đưa file sinh tự động hoặc tài liệu thật của người dùng vào bộ mã tích hợp. |
| `README.md` | Sửa | Cách dùng PDF chỉ xem, giới hạn, yêu cầu migration, PDF cũ cần nhập lại, và liên kết đến các tài liệu PDF. |
| `docs/pdf-import-export-changes.md` | Mới | Bảng kê và hướng dẫn đối chiếu hiện tại. |
| `docs/pdf-browser-compatibility-notes.md` | Mới | File riêng ghi các giới hạn trình duyệt, cách fallback và ma trận kiểm thử trước triển khai. |
| `docs/pdf-import-export-manifest.json` | Mới | Snapshot đường dẫn, tác dụng, trạng thái Git và SHA-256 để đối chiếu đúng bản file. Không tự chứa hash của chính manifest. |

`Dockerfile`, `compose.yaml`, `composer.json` và `composer.lock` **không có thay đổi dành cho bản PDF cuối này**. Không cần Poppler, LibreOffice hoặc PDF.js cho luồng lưu/preview/export PDF gốc. `docker/entrypoint.sh` đã có `php artisan migrate --force` từ trước, không sửa cho PDF.

## Những thành phần Vlex cần kiểm tra trước khi ghép

1. Đối chiếu model Page/Attachment, namespace, khóa, quan hệ book/chapter/folder và controller thật. Không xem scaffold/alias local là mã nghiệp vụ Vlex đã xác thực.
2. Backup **database và storage attachment cùng nhau**. Cột `pdf_attachment_id`, record attachment và file vật lý phải được giữ tương ứng khi chuyển dữ liệu; không chỉ copy database hoặc HTML.
3. Chạy migration theo quy trình triển khai trước khi phục vụ mã mới. Cần quyền DDL phù hợp; rollback bỏ cột sẽ làm mất dấu loại trang/liên kết PDF trong Page, không phải quy trình tự khôi phục dữ liệu.
4. Ghép đầy đủ các chốt khóa nội dung và attachment ở web/API, không chỉ ghép menu/iframe. Kiểm tra người không có quyền xem không truy cập preview/export/attachment bằng ID hoặc slug đoán được.
5. Đối chiếu AttachmentService, disk local/private/S3/CDN và quyền đọc/ghi. Không đưa file PDF gốc vào đường dẫn public để bỏ qua xác thực. Giữ Content-Type/Disposition đúng và cấu hình cache phù hợp với tài liệu riêng.
6. Đồng bộ upload PHP, reverse proxy và ứng dụng: lab `upload_max_filesize=20M`, `post_max_size=24M`, request validate 20480 KB; disk attachment, memory/timeouts cần đủ. Giới hạn 20 MB không có nghĩa tài liệu nặng sẽ render mượt trên mọi máy khách.
7. Đối chiếu CSP, frame-ancestors, X-Frame-Options, domain/subdomain đăng nhập, cookie và proxy: trang phải nhúng được endpoint PDF cùng nguồn mà vẫn giữ quyền riêng tư. Kiểm tra cả response qua reverse proxy thật.
8. Ghép và kiểm thử trên staging trước; làm mới route/view cache và triển khai source/image theo quy trình Vlex. Hash trong manifest chỉ xác định bản file, không xác nhận tương thích hoặc thay thế kiểm thử.

## Các giới hạn nghiệp vụ cần giữ khi bàn giao

- PDF nhập theo bản trích chữ/HTML cũ **không có file gốc lưu kèm**; cần nhập lại file PDF nguồn. Không thể dựng lại bản gốc nguyên vẹn từ chữ đã trích. Không tự xóa/chuyển các trang cũ.
- Xuất nguyên file áp dụng **từng trang PDF**. Xuất sách/chương vẫn là PDF tổng hợp HTML và không ghép các PDF nguồn; lấy file nguyên vẹn từ trang tương ứng.
- Copy trang PDF qua PageRepo bị chặn; quy trình copy sách có sử dụng đường copy này có thể bị chặn khi gặp PDF. Nếu Vlex cần copy hàng loạt phải thiết kế và thử riêng việc sao chép file/record/liên kết.
- Kiểm tra upload chỉ ở mức cơ bản; file hỏng sâu có thể được lưu và viewer báo lỗi. Không tuyên bố mọi file `.pdf` đều hiển thị được.
- Giữ nguyên bytes bảo toàn dữ liệu file đã nhập, bao gồm dữ liệu chữ ký nếu có; chưa kiểm tra tính hợp lệ/chứng thư/trạng thái chữ ký bằng phần mềm chuyên dụng.

## Kiểm thử đã làm và cần làm tiếp

Đã đạt ở lab: service validation và chốt chỉ xem; HTTP import 5 loại fixture → preview/export trùng SHA-256; sửa/lưu nháp/thay/xóa riêng attachment và xuất kiểu khác bị chặn; người chưa đăng nhập bị chuyển về login; file giả/cụt trả 422; editor trang thường vẫn mở. Runtime và 104 gói production khớp Composer lock gốc. Đã áp dụng migration trong lab. Thay đổi fragment menu/toolbar cuối được xác nhận trong template chạy; chưa kiểm chứng hành vi trên mọi viewer.

Kiểm tra giao diện mới xác nhận iframe, nút mở/tải và không có nút sửa. Trình duyệt tích hợp của công cụ kiểm thử đã hiện khung PDF trống; **chưa xác minh render native thành công trên Chrome/Edge thực tế**. File [lưu ý trình duyệt](pdf-browser-compatibility-notes.md) liệt kê kiểm thử còn thiếu. Các kết quả lab không phải xác nhận tương thích Vlex thật.

Chạy lại từ gốc repository, Docker local đang hoạt động:

```powershell
docker compose up -d --build
docker compose exec -T app mkdir -p /tmp/pdf-import-test
docker compose cp ./tests/pdf-import/check-service.php app:/tmp/pdf-import-test/check-service.php
docker compose exec -T app php /tmp/pdf-import-test/check-service.php
docker compose cp app:/tmp/pdf-import-test/. ./tests/pdf-import/
./tests/pdf-import/check-http.ps1
```

Script hiện dùng tài khoản lab `admin@admin.com` và URL `http://localhost:8080`, không dùng nguyên cấu hình này cho production. Fixture là dữ liệu tổng hợp; không commit PDF thật của người dùng.
