# Nền BookStack 21.11.1 để tiếp nhận mã VLEX

## Mục tiêu hiện tại

Giữ ứng dụng BookStack gốc làm bộ khung, dựng các đường dẫn bổ sung theo cây mã nguồn VLEX do người dùng cung cấp. Người dùng có thể chép từng file VLEX vào đúng vị trí để phân tích và sửa lỗi. Chưa triển khai lại chức năng VLEX từ ảnh và chưa tạo ứng dụng độc lập cạnh BookStack.

Nguồn làm việc: `bookstack-lab/source/BookStack-21.11.1/`. Truy cập http://localhost:8080/books. Login, sách/chương/trang, editor, quyền và role, tìm kiếm, import Word đã có, export và attachment gốc tiếp tục hoạt động theo mã hiện có. Chưa thay hierarchy sách/chương/trang bằng cây folder VLEX.

Không chạy composer update, npm update hoặc thêm thư viện ứng dụng. Giữ nguyên version và lockfiles hiện có. Công cụ Python dùng để chuẩn bị cây file trên máy là tooling, không phải dependency của web. Runtime vẫn là môi trường Docker đã có; phần nhập Word bằng LibreOffice đã tồn tại từ trước, không được xem là chức năng nguyên gốc VLEX.

## Những gì đã chuẩn bị

- Các file BookStack đã có tại đường dẫn VLEX được giữ nguyên.
- Những đường dẫn khác namespace nhưng có lớp tương đương rõ ràng dùng `class_alias` tới lớp BookStack gốc. Ví dụ `app/Permissions/User.php` trỏ tới `app/Auth/User.php`; không tạo một model user thứ hai, không thay auth provider.
- File VLEX thiếu và chưa có tương đương có ghi chú `VLEX SCAFFOLD`. Chúng chưa đăng ký route, provider hoặc quyền. Nếu vô tình khởi tạo chúng, lỗi `LogicException` báo rõ file cần mã thật; không âm thầm giả lập thành công.
- `database/seeds/` có đường dẫn tương thích với `database/seeders/` hiện tại. Composer vẫn dùng namespace `Database\Seeders`; chưa tự thêm seeder legacy.
- 13 migration VLEX sau bản gốc được đặt tại `database/migrations/*.php.stub`. Tên được chuẩn hóa từ bản chép, chưa xác minh bằng file gốc. Chúng chưa được artisan migrate chạy. Không đoán schema hoặc insert quyền khi chưa biết nội dung.
- `api/requests` và `api/responses` dùng các mẫu gốc trong `dev/api` khi có; `attachments-group.json` và `search-all.json` là `{}` chờ request thật.
- `docs/` có tài liệu nền BookStack tương ứng. Tên `legal-theme-system.md` giữ theo bản chép nhưng nội dung tham khảo từ `logical-theme-system.md`; cần đối chiếu file VLEX thật.
- Các ảnh biết tên nhưng thiếu nguồn là PNG giữ chỗ 1 pixel, chưa gắn vào giao diện. Không tái dựng logo/ảnh nội bộ. Các tên không đọc rõ không được tự đoán.
- `public/public` chỉ là đường dẫn dự phòng theo bản chép. Document root vẫn là `public/`. File index lồng trả 501, không trở thành entry point thứ hai.
- Docker chạy local vẫn ở cấp `bookstack-lab`. Các file Docker thuộc cây VLEX bên trong source ở dạng `.stub` để tránh ghi đè runtime khi chưa có nội dung thật.

Xem `vlex-file-map.md`/`.json` để biết trạng thái từng đường dẫn. Các tên ghi “Old…”, “SaveModule…” và nhóm ảnh mờ nằm trong mục `unresolved`, không tạo tên đoán. Các migration cũ có năm/ngày bị OCR sai được đối chiếu với migration BookStack đang có, không tạo migration trùng.

## Khi chép từng file VLEX về local

1. Chép vào đường dẫn tương ứng bên trong `source/BookStack-21.11.1`, thay toàn bộ file scaffold/alias nếu đó là mã thật. Không chép vào archive hoặc trực tiếp vào vendor/container rồi bỏ qua source.
2. Đối chiếu namespace/import và các file phụ thuộc. Có file tại đúng vị trí không có nghĩa là dependency, route và schema của nó đã đủ. Chép model/controller chưa tự kích hoạt tính năng.
3. Với file đã tồn tại, xem diff trước khi thay thế; giữ lại thay đổi cần thiết cho môi trường local. Đặc biệt kiểm tra Auth/Permissions/Config, providers, routes và bootstrap.
4. Với migration, xác minh tên file/class, up/down, schema và dữ liệu ảnh hưởng bằng nội dung VLEX thật. Thay bản `.php.stub` bằng file `.php` thật khi đã sẵn sàng. Không chỉ đổi đuôi scaffold: nó cố ý ném lỗi khi chưa có schema.
5. Kiểm tra cú pháp và build lại container bằng `docker compose up -d --build` tại `bookstack-lab`. Entrypoint hiện chạy `php artisan migrate --force`; vì vậy một migration thật vừa chép sẽ được áp dụng khi khởi động. Sao lưu database/volume trước thay đổi schema cần giữ dữ liệu.
6. Xác minh luồng cụ thể vừa thay đổi và quyền admin/người dùng. Dùng các role/quyền BookStack đang có cho tới khi mã phân quyền VLEX thật cùng schema được ghép đầy đủ.

Không tự tạo adapter giữa “folder VLEX” và “chapter BookStack” khi chưa biết quan hệ thực. Các file như `HierarchyFolder`, `LibraryMap`, `AttachedFileRepo`, `ImportLibraryController` là vị trí chờ mã để nghiên cứu cách triển khai thực tế. Phân quyền xem/thêm/sửa/xóa theo nhóm/người dùng chưa được suy đoán hoặc nối vào hệ thống đang chạy.

## Prototype cũ

Mã, CSS, view, tài liệu và test của Workspace độc lập được chuyển tới `archive/workspace-prototype/`. Route upload/create/edit/download của prototype đã gỡ; `/vlex` chỉ chuyển về `/books` để bookmark cũ không mở màn hình sai mục tiêu.

Giữ migration `2026_10_22_000001_create_vlex_workspace.php` trong source vì nó đã chạy, giữ bảng `vlex_nodes`/`vlex_versions` và tệp trong volume để không mất dữ liệu đã nhập. Chúng không tham gia luồng BookStack. Việc giữ lịch sử migration không kích hoạt lại Workspace. Chưa xóa hoặc chuyển đổi các dữ liệu này sang thực thể BookStack.

## Kiểm tra cấu trúc

`tests/check-vlex-structure.php` kiểm tra alias cùng lớp gốc, scaffold chưa được nối vào route, migration stub chưa kích hoạt, lockfiles và khả năng khởi động BookStack. Bài kiểm tra HTTP `tests/word-import/check-http.ps1` kiểm tra nhập DOC/DOCX vào chính luồng sách/trang, bao gồm editor và xuất bản.

## Kết quả kiểm tra ngày 22/10/2026

Đã build và tạo lại container thành công. 30 alias trỏ đúng lớp gốc, 81 file PHP hợp lệ, 13 migration stub không bị migrator thu thập. Auth vẫn dùng BookStack\Auth\User. Phiên bản BookStack/PHP/Laravel/MySQL và 104 thư viện production khớp nền ban đầu; composer.json/lock, package.json/lock và version không đổi. Kiểm thử HTTP nhập DOC/DOCX qua bản nháp tới xuất bản đều qua, gồm tiếng Việt, bảng/ô gộp, ảnh và liên kết mục lục. Bookmark /vlex đã chuyển về giao diện Books gốc.

Bản cây app người dùng cung cấp được giữ tại docs/references/vlex-app-tree.txt; scripts/prepare-vlex-structure.py chỉ là công cụ chuẩn bị ban đầu, không cần chạy khi chép mỗi file VLEX. Chức năng cụ thể của các file scaffold cần được kiểm tra sau khi thay bằng mã thật.

