# Hồ sơ đối chiếu tìm kiếm trước khi áp dụng vào VLEX thật

Ngày chốt: 28/10/2026. Phạm vi: các thay đổi tìm kiếm Name/Word/Excel/PDF trong cuộc trao đổi này.

Đọc cùng [bảng file và SHA-256](search-scope-manifest.json), [chi tiết hành vi](search-scope-changes.md) và [hồ sơ PDF](pdf-import-export-changes.md). Đường dẫn dưới đây tính từ `source/BookStack-21.11.1/`, trừ các file test/docs ở thư mục lab.

## Quyết định không sử dụng Elasticsearch và OCR

**Ràng buộc triển khai hiện tại: không sử dụng Elasticsearch và không sử dụng OCR.** Người dùng đã quyết định bỏ hai phương án vì lo ngại độ chính xác khi xử lý tiếng Việt; hệ thống phải hoạt động trong mạng nội bộ, không phụ thuộc dịch vụ bên ngoài. Chưa cài hoặc tích hợp hai công cụ này trong bản lab.

Đây là quyết định về phạm vi và mức rủi ro chấp nhận của dự án, không phải kết luận Elasticsearch không hỗ trợ tiếng Việt hoặc không chạy được trong LAN. Chưa thực hiện benchmark Elasticsearch với analyzer tiếng Việt hay đánh giá chất lượng OCR trên tập PDF khách hàng.

Hệ quả đã triển khai:

- MySQL và `search_terms` của BookStack tiếp tục phục vụ tìm kiếm.
- Word/Excel tìm trong tiêu đề và nội dung đã lưu. Text thuần không chứa HTML được lưu ở `pages.text`; chỉ mục từ khóa hiện vẫn sinh từ HTML đã tách ranh giới phần tử, không chuyển toàn bộ sang một search engine mới.
- PDF chỉ tìm theo **tiêu đề page (`pages.name`)**, không phải tên attachment và không tìm text/mô tả/nội dung file. Cả từ khóa thường, cụm chính xác và Advanced Search đều tuân thủ; `inbody` với phạm vi PDF trả rỗng.
- Cảnh báo cho người dùng: **“PDF chỉ hỗ trợ tìm theo tiêu đề, không tìm nội dung bên trong file.”** Dropdown Advanced Search ghi “PDF — chỉ tìm tiêu đề”.
- Không có gói Elasticsearch/OCR hoặc dependency mới nào cần cài cho phần tìm kiếm này.

## File backend và tác dụng

| File | Thay đổi/tác dụng | Điểm cần đối chiếu tại VLEX thật |
| --- | --- | --- |
| `app/Entities/Tools/SearchScope.php` | Mới: định nghĩa bốn phạm vi, nhãn và validation. PDF ghi rõ chỉ tiêu đề. | Tên namespace, cơ chế validation; giá trị gửi là `names`, không phải `name`. |
| `app/Entities/Tools/SearchText.php` | Mới: trích text thuần, bỏ script/style/head, thêm khoảng cách giữa ô/hàng/khối, chuẩn hóa khoảng trắng. | HTML thực của khách hàng, Unicode/NBSP và nội dung bảng; không làm mất mã nghiệp vụ. |
| `app/Entities/Tools/SearchOptions.php` | Thêm `documentScope`, `rawQuery`, `matchAllTerms`, `fromUserString()`. Tách dấu câu/ngoặc cho tìm thường; giữ nguyên input và giữ `fromString()` cũ cho luồng nội bộ. | VLEX có mở rộng cú pháp tìm kiếm không; kiểm tra ngoặc, dấu kép, tag/filter và client API hiện hữu. |
| `app/Entities/Tools/SearchRunner.php` | Giới hạn loại tài liệu; Name/PDF chỉ query tên; Word/Excel tra chỉ mục và yêu cầu đủ các từ trong luồng mới. Chặn `inbody` khi chỉ tìm tiêu đề. Giữ permission enforcement. | Schema/morph type, quyền sở hữu/quyền kế thừa, MySQL collation, SQL mode; đo truy vấn thực tế. |
| `app/Entities/Tools/SearchIndex.php` | Tách ranh giới ô/khối trước tokenize; thay chỉ mục từng entity trong transaction, insert lô 500. | Cấu trúc `search_terms`, giới hạn độ dài token/packet, sự kiện lưu trang và job có sẵn. |
| `app/Entities/Tools/SearchResultsFormatter.php` | Ưu tiên đoạn trích quanh câu gốc; không lấy đoạn trích nội dung ở Name/PDF. | Cơ chế preview, escaping và Unicode trong formatter của VLEX. |
| `app/Entities/Tools/PageContent.php` | Dùng SearchText để tạo `pages.text`, tránh dính chữ giữa các ô. | File này có thay đổi PDF trước đó; chỉ merge phần liên quan, giữ logic riêng của khách hàng. |
| `app/Console/Commands/RepairDocumentSearch.php` | Mới: lệnh `vlex:repair-document-search`, nhận diện Excel cũ qua ExcelPageLayout, chuẩn hóa text và reindex từng page chưa xóa. Hỗ trợ `--after=ID`. | Cần có `ExcelPageLayout::matches()`, `pdf_attachment_id`, `document_type`, quan hệ tags và `indexForSearch()`. |
| `app/Http/Controllers/SearchController.php` | Validate scope cho tìm toàn kho/sách/chương; giữ nguyên raw query; giữ tham số Advanced Search/scope khi phân trang; mở form trống không tìm toàn kho. | Route middleware, GET validation/redirect, URL prefix, và endpoint AJAX nội bộ. |
| `app/Http/Controllers/Api/SearchApiController.php` | API bắt buộc scope và dùng parser mới. | **Thay đổi hợp đồng API**: client cũ thiếu scope nhận lỗi; cần sửa hoặc có kế hoạch chuyển đổi client. |
| `app/Http/Controllers/WordImportController.php` | Ghi `document_type=word` khi tạo draft nhập Word. | Nếu VLEX dùng PHPWord/Modules hoặc controller khác, gắn logic vào đúng luồng nhập thực tế. |
| `app/Http/Controllers/ExcelImportController.php` | Ghi `document_type=excel` khi tạo draft nhập Excel. | Giữ loại qua draft/publish/edit/duplicate/restore của khách hàng. |
| `app/Http/Controllers/PdfImportController.php` | Ghi `document_type=pdf` cho page nhập PDF. | File mới thuộc cả chức năng PDF trước đó; không copy nếu VLEX đã có hệ thống PDF khác, cần merge mapping loại. |
| `database/migrations/2026_10_28_090000_add_document_type_to_pages.php` | Mới: thêm cột string(16), mặc định Word, index; backfill theo ID lô 500. PDF qua attachment ID, Excel qua marker. | Phụ thuộc cột `pdf_attachment_id`; kiểm tra trùng cột/index/migration và table prefix. Chi phí ALTER/backfill trên dữ liệu lớn. |

## File giao diện và tài liệu API

| File | Thay đổi/tác dụng | Điểm cần đối chiếu tại VLEX thật |
| --- | --- | --- |
| `resources/views/common/header.blade.php` | Thanh tìm kiếm chung, select gọn ở cuối; class hỗ trợ căn giữa chiều cao. | Header, logo, menu và breakpoint riêng của VLEX. |
| `resources/views/search/parts/field.blade.php` | Mới: thành phần ô tìm kiếm kết hợp, dùng cho header/mobile/sách/chương. | Tên biến Blade, ID và refs mà JS đang dùng. |
| `resources/views/search/parts/scope-select.blade.php` | Mới: select required, mặc định Name; nhãn ngắn hoặc đầy đủ; tooltip PDF chỉ tiêu đề. | Tránh trùng tên `scope` với bộ lọc sẵn có; kiểm tra ngôn ngữ và accessibility. |
| `resources/views/search/all.blade.php` | Advanced Search tách Search Terms và phạm vi; dùng input nguyên văn; sidebar theo grid mặc định; cảnh báo PDF và giữ phạm vi khi chuyển trang. | Các filter/partial đã được khách hàng tùy biến, cách hiển thị lỗi và quyền. |
| `resources/views/entities/search-form.blade.php` | Dùng thành phần kết hợp cho tìm trong sách/chương. | Tham chiếu `entity-search@searchInput` và `entity-search@scopeInput`. |
| `resources/views/layouts/base.blade.php` | Nạp CSS mới kèm hash để tránh cache cũ. | CSS phải tồn tại trước khi render vì có `hash_file`; URL/CDN/CSP và hệ thống version asset của VLEX. |
| `public/dist/search-field.css` | Mới: bo tròn header, căn giữa, select cuối, dark mode; Advanced Search input/select rộng tối đa 250px. | Header grid, breakpoint, CSS specificity, mobile, dark mode và trình duyệt khách hàng. |
| `resources/js/components/entity-search.js` | Gửi scope khi AJAX tìm trong sách/chương, kiểm tra form và tìm lại khi đổi scope. | HTTP helper/component registry/ref của VLEX; kiểm tra kết quả AJAX khi đổi nhanh bộ lọc. |
| `public/dist/app.js` | Bundle đang phục vụ trình duyệt, đã đồng bộ thay đổi entity-search từ nguồn. | Lab đồng bộ có kiểm soát vào bundle có sẵn; khi đưa sang VLEX nên build qua pipeline frontend thật. Sourcemap cũ chưa rebuild. |
| `dev/api/requests/search-all.http` | Ví dụ gọi API có `scope=word`. | Cập nhật tài liệu/client tích hợp tương ứng. |

## Test và hồ sơ lưu kèm

| File ở thư mục lab | Tác dụng |
| --- | --- |
| `tests/search-scope/check-service.php` | Fixture trong transaction, rollback sau test: scope, quyền, đủ từ, ngoặc tiếng Việt, exact phrase, ranh giới ô, PDF không tìm body. |
| `tests/search-scope/check-http.ps1` | HTTP smoke test: dropdown/default Name, scope validation, pagination, Advanced Search, cảnh báo PDF, anonymous guard và câu tìm Excel đã báo lỗi. |
| `docs/search-scope-changes.md` | Chi tiết hành vi, giới hạn và ghi nhận triển khai local. |
| `docs/search-scope-manifest.json` | Danh sách file, mục đích, trạng thái so với Git HEAD và SHA-256 tại lúc chốt để kiểm tra bản sao. |
| `docs/search-vlex-compatibility-handover.md` | Hồ sơ bàn giao/đối chiếu này. |

Script HTTP có tài khoản lab mặc định và một regression gắn với tài liệu local; cần đổi fixture/tài khoản trước khi dùng ở môi trường khách hàng. Không sử dụng credential lab trên production.

## Phụ thuộc và khác biệt chưa được xác minh

Nền lab: BookStack 21.11.1, PHP 8.0.30, Laravel 8.70.2, MySQL 8.0.36. Không nâng cấp Composer/package lock cho phần tìm kiếm. Chưa có đủ source/schema/composer.lock thực của khách hàng để xác nhận tương thích VLEX thật.

Các file phụ thuộc cần đọc khi đối chiếu, nhưng không được ghi là “sửa mới bởi phần tìm kiếm”:

- `app/Uploads/ExcelPageLayout.php`: nhận diện HTML Excel cũ bằng metadata bảng Calc; đây là heuristic, có thể nhận nhầm hoặc bỏ sót tài liệu khác. Ưu tiên metadata nguồn nhập thực của VLEX.
- `app/Entities/Models/Page.php`, `app/Entities/Models/Entity.php`, `app/Entities/Repos/PageRepo.php`: trường dữ liệu, thuộc tính list, luồng save/publish và gọi lập chỉ mục.
- `database/migrations/2026_10_27_180000_add_pdf_attachment_to_pages.php`: prerequisite của migration phân loại nếu VLEX chưa có `pdf_attachment_id`.
- `routes/web.php`: các route tìm kiếm đang có; phần tìm kiếm không tạo route mới. PDF trước đó có route riêng.
- `database/migrations/2017_03_19_091553_create_search_index_table.php`: cấu trúc search_terms và index gốc.
- [Manifest Word](word-import-export-manifest.json), [manifest Excel](excel-import-export-manifest.json), [manifest PDF](pdf-import-export-manifest.json): phạm vi nhập/xuất trước đó có thể chồng lấn file controller/content. Không coi toàn bộ working tree hiện tại là diff riêng của tìm kiếm.

## Trình tự áp dụng để kiểm tra tương thích

1. Đối chiếu trên staging với source/schema thật; backup DB và giữ bản source có thể quay lại. Merge từng thay đổi vào file tương ứng, không ghi đè nguyên file tùy biến.
2. Kiểm tra mapping folder/file và document_type. HTML lịch sử chưa có nguồn nhập mặc định Word; không bảo đảm phân loại đúng tất cả tài liệu cũ.
3. Xác minh migration PDF prerequisite, sau đó migration document_type. Đo trước thời gian ALTER/index/backfill trên bản sao dữ liệu lớn.
4. Build/deploy CSS/JS và Blade cùng nhau; CSS mới phải tồn tại trước khi base layout dùng hash_file. Đối chiếu consumer API vì scope trở thành bắt buộc.
5. Trong cửa sổ bảo trì dừng chỉnh sửa nội dung, chạy `php artisan vlex:repair-document-search`; tiếp tục từ ID sau cùng nếu cần bằng `--after=ID`. Lệnh không truncate kho chỉ mục; mỗi page có transaction riêng. Không dùng đồng thời với biên tập vì có thể ghi text tính từ snapshot cũ.
6. Chạy `php artisan view:clear`, kiểm tra đăng nhập/quyền, cả bốn scope, import/edit/publish và câu tiếng Việt có ngoặc/dấu kép. Trường hợp dùng body trong PDF phải trả rỗng, title vẫn tìm được.
7. Đo P95/P99 và tài nguyên trên dữ liệu đại diện, kể cả từ phổ biến, câu dài và phân trang sâu. Hiện vẫn có COUNT/OFFSET, LIKE chuỗi con ở tên/cụm chính xác; chưa có bằng chứng hết timeout ở kho khách hàng lớn.

Nếu rollback schema phải rollback code/UI/API đồng bộ trước hoặc trong cùng cửa sổ bảo trì; code mới phụ thuộc document_type và SearchText. Dữ liệu text đã chuẩn hóa và chỉ mục cần được dựng lại từ bản source cũ nếu muốn khôi phục hành vi cũ; down migration không tự hoàn tác hai phần này.

## Bằng chứng kiểm tra local đã có

- Service test và HTTP smoke test đã PASS sau khi giới hạn PDF chỉ tiêu đề.
- Câu `Mô tả chức năng (có vài cái)` trong scope Excel đã tìm đúng page 27 sau sửa phân loại, giữ nguyên input; biến thể ngoặc kép cũng tìm được.
- Header bo tròn/căn giữa, Advanced Search tách hai trường đã kiểm tra bằng trình duyệt. Ảnh tại `../../outputs/search-rounded-header.png`.
- Đây là bằng chứng local, chưa xác nhận cài đặt, hiệu năng hoặc tương thích trên VLEX khách hàng.
