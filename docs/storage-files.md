# File upload trong cây thư mục VLEX

Hồ sơ để tra soát và ghép vào VLEX thật: [tên từng file, tác dụng, điểm tương thích và checklist](file-upload-vlex-handover.md), kèm [manifest SHA-256](file-upload-vlex-manifest.json). Các nhóm tính năng khác nằm trong [mục lục bàn giao](vlex-handover-index.md).

Cấu trúc người dùng xác nhận ngày 29/10/2026: **shelves → book 1 → book 2 → … → book n → page + file upload**. Book chính là folder. File gốc nằm trong Book thật, cùng danh sách với Page, không có kho hoặc cây folder riêng.

## Ghép theo danh sách file VLEX đã cung cấp

| File trong source/BookStack-21.11.1 | Vai trò hiện tại |
| --- | --- |
| app/Entities/Models/AttachedFile.php | Model file upload, có đúng một book_id. |
| app/Entities/Repos/AttachedFileRepo.php | Lưu/xóa file nguyên bản trên disk private. |
| app/Http/Controllers/FileAttachedController.php | Form upload, kết quả từng file, tải xuống, màn move/tìm đích và màn xác nhận xóa riêng. |
| app/View/LibraryMap.php | Quan hệ book cha–con/shelf, đường dẫn, move đơn cha, kiểm vòng lặp và khóa transaction. |
| app/ViewComponents/HierarchyFolder.php | Lấy book con, page/chapter, file và breadcrumb để hiển thị trong Book. |
| app/Entities/Models/Book.php, Bookshelf.php | Book cha–con và file trực tiếp; appendBook chuyển về đúng một shelf. |
| app/Entities/Repos/BookshelfRepo.php | Cập nhật shelf bằng thao tác chuyển Book, gỡ mọi membership cũ. |
| app/Entities/BreadcrumbsViewComposer.php, app/Entities/Tools/SiblingFetcher.php | Breadcrumb/sibling theo cha thực tế, không dùng shelf cũ trong session làm vị trí lưu. |
| app/Entities/Tools/TrashCan.php | Xóa/khôi phục book con theo Book cha; file giữ theo Book trong thùng rác, bị xóa vật lý khi Book bị purge. |

Đây là triển khai lab dựa trên cây file VLEX và hành vi người dùng đã xác nhận. Danh sách đường dẫn không cung cấp nội dung lớp/schema production. Các mục trên được đánh dấu implemented-lab trong vlex-file-map; không trình bày chúng như mã gốc VLEX đã nhận. ImportLibraryController và các scaffold phân quyền khác tiếp tục chờ nội dung thật.

Migration mới thêm books.parent_book_id, bảng vlex_book_files và một hàng khóa cấu trúc. Đây là schema lab, cần map sang schema VLEX thực tế khi có nội dung file. Không kích hoạt bằng cách đổi đuôi các migration VLEX .php.stub chưa biết schema. Migration kho riêng đã từng chạy được giữ trong lịch sử; các bảng vlex_storage_* cũ không còn route/model/view hoạt động và không bị xóa dữ liệu tự động.

## Quan hệ và thao tác

- Book có một cha là Book hoặc Shelf; Book lồng không có membership shelf trực tiếp. Các Book cũ chưa phân loại vẫn có thể xuất hiện ở danh sách Books. Membership cũ nhiều shelf không bị migration tự ý sửa; khi chuyển hoặc cập nhật placement, mọi liên kết cũ được gỡ và chỉ giữ đúng cha đích.
- Giao diện Book có Tạo book con, Chuyển book, Upload file gốc và danh sách Page + file. Các route upload nằm dưới /books/{slug}/upload-files. Không có menu/route /storage.
- File có một book_id bắt buộc. Move cập nhật chính hàng file trong transaction, không copy blob hoặc giữ bảng nối tới book cũ. ID, hash và bytes không đổi. Chuyển Book mang theo subtree, Page và file; gỡ parent_book_id/membership shelf cũ, breadcrumb tính lại từ quan hệ thật.
- Move cấu trúc khóa một hàng chung để ngăn hai move đồng thời tạo vòng lặp. Từ chối chuyển vào chính Book hoặc hậu duệ, kể cả request giả mạo.
- Tải file chỉ có download; không tạo Page đại diện, không chuyển nội dung thành HTML, không cung cấp xem/sửa file. Disk local_secure_attachments, prefix vlex-book-files/, tên ngẫu nhiên, tải qua auth và Content-Disposition attachment.
- Quyền xem/download theo Book đang chứa file; upload cần page-create trên Book và attachment-create-all. Move cần quyền sửa attachment nguồn cùng quyền upload vào Book đích; delete cần attachment-delete. Move Book kiểm quyền sửa nguồn, cha cũ và đích. Không tự nhận đây là ACL nhóm/người dùng VLEX production; các scaffold quyền vẫn cần đối chiếu mã thật.
- Nút chuyển file mở `/files/{id}/move`, dùng lại `entities.selector` của màn chuyển Page. Bộ chọn nhận URL tìm đích riêng; mặc định các màn cũ giữ endpoint gốc. Tìm theo tên shelf hoặc book/ancestor, trả tối đa 30 Book có quyền xem và thêm file, có đầy đủ đường dẫn từ LibraryMap/HierarchyFolder; không giới hạn vào shelf hiện tại, không cho chọn chính Book nguồn. POST vẫn kiểm lại quyền nguồn và đích. `entity_selection=book:id` là dữ liệu từ bộ chọn; `book_id` cũ còn được hỗ trợ cho request tích hợp.
- Nút xóa mở `/files/{id}/delete`. GET chỉ hiển thị tên file, đường dẫn, Hủy và Xác nhận xóa; chỉ DELETE mới xóa vĩnh viễn. Các view `files/move`, `files/delete` dùng bố cục/selector từ màn Page hiện có; không dùng dropdown inline hoặc xây bộ chọn độc lập. Xóa Book dùng thùng rác BookStack và mang theo book con; khôi phục không tự khôi phục book con đã được xóa riêng trước đó. Purge xóa file vật lý theo Book.

## Kiểm tra tiếp nhận từng file

DOC/DOCX/XLS/XLSX/PDF, tạm 20 MiB/file qua VLEX_STORAGE_MAX_MB. Đồng bộ PHP/proxy nếu đổi giới hạn. Giới hạn này độc lập với nhập Excel thành Page (2 MiB/5 sheet).

Giao diện gửi riêng từng file để không vượt tổng post_max_size khi chọn nhiều file. Server trả results gồm tên, trạng thái, lỗi và cảnh báo từng file; một file bị từ chối không ngăn file khác. Endpoint vẫn hỗ trợ mảng tối đa 20 file/request, chịu giới hạn tổng của PHP/proxy.

Kiểm lỗi upload, đuôi/dung lượng/rỗng; DOC/XLS kiểm OLE và stream đúng loại; DOCX/XLSX kiểm gói ZIP, checksum CRC, XML, tối đa 10.000 thành phần/200 MiB giải nén. Office phải mở và xuất thử PDF qua LibreOffice trong profile riêng, tắt macro/cập nhật liên kết. PDF qua qpdf --check, pdfinfo, dựng mọi trang bằng Poppler ở kích thước thumbnail, tối đa 2.000 trang. Mỗi công cụ timeout 60 giây. File mã hóa/đặt mật khẩu hoặc không xác minh hoàn tất bị từ chối với lý do; original không đổi.

Mốc Office 2010 trở lên: cảnh báo DOC/XLS và compatibilityMode trước Word 2010; không coi file hỏng chỉ vì định dạng cũ. Đọc được qua LibreOffice/Poppler không chứng nhận hiển thị đúng trên từng phiên bản Microsoft Office/phần mềm PDF. Thông tin kiểm tra vẫn được giữ trong metadata file. Kết quả upload tách tên file, nhãn loại file và trạng thái: dấu ✓ xanh khi đã lưu, dấu × đỏ khi thất bại; chỉ thất bại mới hiển thị lý do. File đang chờ/đang kiểm tra dùng màu trung tính.

Danh sách file trong Book cũng tách tên, loại và dung lượng, hiển thị dấu ✓/Đã lưu; không lặp lại thông báo kiểm tra dài. Chuyển/xóa dẫn tới màn riêng theo luồng VLEX, tải xuống vẫn truy cập trực tiếp.

## Chạy và kiểm thử

Dockerfile thêm qpdf/poppler-utils, không thay Composer/npm dependencies hoặc lockfiles. Build docker compose build app rồi docker compose up -d app; entrypoint chạy migration.

Test service: copy fixture DOC/DOCX trong tests/word-import và XLS/XLSX trong tests/excel-import vào /tmp/storage-fixtures trong container; copy tests/storage/check-service.php vào /tmp/check-storage.php; chạy docker compose exec -T --user www-data app php /tmp/check-storage.php để tránh tạo thư mục upload thuộc root. Kiểm file giả/hỏng/CRC/XML/compatibility và giới hạn, quan hệ Book thật, move đơn cha/subtree/vòng lặp, quyền theo Book, bytes gốc và delete. Dữ liệu DB rollback.

Test HTTP tests/storage/check-http.ps1 tạo hai Shelf, ba cấp Book, Page và file, kiểm multi-file, download, chuyển file và Book, breadcrumb mới, vòng lặp, guest, delete và route kho cũ bị gỡ. File thử bị xóa, Book/Shelf thử đưa vào thùng rác trong finally. Chỉ chạy trên lab.
