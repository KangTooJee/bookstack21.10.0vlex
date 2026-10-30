# Bàn giao thử nghiệm Word → VLEX

Ngày chốt: 29/10/2026. Phạm vi: dán nội dung Word, font/căn lề, danh sách La Mã, bảng, ảnh nội bộ và đối chiếu nhập/xuất DOCX. Chấp nhận khác ngắt trang. Nền thử nghiệm: BookStack 21.11.1, TinyMCE 4.9.4, PHP 8.0.30, LibreOffice trên máy chủ.

## Kết luận cần giữ khi nghiệm thu

- Chưa đáp ứng Ctrl+A/Ctrl+C từ Word rồi Ctrl+V có đầy đủ ảnh cho mọi tài liệu.
- Log thực tế của khách hàng: HTML 170572 ký tự, RTF 0 ký tự, không có clipboard file ảnh; HTML có 15 ảnh chỉ trỏ tới file://. Trang web không nhận bytes của các ảnh này và không đọc được file tạm trên máy người dùng. Chỉ nhận diện được ảnh không có nghĩa đã nhận dữ liệu ảnh.
- Không chuyển ảnh thật thành vector và không tiếp tục phát triển cầu nối Windows. Không cần cài ứng dụng ngoài, không có tùy chọn Enable automatic Word image paste trong luồng hiện tại.
- Khi thiếu dữ liệu ảnh: toast cảnh báo “Clipboard chưa cung cấp đủ dữ liệu ảnh nên chưa dán đoạn này. Hãy dán phần chữ trước; sau đó copy từng ảnh trong Word, đặt con trỏ vào vị trí mong muốn rồi nhấn Ctrl+V để dán ảnh.” Nội dung đang sửa giữ nguyên; không chèn placeholder. Có thể dán phần chữ bằng Ctrl+Shift+V nếu chấp nhận chữ thuần; muốn giữ định dạng thì chọn riêng phần chữ để copy.
- Khi clipboard có dữ liệu ảnh hỗ trợ: tải ảnh vào gallery nội bộ trước khi chèn, chờ ảnh tải được, rồi chèn tại con trỏ/vùng chọn. Dán riêng từng ảnh là cách người dùng đã xác nhận dùng được; vẫn cần kiểm tra với máy khách thực tế khi triển khai.
- Nhập DOCX cung cấp được dữ liệu nguồn nhưng là luồng khác, không được tính là đã đạt yêu cầu Ctrl+A/Ctrl+V.

## File và tác dụng

Đường dẫn dưới đây tương đối với bookstack-lab. Các file dùng chung còn chứa thay đổi của tính năng khác; cần đối chiếu từng phần, không chép đè cả file vào VLEX thật. Manifest JSON kèm theo lưu đường dẫn và SHA-256 của bản hiện tại để tra soát.

| File | Tác dụng / điểm tích hợp |
| --- | --- |
| source/BookStack-21.11.1/public/dist/word-paste.js | Luồng paste: giữ định dạng hỗ trợ, La Mã, đọc RTF/bitmap, upload ảnh, giữ con trỏ trước xử lý bất đồng bộ, chặn lưu khi đang upload, toast thiếu dữ liệu ảnh. Không gọi ứng dụng ngoài. |
| source/BookStack-21.11.1/resources/views/pages/edit.blade.php | Nạp script paste nội bộ trước khởi tạo TinyMCE, cache bust theo nội dung file. |
| source/BookStack-21.11.1/public/dist/office-content.css | Hiển thị font/bảng/ảnh nội dung Office trong editor và trang xem; xử lý bảng rộng. |
| source/BookStack-21.11.1/resources/views/layouts/base.blade.php | Nạp CSS Office nội bộ và cache bust cho trang xem. |
| source/BookStack-21.11.1/app/Uploads/OfficeHtmlStyles.php | Chuẩn hóa CSS Office được hỗ trợ, font/cỡ chữ, thông tin trang và wrapper tài liệu; dùng trong nhập Word. |
| source/BookStack-21.11.1/app/Uploads/WordImportService.php | Chuyển DOC/DOCX bằng LibreOffice, bảo toàn định dạng hỗ trợ và ảnh; thêm chuyển nhóm ảnh RTF cho clipboard. |
| source/BookStack-21.11.1/app/Http/Controllers/WordPasteController.php | Endpoint chuyển ảnh RTF, kiểm tra quyền, giới hạn dữ liệu và nội dung RTF. |
| source/BookStack-21.11.1/routes/web.php | Đăng ký POST /clipboard/word-images trong nhóm auth và throttle; file dùng chung nhiều tính năng. |
| source/BookStack-21.11.1/app/Uploads/WordExportService.php | Xuất DOCX, xử lý tài liệu Office, tránh thêm tiêu đề ứng dụng và đoạn trống cuối gây trang trắng. |
| source/BookStack-21.11.1/app/Uploads/WordExportAppearance.php | Chuẩn hóa định dạng khi xuất, gồm đậm/nghiêng/gạch chân lồng nhau. |
| source/BookStack-21.11.1/app/Uploads/WordExportLayout.php | Áp dụng thông tin bố cục/kích thước/lề tài liệu Office khi xuất. |
| source/BookStack-21.11.1/app/Http/Controllers/WordImportController.php | Điểm vào nhập Word, liên kết draft và loại tài liệu; có thay đổi dùng chung. |
| source/BookStack-21.11.1/app/Http/Controllers/PageExportController.php | Điểm tải DOCX; có cả các đường xuất khác, chỉ đối chiếu phần Word. |
| source/BookStack-21.11.1/public/clipboard-check.html | Trang chẩn đoán các dạng clipboard, số ảnh, bytes/RTF; không phải chức năng editor sản phẩm. |
| compose.yaml | URL/bind LAN, tắt dịch vụ ngoài; cấu hình triển khai thử nghiệm, không chép đè cấu hình thật. |
| tests/word-paste/check-browser.cjs | Kiểm tra paste mô phỏng, toast hướng dẫn, upload/lưu/mở lại, La Mã, WMF, lỗi quyền và không gọi ngoài. |
| tests/word-paste/check-paste-position.cjs | Kiểm tra chuỗi keydown/paste/keyup tại con trỏ, vùng chọn, ô bảng và Ctrl+Shift+V. |
| tests/word-paste/check-customer-fidelity.cjs | Ca mẫu khách hàng/font/bảng/ảnh; có các kỳ vọng lịch sử cần đối chiếu với chính sách không chèn đoạn thiếu ảnh hiện tại trước khi chạy lại. |
| tests/word-paste/check-roundtrip.py | Đối chiếu DOCX nguồn/xuất: text, cỡ chữ, nghiêng, ảnh và bố cục. |
| docs/word-paste-trial.md | Ghi chép triển khai và phạm vi thử nghiệm paste. |
| docs/word-import-export-changes.md | Ghi chép nhập/xuất Word trước đợt paste, dùng để tra cứu bổ sung. |
| docs/word-import-export-manifest.json | Manifest nhập/xuất Word trước đây; không thay thế snapshot mới của đợt paste. |

## File thử nghiệm đã loại — không đưa vào sản phẩm

| File | Trạng thái |
| --- | --- |
| scripts/prepare-word-clipboard.ps1 | Công cụ clipboard Windows đã ngừng dùng; không cài, không chạy, không bật tự động. |
| tests/word-paste/check-native-bridge.ps1 | Chỉ kiểm tra công cụ đã loại. |
| tests/word-paste/check-native-upload.cjs | Chỉ kiểm tra thử nghiệm native cũ. |
| tests/word-paste/check-full-word-paste.cjs | Ca 15 ảnh với cầu nối và clipboard mô phỏng; không chứng minh paste Word thuần web. |
| tests/word-paste/serve-native-bridge-fixture.ps1 | Máy chủ fixture cho thử nghiệm cầu nối cũ. |
| docs/native-word-clipboard-trial.md | Lịch sử phương án bị loại. |

## Áp dụng và kiểm tra tương thích trên VLEX thật

1. Đối chiếu phiên bản TinyMCE, hook editor-tinymce::pre-init, window.editor, $events, $http, baseUrl, form data-page-id và endpoint gallery. Không giả định bản VLEX thật trùng bản thử.
2. Đối chiếu quyền image-create-all, CSRF, throttle, PHP upload limit, LibreOffice, cấu hình xử lý RTF và storage uploads. Mã PHP/route dùng chung cần merge riêng phần Word.
3. Giữ CSS Office khi lưu, lọc HTML và mở lại; kiểm tra font, căn lề, từng cạnh border, bảng không viền, bảng rộng và danh sách La Mã. Cài font phù hợp trên máy khách/máy chuyển đổi theo quyền sử dụng. Font Times New Roman từng được chép vào container thử nghiệm, chưa đóng gói vào image.
4. Kiểm tra Word Windows thực tế: text không ảnh, text kèm ảnh, Ctrl+A có ảnh, dán từng ảnh tại giữa đoạn/vùng chọn/ô bảng. Ca thiếu bytes phải hiện toast hướng dẫn, giữ nguyên nội dung và không tạo placeholder. Ca có bytes phải lưu ảnh trước khi hiển thị và giữ sau Save/mở lại.
5. Kiểm tra lại nhập/sửa/lưu/xuất DOCX bằng mẫu khách hàng; khác ngắt trang được chấp nhận nhưng chưa có cam kết giống hệt Word. Kiểm thử mô phỏng không thay thế nghiệm thu với clipboard thật.
6. Trên LAN kiểm tra từ máy khác, APP_URL/URL ảnh, quyền truy cập và không gọi CDN/Internet. Các cập nhật chạy thử đã dùng docker compose cp; phải build image chứa mã mới trước khi recreate. Chưa nghiệm thu trên VLEX sản xuất hoặc từ máy khác trong LAN.

Không đưa các thay đổi PDF/tìm kiếm/Excel không thuộc phạm vi này vào bản vá chỉ vì chúng cùng nằm trong working tree.
