# Thử nghiệm Ctrl+V từ Microsoft Word vào New page

**Trạng thái 29/10/2026: chưa đạt yêu cầu Ctrl+A từ Word có đủ ảnh.** Log thực tế có 15 đường dẫn file:// nhưng không có bytes ảnh trong file clipboard hoặc RTF. Luồng web không gọi ứng dụng ngoài và không có tùy chọn Enable automatic Word image paste. Thử nghiệm cầu nối Windows đã bị loại theo yêu cầu. Kiểm thử clipboard mô phỏng không thay thế nghiệm thu thao tác Word thực tế.

Bản bàn giao để áp dụng và tra soát VLEX thật: [word-paste-vlex-handover.md](word-paste-vlex-handover.md); snapshot đường dẫn, tác dụng và SHA-256: [word-paste-vlex-manifest.json](word-paste-vlex-manifest.json). Khi thiếu dữ liệu ảnh, toast hướng dẫn dán phần chữ trước, sau đó copy từng ảnh trong Word, đặt con trỏ vào vị trí mong muốn và Ctrl+V; không chèn đoạn thiếu ảnh hoặc placeholder.

Ngày: 28/10/2026. Giữ BookStack 21.11.1, TinyMCE 4.9.4, PHP 8.0.30 và lockfile hiện có; không thêm thư viện ứng dụng hoặc CDN.

## Thao tác

1. Trong Word trên Windows, chọn một phần văn bản/danh sách/bảng/ảnh hoặc riêng icon rồi Ctrl+C.
2. Mở New page hoặc Edit trong BookStack, đặt con trỏ trong nội dung và Ctrl+V.
3. Chờ bộ soạn thảo xử lý ảnh, sau đó Save Page. Mở lại trang và Edit để kiểm tra.

Khi clipboard cung cấp bytes ảnh, ảnh được đưa qua endpoint gallery hiện có, gắn với page đang sửa và lưu vào volume uploads. Không lưu đường dẫn ảnh tạm file:// trên máy người copy. Khi chỉ có đường dẫn file://, luồng dán hiện chưa lấy được ảnh; nhập DOCX là phương án khác để cung cấp dữ liệu gốc, không phải nghiệm thu cho yêu cầu Ctrl+A/Ctrl+V.

## Cách xử lý

- HTML của Word: chuyển mso-list sang ol/li, giữ số bắt đầu, số La Mã hoa/thường và cấp con. Hỗ trợ hai dạng conditional comment supportLists và tên kiểu roman-upper/roman-lower của Word.
- Ảnh PNG/JPEG trong RTF hoặc file bitmap của clipboard: đưa vào kho ảnh trước khi chèn nội dung. Ảnh đơn không cần HTML cũng dùng được.
- Icon WMF/EMF: gửi riêng các nhóm pict dưới dạng RTF tối giản đến máy chủ, LibreOffice chuyển sang ảnh web, sau đó tải vào gallery. Không dùng dịch vụ chuyển đổi Internet.
- Nếu thiếu bytes ảnh hoặc bộ chuyển không đọc được định dạng: báo lỗi và giữ nguyên nội dung đang sửa, không chèn đoạn thiếu ảnh hoặc placeholder. Lỗi upload cũng giữ nguyên nội dung có sẵn và báo thất bại.
- Chặn nút lưu và lệnh lưu bằng bàn phím trong lúc upload để không lưu nhầm nội dung chưa có ảnh.
- Endpoint chuyển clipboard yêu cầu đăng nhập, quyền image-create-all, CSRF, giới hạn 20 MB và 20 yêu cầu/phút. Chặn RTF chứa field, object, objdata, filetbl hoặc datastore; timeout chuyển đổi 60 giây và dọn thư mục tạm.

## Các file

| File trong source/BookStack-21.11.1 | Vai trò |
| --- | --- |
| public/dist/word-paste.js | Đọc clipboard, chuẩn hóa danh sách, xử lý và tải ảnh, bảo vệ khi đang lưu ảnh |
| resources/views/pages/edit.blade.php | Nạp script nội bộ qua hook TinyMCE hiện có, có cache bust theo SHA-256 |
| app/Http/Controllers/WordPasteController.php | API chuyển ảnh RTF từ clipboard |
| app/Uploads/WordImportService.php | Dùng lại LibreOffice, bổ sung đường chuyển RTF chỉ cho clipboard; đường import doc/docx giữ nguyên |
| routes/web.php | POST /clipboard/word-images trong nhóm auth, throttle |

## Mạng LAN

compose.yaml hỗ trợ APP_BIND_ADDRESS và APP_URL. Mặc định vẫn là localhost:8080. Để triển khai vào LAN, đặt trong .env (thay bằng IP/hostname thực của máy chủ):

```dotenv
APP_BIND_ADDRESS=192.168.1.10
APP_URL=http://192.168.1.10:8080
APP_PORT=8080
```

Sau đó build/chạy lại bằng `docker compose up -d --build`. Các máy dùng cùng APP_URL để mọi đường dẫn ảnh và tài nguyên trỏ đúng máy chủ nội bộ. Quy tắc mạng/firewall cần cho phép cổng đã chọn. Nếu đã lưu ảnh với hostname cũ, cần cập nhật URL trong database theo công cụ chuyển URL của BookStack trước khi nghiệm thu LAN.

DISABLE_EXTERNAL_SERVICES được bật trong compose. Draw.io mặc định tắt; nếu cần có thể đặt DRAWIO thành URL dịch vụ Draw.io nội bộ. Script, CSS, TinyMCE và ảnh của luồng dán không dùng CDN. Lần build đầu cần sẵn image/gói phụ thuộc; môi trường runtime không cần tải thư viện từ Internet.

Bản đang chạy được cập nhật bằng docker compose cp để thử ngay; lần recreate tiếp theo phải build image có các file mới. Chưa nghiệm thu từ một máy khác trong LAN.

## Kiểm tra và phạm vi

### Sửa lỗi theo mẫu Quỹ Công Đoàn ngày 28/10/2026

Phạm vi người dùng đã xác nhận: ưu tiên nội dung, font, bảng và ảnh; chấp nhận khác ngắt trang. Không đặt tiêu chí giữ nguyên header/footer và vị trí đối tượng nổi cho mọi tài liệu.

- Chụp lại các định dạng và File của clipboard ngay trong sự kiện paste, trước bước bất đồng bộ; không đọc DataTransfer đã hết vòng đời.
- Chuẩn hóa CSS trong head/class thành inline trước khi xóa head; giữ các thuộc tính viền dài như border-top-width/color/style, text-indent và line-height.
- Chuyển font legacy thành span trước TinyMCE để không đổi 13pt/11pt thành medium/small. Khi xuất, giải quyết đồng thời đậm/nghiêng/gạch chân ở từng đoạn text để Writer không làm mất nghiêng khi gặp strong/em lồng nhau.
- Bỏ qua spacer 1×1 cả trong HTML và dữ liệu ảnh RTF; không cảnh báo ảnh cho đoạn Google Docs chỉ có text. Không bỏ nhầm ảnh thật có kích thước HTML bằng 0: sau khi lưu gallery, decode ảnh, gỡ kích thước 0 và xác nhận ảnh có kích thước tự nhiên trước khi chèn.
- Ảnh HTTP(S)/blob đọc được từ trình duyệt được tải một lần rồi lưu vào gallery. Nếu trình duyệt chặn CORS hoặc clipboard không cung cấp bytes ảnh, không thể truy cập file:// trên máy người dùng; báo thất bại và giữ nguyên nội dung đang sửa.
- Nội dung Office có wrapper riêng, dùng chung office-content.css ở editor và viewer. Bảng nguồn không bị tự thêm viền xám; bảng rộng cuộn trong vùng nội dung, không đè sidebar. Viền inline và ô không viền được giữ riêng.
- DOCX import giữ font mặc định và kích thước trang/lề từ OOXML, CSS đoạn của Writer và nét kẻ ShapeN trong ô bảng. Mẫu khách hàng có hai bảng không viền, hai nét kẻ được Writer raster hóa và một ảnh đệm 1×1.
- DOCX export dùng lại lề/kích thước nguồn khi có metadata, không tự chèn tiêu đề ứng dụng vào nội dung Office và không tạo trang trắng do các đoạn rỗng cuối tài liệu.

Các file bổ sung/sửa thêm: app/Uploads/OfficeHtmlStyles.php, WordImportService.php, WordExportService.php, WordExportAppearance.php, WordExportLayout.php; public/dist/office-content.css; resources/views/layouts/base.blade.php và word-paste.js.

`tests/word-paste/check-customer-fidelity.cjs` nhập file mẫu thật, kiểm tra edit → lưu → xem → xuất và các ca clipboard mô phỏng: Google Docs có spacer, CSS font/căn lề, viền từng cạnh, bảng rộng và hai ảnh trong một lần dán. `check-roundtrip.py` đối chiếu text không kể whitespace, cỡ font 11/12/13/14pt, chữ nghiêng, hai ảnh nét kẻ, không thêm tiêu đề ứng dụng và không có trang trắng sau render. Các bài service và export appearance cũ vẫn phải qua.

Đã dùng Times New Roman có sẵn trên máy Windows này cho bộ render trong container thử nghiệm. Font không được đưa vào repository hoặc phục vụ qua CDN; server LAN thực cần có bộ font được phép sử dụng tương ứng. Khi recreate container hiện tại, cần cài lại font hoặc cung cấp font qua cơ chế triển khai của môi trường đó.

Các page/đoạn dán cũ đã mất CSS hoặc thay ảnh bằng placeholder không có đủ dữ liệu để tự khôi phục; cần dán hoặc import lại từ nguồn. Bản đang chạy đã được cập nhật trực tiếp để kiểm tra. Trước khi recreate cần build lại image từ source mới.

`tests/word-paste/check-browser.cjs` chạy bằng Playwright/Chrome đã có trong bộ công cụ kiểm tra, không thêm dependency vào dự án. Ví dụ PowerShell với môi trường đã có playwright:

```powershell
$env:NODE_PATH='C:/Users/Vissoft_PC/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules'
node tests/word-paste/check-browser.cjs
```

Kiểm tra clipboard mô phỏng HTML+RTF, upload thật qua HTTP, lưu và mở lại thật: văn bản tiếng Việt, bảng, IV/V, ii lồng nhau, ảnh bitmap, ảnh đơn, icon WMF, lỗi thiếu bytes, lỗi upload 403 và tài nguyên không gọi Internet. Kết quả/screenshot nằm ở outputs/word-paste bên ngoài thư mục bookstack-lab. Test giữ một sách/trang mẫu cho người dùng xem.

Đây là bản thử nghiệm thao tác dán, chưa có cam kết độ tương đương Google Docs cho mọi tài liệu. Cần thử trực tiếp Ctrl+C từ Word trên Windows với mẫu khách hàng ở Chrome/Edge. Trình duyệt có thể không cung cấp tất cả định dạng clipboard; nhóm shape, SmartArt, OLE, ảnh liên kết ngoài và bố cục nổi chưa được bảo đảm. RTF-only không có HTML giữ chữ thuần và ảnh nhưng không bảo đảm vị trí tương đối. Nếu không có bytes ảnh thì ứng dụng web không thể đọc trực tiếp file:// trên máy người dùng. Icon EMF đã có đường chuyển nhưng cần bổ sung mẫu EMF thực để nghiệm thu; kiểm thử vector tự động hiện dùng WMF.
