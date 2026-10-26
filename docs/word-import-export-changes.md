# Bảng kê thay đổi import và export Word để đối chiếu Vlex

Ngày ghi nhận: 26/10/2026. Nhánh local: `files-curl`.

Tài liệu này ghi lại mã nguồn và các điểm tích hợp của tính năng nhập Word → chỉnh sửa HTML trên web → xuất DOCX. Mục đích là giúp đối chiếu và ghép thay đổi vào Vlex thật để kiểm tra tương thích. Đây là kết quả trên nền BookStack local; chưa xác nhận tương thích với mã tùy biến, phân quyền, kho ảnh và giao diện của Vlex thật.

## Mốc nguồn và cách đọc đường dẫn

- Repository local: `C:/Users/Vissoft_PC/Downloads/VLEX/bookstack-lab`.
- Gốc ứng dụng local: `source/BookStack-21.11.1/`.
- Các đường dẫn trong bảng mã ứng dụng bên dưới tính từ gốc ứng dụng, không phải từ gốc repository.
- HEAD nền tại thời điểm ghi nhận: `5e9b4137108b1371542e1219c490a9010c2484f1`.
- Các sửa lỗi/import cải tiến và tính năng export của phiên làm việc này **chưa commit** ở thời điểm lập bảng kê. HEAD nêu trên không phải commit chứa toàn bộ tính năng mới.
- File `word-import-export-manifest.json` bên cạnh ghi trạng thái Git và SHA256 của từng file trong phạm vi tính năng tại thời điểm chụp. Dùng hash để đối chiếu đúng bản file; các chỉnh sửa sau này cần cập nhật manifest.

## File mã ứng dụng mới hoặc sửa trong phiên này

| File | Trạng thái khi lập bảng kê | Tác dụng và phần cần ghép vào Vlex |
| --- | --- | --- |
| `app/Uploads/WordImportService.php` | Sửa file có sẵn | Chuyển DOC/DOCX thành HTML qua LibreOffice; kiểm tra định dạng/kích thước, lọc HTML và nhúng ảnh. Bản sửa lọc ảnh ShapeN/hộp chữ FrameN sinh từ lớp vẽ; chuẩn bị bản sao DOCX để bỏ OLE/ảnh biểu tượng nhúng và giữ độ thụt lề danh sách; đọc cả thuộc tính thụt lề `left` và `start`. Không sửa file Word gốc. |
| `app/Uploads/WordExportService.php` | File mới | Điều phối xuất nội dung đã lưu của Page qua PageContent, chuẩn bị HTML/ảnh, gọi LibreOffice, đóng gói ảnh vào DOCX và sửa quan hệ ảnh từ link sang embed. Phối hợp hai lớp Layout/Appearance, giữ bookmark/mục lục và thụt lề danh sách, trả nội dung DOCX; dọn thư mục tạm cả khi lỗi. |
| `app/Uploads/WordExportLayout.php` | File mới | Giữ khối trong vùng in A4: quy đổi width phần trăm theo khối cha, xác định lưới cột có colspan/rowspan, thu ảnh theo lòng ô và chiều cao trang, giữ tỷ lệ ảnh, neo bảng lồng đúng ô. Sau chuyển đổi, giới hạn lại lưới bảng/ô/ảnh trực tiếp trong DOCX; gỡ chiều cao hàng cố định/nowrap gây cắt nội dung và tạo điểm ngắt cho chuỗi dài trong bản xuất. |
| `app/Uploads/WordExportAppearance.php` | File mới | Tính font theo kế thừa HTML, CSS inline và thẻ font cũ; chuyển cỡ px/pt/em/rem/%/keyword sang point tường minh. Mặc định theo CSS web local trên Windows, không ép Arial 11pt. Lưu định dạng viền từng bảng/ô rồi ghi lại trực tiếp vào DOCX; giữ cạnh, kiểu nét, độ dày, màu, ô không viền và khung ngoài bảng. |
| `app/Http/Controllers/PageExportController.php` | Sửa file có sẵn | Thêm action `word()` lấy trang bằng PageRepo, gọi WordExportService, thông báo lỗi/chuyển về trang khi không xuất được, trả attachment `.docx` với MIME chuẩn. Dùng middleware quyền `content-export` sẵn có của controller. Các action export gốc vẫn tồn tại. |
| `routes/web.php` | Sửa file có sẵn | Trong phiên này thêm GET `/books/{bookSlug}/page/{pageSlug}/export/word`, trỏ tới action `word()` và throttle 10 lần/phút. File cũng đã có bốn route import Word từ trước; cần giữ hoặc đối chiếu với route nhập hiện có ở Vlex. Ghép các dòng liên quan, không chép đè toàn bộ routing Vlex. |
| `resources/views/entities/export-menu.blade.php` | Sửa file có sẵn | Thêm mục xuất Word `.docx` khi entity là Page. Chưa thêm xuất toàn bộ sách/chương. Menu xuất tiếp tục được hiển thị theo quyền đang có của trang. |
| `resources/lang/en/entities.php` | Sửa file có sẵn | Thêm khóa `export_word` với nhãn tiếng Anh. |
| `resources/lang/vi/entities.php` | Sửa file có sẵn | Thêm khóa `export_word` với nhãn “Tài liệu Word”. |

Ba lớp export mới đi cùng nhau. Chỉ chép WordExportService mà thiếu WordExportLayout hoặc WordExportAppearance sẽ không đủ để chạy bản hiện tại.

## File import đã tồn tại trước phiên này nhưng vẫn thuộc tính năng

Nhóm dưới đây không có thay đổi Git mới trong phiên này. Nếu Vlex đích chưa có luồng import giống local, vẫn phải ghép các phần tương ứng.

| File | Tác dụng |
| --- | --- |
| `app/Http/Controllers/WordImportController.php` | Hiển thị form, kiểm tra đăng nhập/quyền tạo trang, nhận file tối đa 20 MB, gọi importer, kiểm tra quyền ảnh và tạo trang nháp trong transaction. |
| `resources/views/pages/import-word.blade.php` | Form tên trang và upload DOC/DOCX, CSRF, thông báo lỗi và mở bản nháp sau nhập. |
| `resources/views/pages/parts/word-import-action.blade.php` | Nút “Nhập Word (.doc/.docx)”, kiểm tra đăng nhập và quyền tạo trang. |
| `resources/views/books/show.blade.php` | Chèn nút import trong trang sách. |
| `resources/views/chapters/show.blade.php` | Chèn nút import trong trang chương. |
| `resources/views/pages/show.blade.php` | Chèn nút import theo parent của trang; đồng thời là nơi hiển thị menu export theo quyền. |
| `routes/web.php` | GET/POST import theo sách và theo chương; POST có throttle 10 lần/phút. Đây là cùng file routing đã liệt kê ở nhóm sửa mới. |

## Phụ thuộc đang dùng nhưng không sửa cho tính năng này

| File hoặc thành phần | Điểm cần kiểm tra trên Vlex thật |
| --- | --- |
| `app/Entities/Models/Page.php` | Model Page, tên trang, HTML đã lưu và quan hệ sách/chương; xác nhận Vlex có dùng cùng model/namespace hay thay bằng thực thể khác. |
| `app/Entities/Repos/PageRepo.php` | `getBySlug()`, `getParentFromSlugs()`, `getNewDraftPage()` và scope xem trang. Không thay bằng truy vấn bỏ kiểm tra quyền. |
| `app/Entities/Tools/PageContent.php` | Render HTML, xử lý nội dung include/bookmark, chuyển ảnh base64 thành ảnh lưu trong kho khi import/lưu trang. Cần đối chiếu tùy biến Vlex và bộ lọc HTML của editor. |
| `app/Uploads/ImageService.php` | `imageUriToBase64()` lấy ảnh đã lưu từ disk cấu hình. Cần kiểm tra URL nội bộ, thumbnail, secure storage, S3/CDN của Vlex. |
| `app/Http/Controllers/Controller.php` | Các helper quyền, `downloadResponse()`, thông báo lỗi và MIME response. |
| `app/Entities/Tools/ExportFormatter.php` | Export HTML/PDF/Markdown có sẵn; không sửa lớp này để thêm Word. |
| `resources/sass/_variables.scss`, `_html.scss`, `_text.scss`, `_tables.scss`, `_pages.scss`, `_tinymce.scss` | Không sửa CSS. Font mặc định, tiêu đề và viền mặc định trong exporter được đối chiếu với CSS local; nếu theme Vlex khác, cần điều chỉnh WordExportAppearance và HTML đầu ra cho cùng định dạng. |
| `resources/js/components/wysiwyg-editor.js` | Không sửa editor. Định dạng lấy từ HTML đã lưu; cần kiểm tra editor Vlex có loại/đổi style trước khi lưu hay không. |
| Symfony Process, PHP DOM/ZipArchive/mbstring/fileinfo, ImageService | Dùng thư viện/extension đang có; không thêm PHPWord và không đổi Composer lock trong phiên này. |

Cây Vlex local hiện có cả scaffold/alias chờ mã thật. Xem `vlex-local-development.md` và `vlex-file-map.md` để phân biệt; có cùng tên file không đồng nghĩa nội dung đã là mã Vlex thật.

## File kiểm thử và dữ liệu mẫu

Các đường dẫn phần này tính từ **gốc repository**.

| File | Trạng thái | Tác dụng |
| --- | --- | --- |
| `tests/word-import/check-service.php` | Sửa | Chuyển DOC/DOCX, lọc HTML, loại shape/frame thừa, giữ ảnh thật và bookmark; từ HTML mẫu sinh fixture Word dùng cho các test sau. Gọi thêm check-docx-layout.php. |
| `tests/word-import/check-docx-layout.php` | Mới | Tự tạo DOCX tổng hợp kiểm tra numbering, thụt lề nhiều cấp, ghi đè trực tiếp, OLE, GIF thật và bookmark; xác nhận không đổi file gốc. |
| `tests/word-import/check-export.php` | Mới | Nhập Word, bổ sung nội dung HTML như khi chỉnh sửa web rồi xuất DOCX; kiểm tra chữ, bảng/ô gộp, ảnh nhúng, r:embed, mục lục, import lại để so thụt lề, dọn tạm và từ chối ảnh không hỗ trợ. |
| `tests/word-import/check-export-layout.php` | Mới | Tạo ảnh lớn/ảnh cao và bảng kiểm thử: 100%, nhiều cột, tỷ lệ cột, bảng lồng, colspan/rowspan, chiều cao cố định, chữ/code dài. Kiểm tra không vượt khối chứa và không thay HTML đã lưu. |
| `tests/word-import/check-export-appearance.php` | Mới | Kiểm tra tên/cỡ font trong run XML của DOCX, font kế thừa/ghi đè, đơn vị CSS/thẻ font cũ; kiểm tra viền mặc định, viền màu/kiểu/độ dày theo cạnh, ô không viền và khung ngoài. |
| `tests/word-import/check-http.ps1` | Sửa | Luồng web import → nháp → thêm HTML → lưu → tải DOCX, kiểm tra ảnh/định dạng download/menu/quyền người chưa đăng nhập. Có tham số LayoutFixture để kiểm tra lưu thụt lề; tạo sách thử rồi đưa vào thùng rác. |
| `tests/word-import/fixture.html`, `fixture.png`, `fixture.doc`, `fixture.docx` | Đã có, không sửa trong phiên | Dữ liệu tổng hợp cho test tiếng Việt, mục lục, danh sách, bảng/ô gộp và ảnh. Không phải tài liệu dự án thật của người dùng. |

Các script PHP hiện bootstrap `/var/www/html` và dùng `/tmp/word-import-test`; script HTTP dùng URL/tài khoản của lab. Phải sửa cấu hình test cho staging Vlex, không mặc định coi các đường dẫn/tài khoản lab dùng được ở hệ thống đích.

## File môi trường và tài liệu

| File từ gốc repository | Trạng thái trong phiên | Tác dụng |
| --- | --- | --- |
| `Dockerfile` | Không sửa | Runtime PHP 8.0.30, cài LibreOffice Writer, fonts-dejavu-core và extension cần thiết; đặt giới hạn upload/memory/thời gian xử lý. Cung cấp môi trường lab, không phải file bắt buộc chép đè Dockerfile của Vlex. |
| `compose.yaml` | Không sửa | App, MySQL, port localhost và volumes storage/uploads của lab. Không dùng để thay deployment thật mà chưa đối chiếu. |
| `README.md` | Sửa | Cách dùng, giới hạn và kiểm thử import/export; có liên kết tới bảng kê này. |
| `docs/word-import-export-changes.md` | Mới | Bảng kê và hướng dẫn đối chiếu này. |
| `docs/word-import-export-manifest.json` | Mới | Snapshot danh sách file/trạng thái/SHA256 để truy lại đúng phiên bản. Không tự bao gồm hash của chính manifest. |

## Yêu cầu môi trường cần đối chiếu

- Nền lab: BookStack 21.11.1, PHP 8.0.30, Laravel 8.70.2; LibreOffice quan sát trong lab là 7.0.4.2 trên Linux. Chưa xác minh phiên bản runtime Vlex thật.
- Binary `libreoffice` phải chạy được bởi user web; Symfony Process cần được phép gọi process. Mỗi lần chuyển đổi dùng profile riêng, macro bị vô hiệu và cập nhật link bị hạn chế.
- Mã hiện dùng `/tmp`, profile URL `file://` và cách kiểm tra realpath theo dấu `/`. Nếu máy chủ Vlex dùng Windows, cần sửa và kiểm thử phần đường dẫn/URI trước khi áp dụng.
- PHP cần DOM/libxml, mbstring, zip, fileinfo; GD được dùng cho ảnh và bộ test. User web cần quyền tạo/xóa thư mục tạm và đọc kho ảnh theo cấu hình.
- Lab đang đặt memory_limit 256 MB, upload_max_filesize 20 MB, post_max_size 24 MB, max_execution_time 120 giây. Timeout LibreOffice là 60 giây mỗi lượt. Cần đồng bộ cả reverse proxy/request timeout nếu Vlex có lớp proxy.
- Giới hạn mã: upload 20 MB; DOCX tối đa 5000 entries/100 MB sau giải nén; HTML đầu vào/đầu ra theo bước chuyển đổi tối đa 20 MB; tổng ảnh tối đa 30 MB; DOCX export tối đa 30 MB. Không tăng một giới hạn riêng lẻ khi chưa kiểm tra memory/timeouts.
- Font chỉ được ghi tên trong DOCX, không được nhúng file font. Máy mở Word cần có font tương ứng. Word lưu cỡ chữ theo nửa point; có làm tròn khi quy đổi CSS.
- Không có migration, thay đổi schema, thay đổi composer.json/composer.lock hoặc build frontend mới dành riêng cho các sửa import/export này.

## Trình tự ghép và kiểm tra trên Vlex

1. Đối chiếu mã thật và dependency/lockfile/runtime trước. Xác định model trang, quan hệ folder/sách/chương, controller, route, namespace và quy tắc phân quyền thực tế. Không lấy scaffold local làm mã nghiệp vụ Vlex.
2. Ghép service mới và sửa importer tương ứng. Ghép riêng action controller, route, menu và khóa dịch vào mã Vlex; giữ các tùy biến hiện có của từng file.
3. Đối chiếu kho ảnh và URL. Xác nhận ảnh đã import và ảnh thêm mới trong editor đều xuất được, gồm ảnh trong bảng/ô gộp; ảnh ngoài không đọc được phải có thông báo thay vì xuất thiếu ảnh.
4. Đối chiếu CSS/theme Vlex với mặc định font/tiêu đề/viền của exporter. Kiểm tra HTML thật sau khi lưu, không chỉ HTML đang nằm trong editor.
5. Kiểm tra quyền xem trang, `page-create`, quyền ảnh và `content-export` với người dùng/role thật. Scope không xem được trang không được phép xuất trang đó.
6. Chạy bộ test trên staging với dữ liệu tổng hợp, sau đó thử bản sao tài liệu đại diện đã được phép dùng. Kiểm tra nội dung mới sửa sau import có trong DOCX; HTML chưa lưu không nằm trong export.
7. Mở DOCX bằng Microsoft Word ở môi trường người dùng thật, so với HTML: khung bảng, từng cột/phần bên phải, ảnh trong ô, bảng lồng/ô gộp, phần sau bảng, font/cỡ chữ, danh sách/mục lục và ngắt trang. Bản render LibreOffice local chưa thay thế cho bước này.
8. Sau khi ghép mã hoặc đổi môi trường, làm mới route/view cache theo quy trình của Vlex và xác nhận file chạy trùng bản đã đối chiếu. Local từng copy file vào container để thử ngay; lần triển khai bền vững cần đưa mã vào source/image build.

Các chức năng hiện biết khác Word gốc: ShapeN/FrameN nổi và OLE bị loại khi nhập; các đối tượng đó không thể khôi phục từ HTML khi xuất. Xuất Word chỉ áp dụng từng trang đã lưu, chưa xuất nguyên sách/chương. Bảng dài có thể qua nhiều trang; ảnh quá lớn được thu vừa khối/trang. Chưa có tính năng import Excel trong phạm vi bảng kê này.

## Cách chạy lại kiểm thử trong lab

Chạy PowerShell tại gốc repository, với container local đang hoạt động:

```powershell
docker compose exec -T app mkdir -p /tmp/word-import-test
docker compose cp ./tests/word-import/. app:/tmp/word-import-test/
docker compose exec -T app php /tmp/word-import-test/check-service.php
docker compose exec -T app php /tmp/word-import-test/check-export.php
docker compose exec -T app php /tmp/word-import-test/check-export-layout.php
docker compose exec -T app php /tmp/word-import-test/check-export-appearance.php
./tests/word-import/check-http.ps1
```

Tùy chọn kiểm tra thụt lề qua HTTP: copy `layout.docx` được check-docx-layout.php tạo trong container ra file tạm, rồi truyền `-LayoutFixture <đường-dẫn-file-tạm>` cho check-http.ps1. Không dùng file dự án chứa dữ liệu nhạy cảm làm fixture commit.

## Trạng thái xác minh khi lập bảng kê

Đã đạt trong lab: test chuyển DOC/DOCX, lọc nội dung thừa/OLE, thụt lề, export nội dung sửa, ảnh nhúng, bảng/ô gộp/bảng lồng, giới hạn khối, font/viền trong XML và luồng tải qua HTTP. Đã render DOCX bằng LibreOffice và xem các trang thử cho bố cục/font/viền; nội dung cuối còn đầy đủ. Chưa kiểm chứng trên ứng dụng Vlex thật hoặc trực tiếp bằng Microsoft Word của người dùng.
