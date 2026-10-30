# Mục lục hồ sơ đối chiếu trước khi ghép VLEX thật

Cập nhật: **30/10/2026**, Asia/Saigon. Mục lục này giúp tìm lại tên file, tác dụng và giới hạn của từng nhóm thay đổi. Nó không xác nhận tương thích production và không thay thế ngày chốt/hash riêng của mỗi hồ sơ.

| Nhóm | Bảng file / hướng dẫn đối chiếu | Manifest mã nguồn | Lưu ý |
| --- | --- | --- | --- |
| Upload file gốc, kiểm tra tiếp nhận, trạng thái xanh/đỏ, move/tìm đích/delete trong cây Book | [Hồ sơ tra soát từng file](file-upload-vlex-handover.md), [hành vi hiện tại](storage-files.md) | [SHA-256 và trạng thái Git](file-upload-vlex-manifest.json) | Chốt 30/10/2026, có file chưa commit. Tận dụng các vị trí VLEX; hierarchy/schema trong lab phải map lại vì VLEX thật đã có. |
| Nhập/xuất Word và Excel | [Bảng file Word/Excel](vlex-integration-file-map.md), [Word](word-import-export-changes.md), [Excel](excel-import-export-changes.md) | [Manifest Word/Excel](vlex-integration-manifest.json) | Hồ sơ ngày 27/10/2026 theo mốc riêng; một số file dùng chung đã đổi tiếp sau đó. Hash cũ không phải snapshot mới nhất của mọi file hiện tại. |
| Nhập/xem/xuất PDF gốc | [Bảng file PDF](pdf-import-export-changes.md), [tương thích trình duyệt](pdf-browser-compatibility-notes.md) | [Manifest PDF](pdf-import-export-manifest.json) | Luồng PDF Page khác file PDF upload nguyên bản cùng Book. Không nhầm hai controller/giới hạn/semantics. |
| Tìm kiếm Name/Word/Excel/PDF | [Hồ sơ tương thích](search-vlex-compatibility-handover.md), [bảng thay đổi](search-scope-changes.md) | [Manifest tìm kiếm](search-scope-manifest.json) | Không dùng Elasticsearch/OCR theo phạm vi đã chốt. Kiểm lại ACL và chỉ mục/nội dung khi ghép. |
| Dán Word vào editor | [Hồ sơ dán Word](word-paste-vlex-handover.md), [phạm vi thử nghiệm](word-paste-trial.md) | [Manifest dán Word](word-paste-vlex-manifest.json) | Chưa bảo đảm Ctrl+A/Ctrl+C/Ctrl+V có đủ ảnh với mọi tài liệu; đọc giới hạn clipboard cụ thể trước nghiệm thu. |
| Danh sách đường dẫn VLEX người dùng đã cung cấp | [Bảng đường dẫn/trạng thái](vlex-file-map.md), [hướng dẫn lab](vlex-local-development.md), [cây app tham chiếu](references/vlex-app-tree.txt) | [Trạng thái máy đọc](vlex-file-map.json) | scaffold là chờ nội dung; implemented-lab là triển khai lab theo hành vi đã xác nhận, không phải đã nhận mã gốc. |

Các hồ sơ có thể cùng nhắc một file như routes/web.php, books/show.blade.php, layouts/base.blade.php hoặc public/dist/app.js. Khi ghép nhiều tính năng, đối chiếu diff và từng vùng thay đổi; không chép lần lượt toàn bộ các phiên bản file khiến tính năng trước bị ghi đè. Thông tin đăng nhập, .env và dữ liệu tài liệu riêng của người dùng không thuộc bộ bàn giao mã nguồn này.
