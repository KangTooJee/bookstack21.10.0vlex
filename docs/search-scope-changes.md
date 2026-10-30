# Tìm kiếm phân bậc bắt buộc

Ngày: 28/10/2026. Dùng BookStack/PHP/Laravel/MySQL hiện có; không thêm dependency, Elasticsearch hay dịch vụ bên ngoài.

Hồ sơ tra soát để áp dụng sang VLEX thật: [bảng tác dụng từng file và kiểm tra tương thích](search-vlex-compatibility-handover.md), [danh sách 28 file kèm SHA-256](search-scope-manifest.json). Quyết định hiện tại không sử dụng Elasticsearch/OCR do lo ngại độ chính xác tiếng Việt; PDF chỉ tìm tiêu đề.

## Hành vi

Trên header: một thanh tìm kiếm bo tròn theo BookStack, căn giữa theo chiều cao header, với select ngắn Name/Word/Excel/PDF ở cuối, mặc định Name. Advanced Search giữ bố cục sidebar mặc định, ô Search Terms và dropdown Phạm vi tìm kiếm tách riêng, rộng tối đa 250px; dropdown dùng nhãn đầy đủ như “Tên và nội dung file Excel”. Mobile và ô tìm trong sách/chương dùng thanh kết hợp. Scope vẫn được gửi và kiểm tra ở máy chủ.

- `names`: tên shelf/book/chapter/page; không tìm nội dung, mô tả hoặc chỉ mục nội dung. Trong mô hình BookStack, folder tương ứng shelf/book/chapter và file tương ứng page, không phải mọi attachment.
- `word`: tên/nội dung page loại Word.
- `excel`: tên/nội dung page loại Excel.
- `pdf`: chỉ tiêu đề page loại PDF, không tìm text, mô tả, nội dung hoặc chỉ mục nội dung; exact cũng chỉ áp dụng tiêu đề và inbody trả rỗng. Dropdown Advanced Search và kết quả hiển thị rõ “PDF chỉ hỗ trợ tìm theo tiêu đề, không tìm nội dung bên trong file.” Theo quyết định hiện tại không triển khai Elasticsearch hoặc OCR.

Advanced Search vẫn hỗ trợ type/tag/date/permission. Type chỉ thu hẹp thêm phạm vi, không vượt scope. `{inbody:...}` với scope names trả rỗng thay vì quét nội dung. Giữ scope trên URL chuyển trang, không lưu vào chuỗi term. GET `/search` không tham số chỉ hiển thị form, không chạy truy vấn tìm toàn kho.

Cập nhật sau kiểm tra thực tế: giữ nguyên câu người dùng nhập trong header, Advanced Search và URL chuyển trang; không dựng lại bằng toString(). Tìm thông thường tách dấu câu/ngoặc và yêu cầu đủ các từ theo chỉ mục (không yêu cầu thứ tự, vẫn hỗ trợ tiền tố). Đặt toàn câu trong dấu ngoặc kép để tìm cụm liên tiếp trong tên/text; ngoặc tròn giữ nguyên trong cụm chính xác. Exact phrase vẫn dùng LIKE trên text sau khi giới hạn loại tài liệu; chưa thay thế bằng positional index. Bộ tìm nội bộ dùng fromString() cũ giữ tương thích; giao diện người dùng và API dùng fromUserString().

Chuẩn hóa text có khoảng cách giữa ô/hàng/khối HTML, bỏ script/style/head. Chỉ mục HTML cũng tách ranh giới phần tử trước khi tokenize để tránh dính chữ giữa ô. Khi câu đầy đủ có trong text, ưu tiên đoạn trích quanh câu đó. indexEntity thay chỉ mục trong transaction và insert theo lô 500.

API `/api/search` yêu cầu thêm `scope=names|word|excel|pdf`. Client cũ thiếu scope nhận lỗi validation. Endpoint `/search/book/{id}` và `/search/chapter/{id}` cũng bắt buộc scope. AJAX chọn thực thể trong trình biên tập giữ hợp đồng nội bộ cũ để không làm hỏng liên kết/chọn đích; không dùng endpoint đó cho giao diện tìm kiếm toàn kho.

`names` dùng LIKE trên tên (escape wildcard cho từ thông thường), không đọc text page. Đây vẫn là tìm chuỗi con, có thể quét cột tên khi kho lớn. Truy vấn nội dung giới hạn document_type trước khi tổng hợp search_terms và bỏ truy vấn tần suất từ toàn kho. COUNT và OFFSET hiện vẫn giữ để tương thích giao diện; không tuyên bố đã loại bỏ timeout trên dữ liệu khách hàng.

## Phân loại dữ liệu

Migration `2026_10_28_090000_add_document_type_to_pages.php` thêm document_type có index. PDF cũ nhận qua pdf_attachment_id; Excel cũ qua marker bookstack-excel-content. Các trang HTML cũ còn lại mặc định Word vì hệ thống trước đó không lưu nguồn nhập. Đây là quy ước tương thích, không phải khôi phục chính xác nguồn file. Import mới ghi loại rõ ràng; tạo page HTML thủ công mặc định Word.

Sửa phân loại Excel cũ thiếu marker: chạy `php artisan vlex:repair-document-search`. Lệnh dùng cùng bộ nhận diện Calc đã có trong ExcelPageLayout (table border/cellspacing và colgroup width), xử lý một page mỗi lượt, giao dịch riêng từng page, không truncate chỉ mục. Chuẩn hóa pages.text và lập lại chỉ mục các page chưa xóa; giữ PDF text hiện có. Chạy trong cửa sổ bảo trì để tránh ghi đè text khi người dùng đang sửa; có thể tiếp tục bằng `--after=ID`. Heuristic không thay thế metadata nguồn cho mọi tài liệu bất kỳ. Trên local đã sửa page 27 từ Word thành Excel và xử lý 8 page chưa xóa.

Backfill duyệt ID theo lô 500, không tải HTML vào PHP. Đây là migration có đọc HTML lịch sử ở DB, cần chạy trong cửa sổ bảo trì trên dữ liệu khách hàng lớn và đo trước chi phí ALTER/index. Chưa đo ở dữ liệu khách hàng.

## Triển khai và kiểm tra

Build image theo quy trình hiện có; chạy `php artisan migrate --force`, `php artisan vlex:repair-document-search`, `php artisan view:clear`. Bản local đã copy file vào container, chạy migration và repair. Khi tái tạo container cần build image từ source mới. Không thay composer.lock/package.json. CSS của thanh tìm kiếm nằm riêng ở public/dist/search-field.css và dùng hash cache trong base layout.

Đã sửa nguồn entity-search.js và đồng bộ đúng bốn thay đổi tương ứng trong bundle app.js đã có, không cài npm. Sourcemap cũ không được dựng lại; khi build frontend chuẩn sẽ sinh bundle/map mới từ nguồn đã sửa.

Kiểm tra service: copy `tests/search-scope/check-service.php` vào container, chạy `php /tmp/check-search-scope.php`. Fixture tạo trong transaction và rollback: tìm tên không lọt body/description, tách Word/Excel/PDF, exact/inbody/type filter, tìm trong sách, validation scope.

Kiểm tra HTTP: `tests/search-scope/check-http.ps1` dùng tài khoản lab mặc định, kiểm tra dropdown mặc định Name, missing/invalid scope, advanced form, trang kế tiếp giữ scope, endpoint sách, anonymous guard. Phần regression câu tiếng Việt kiểm tra trực tiếp tài liệu local Vlex_Yeu cau phat trien vT10 2026: câu thường và câu có dấu ngoặc kép đều trả đúng trang, giữ nguyên input. Không dùng tài khoản này trên production. Service test độc lập có fixture kiểm tra ngoặc, yêu cầu đủ từ, cụm chính xác và ranh giới ô.

Elasticsearch self-managed có thể triển khai trong LAN/air-gap; chỉ ứng dụng truy cập API nội bộ, bật xác thực/TLS và firewall. OCR cũng cần worker nội bộ. Hạng mục này chưa cài Elasticsearch hoặc OCR.
