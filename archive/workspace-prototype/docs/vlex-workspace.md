# VLEX Workspace — bản mô phỏng ban đầu

Truy cập http://localhost:8080/vlex. Dùng đăng nhập BookStack hiện có. Bản này chạy cục bộ, không gửi tài liệu lên dịch vụ đám mây.

## Cơ sở tái dựng

Nền thực tế là BookStack v21.11.1, PHP 8.0.30, Laravel 8.70.2 và MySQL 8.0.36. Ảnh tham chiếu được dùng để tái dựng thanh VLEX, cây thư mục bên trái, thẻ trang màu xanh và menu Details / Actions bên phải. Danh sách tệp người dùng cung cấp là bản mô tả cấu trúc, không phải mã nguồn tùy biến VLEX. Một số tên migration trong danh sách có lỗi nhận dạng ký tự; không tạo migration giả mang những tên đó.

Workspace là phần cải biên mới, đặt cạnh chức năng BookStack gốc. Sách/chương/trang BookStack và thư mục/trang/tệp Workspace hiện là hai kho riêng. Liên kết Projects / BookStack mở kho gốc, nơi đã có nhập DOC/DOCX thành trang có định dạng. Không tự nhận bản này là mã nguồn VLEX khách hàng hoặc đã khôi phục toàn bộ các Modules/phân quyền tùy biến.

## Cấu trúc bổ sung

```text
bookstack-lab/
├── source/BookStack-21.11.1/
│   ├── app/Http/Controllers/VlexWorkspaceController.php
│   ├── database/migrations/2026_10_22_000001_create_vlex_workspace.php
│   ├── resources/views/vlex/
│   │   ├── workspace.blade.php
│   │   └── tree.blade.php
│   ├── public/dist/vlex-workspace.css
│   ├── resources/views/common/header.blade.php  # liên kết VLEX
│   └── routes/web.php                         # route /vlex/* có xác thực
├── docs/vlex-workspace.md
├── tests/check-vlex.ps1
└── tests/check-vlex-isolation.php
```

Các thư mục app, database, public, resources, routes, storage, tests, themes, api, dev và các tệp Composer/npm của nền BookStack được giữ tại thư mục source. Không tạo lại vendor bằng tệp rỗng; vendor được Composer cài theo lockfile trong Docker. docker/ và compose.yaml của môi trường chạy nằm ở cấp bookstack-lab.

## Chức năng đang dùng được

- Tạo thư mục lồng nhiều cấp; điều hướng cây và breadcrumb; xem lưới/danh sách.
- Tạo, đổi tên và sửa trang văn bản thuần; mã HTML được hiển thị dưới dạng văn bản an toàn.
- Tải tệp lên, tải xuống, tải phiên bản mới và tải lại từng phiên bản cũ. Tối đa 20 MB/tệp.
- Nhận các đuôi DOC/DOCX/DOCM/DOT/DOTX/DOTM, XLS/XLSX/XLSM/XLSB/XLT/XLTX/XLTM, CSV, PDF, RTF, ODT/ODS để lưu nguyên bản.
- Xem PDF qua trình xem tích hợp của trình duyệt; kiểm tra chữ ký PDF khi yêu cầu xem.
- Di chuyển giữa các thư mục; chặn chuyển thư mục vào chính nó hoặc hậu duệ.
- Tìm kiếm theo tên trong kho của tài khoản, đánh dấu yêu thích.
- Đưa cả cây thư mục vào thùng rác và khôi phục. File trong thùng rác không được tải xuống. Khôi phục mục có thư mục cha còn trong thùng rác sẽ đưa mục đó về gốc.
- Tách dữ liệu theo chủ sở hữu, xác thực tải tệp và CSRF cho thao tác ghi. Kể cả admin cũng không truy cập trực tiếp Workspace của tài khoản khác qua ID.

Nút khởi tạo mẫu chỉ xuất hiện khi kho trống. Nó tạo tên thư mục theo ảnh, nhánh `test / test 19012024 / vbfbfbfg` và ba trang mẫu ghi rõ nội dung mô phỏng. Không chứa tài liệu nội bộ thật.

## Lưu trữ và chạy lại

`vlex_nodes` lưu cây thư mục, trang, trạng thái yêu thích và thùng rác; `vlex_versions` lưu metadata các bản tệp. Tệp nằm tại `storage/uploads/files/vlex/<user_id>/`, thuộc volume storage, ngoài public. Database thuộc volume database. Giữ cả hai volume khi sao lưu/khôi phục. Không xóa volumes nếu muốn giữ dữ liệu.

```powershell
docker compose up -d --build
./tests/check-vlex.ps1
docker compose cp tests/check-vlex-isolation.php app:/tmp/check-vlex-isolation.php
docker compose exec -T app php /tmp/check-vlex-isolation.php
```

Kiểm thử HTTP tạo dữ liệu thử có tiền tố VLEX regression, rồi đưa vào thùng rác. Bài kiểm tra cách ly dữ liệu dùng transaction và rollback.

Kiểm thử cần PowerShell 7 và Python 3 để tạo tệp mẫu. Nếu `python` không có trong PATH, dùng `./tests/check-vlex.ps1 -PythonExecutable '<đường dẫn python.exe>'`.

Đã kiểm tra ngày 22/10/2026: tạo thư mục/trang, sửa và tìm kiếm, yêu thích, chống vòng lặp khi di chuyển, tải Word và giữ phiên bản cũ, từ chối HTML, tải lên/xuống XLSX và PDF có SHA256 trùng khớp, MIME xem PDF, xóa/khôi phục hậu duệ, chặn tải ẩn danh và chặn xem/tải/sửa chéo tài khoản. Đã tạo lại container từ image mới và xác minh PHP/Laravel/MySQL cùng 104 thư viện vẫn khớp nền gốc. Giao diện cây thư mục, thẻ trang và hộp thoại tạo thư mục đã được kiểm tra trực tiếp trên trình duyệt.

## Phần chưa tích hợp

Lưu nguyên bản không đồng nghĩa với chỉnh sửa và hiển thị chính xác mọi phiên bản Microsoft Office. Hiện chỉnh sửa Word/Excel bằng ứng dụng trên máy, sau đó tải phiên bản mới; Workspace chưa có bộ soạn thảo Office trên web, đồng biên tập, khóa tài liệu, OCR, tìm toàn văn tệp, so sánh nội dung, phân quyền nhóm/kế thừa hay chia sẻ liên kết. Trang Workspace là văn bản thuần; nhập Word có định dạng tiếp tục dùng chức năng BookStack.

Để tiến tới môi trường khách hàng đầy đủ cần mã nguồn/lockfile tùy biến thực, ma trận quyền và bộ tài liệu kiểm chứng tương thích. Giai đoạn tiếp theo có thể tích hợp bộ soạn thảo Office và nối mô hình Workspace với thực thể BookStack sau khi thống nhất dữ liệu và quyền truy cập.
