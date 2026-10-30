# Lưu ý tương thích trình duyệt khi xem PDF trên Vlex

Ghi nhận: **28/10/2026**, múi giờ Asia/Saigon. Áp dụng cho bản PDF chỉ xem/lưu nguyên file; tham chiếu [bảng kê tích hợp](pdf-import-export-changes.md) và [manifest mã nguồn](pdf-import-export-manifest.json).

## Kết luận cần giữ trong tài liệu bàn giao

**Chức năng preview PDF có thể không đáp ứng tất cả trình duyệt, đặc biệt trình duyệt quá cũ, WebView hoặc trình duyệt đã tắt PDF viewer. Không cam kết hiển thị đồng nhất chỉ vì upload/export thành công.** Cần thống nhất danh sách và phiên bản trình duyệt hỗ trợ, sau đó kiểm thử trên máy người dùng/Vlex staging thật.

Hiện ứng dụng nhúng URL file PDF bằng iframe và dùng PDF viewer có sẵn của trình duyệt. Không có PDF.js, OCR hoặc bộ render PDF của ứng dụng. Khả năng xem PDF trực tiếp là tính năng của trình duyệt; MDN mô tả tín hiệu `navigator.pdfViewerEnabled` để nhận biết hỗ trợ xem PDF inline. Bản thân tín hiệu này cũng có giới hạn tương thích, nên không được coi thuộc tính bị thiếu trên browser cũ là bằng chứng chắc chắn không xem được. [MDN: pdfViewerEnabled](https://developer.mozilla.org/en-US/docs/Web/API/Navigator/pdfViewerEnabled)

## Những trường hợp có thể khác hoặc không hoạt động

| Trường hợp | Ảnh hưởng cần kiểm tra | Hướng xử lý hiện có |
| --- | --- | --- |
| Browser cũ/không có PDF viewer, viewer bị tắt hoặc chính sách buộc tải xuống | Khung trống, tải file thay vì xem, hoặc không mở được trong iframe. | Mở PDF ở tab mới; nếu vẫn không xem được thì tải file gốc và mở bằng phần mềm PDF đã được đơn vị cho phép. |
| WebView/trình duyệt tích hợp trong phần mềm | Không suy ra khả năng PDF từ việc trang HTML hiển thị tốt. Có thể chỉ thấy khung PDF trống. | Kiểm thử riêng loại WebView; dùng trình duyệt được hỗ trợ hoặc tải file. |
| Viewer khác nhau hoặc phiên bản khác nhau | Vị trí toolbar, menu, in, zoom, tìm kiếm và cách hiển thị có thể khác. | Chốt viewer/browser mục tiêu; không cam kết giao diện đồng nhất ở bản hiện tại. |
| Thiết lập đóng menu bên cạnh | Viewer có thể bỏ qua một phần hoặc toàn bộ URL fragment; không bảo đảm menu mặc định đóng trên mọi browser. | Cấu hình iframe hiện tại `#toolbar=1&navpanes=0&pagemode=none`; yêu cầu nghiệp vụ là giữ toolbar và mặc định đóng menu, không phải vô hiệu hóa thao tác của người xem. |
| Nút “Mở PDF trong tab mới” | URL không mang fragment của iframe nên viewer có thể mở theo thiết lập mặc định của chính nó. | Kiểm tra riêng iframe và tab mới; không nhầm hai chế độ là cùng cấu hình menu. |
| PDF có mật khẩu | Viewer có thể yêu cầu mật khẩu hoặc không hỗ trợ mở ngay. | File được lưu/tải nguyên vẹn, không giải mã trên server; mở bằng viewer phù hợp. |
| PDF lớn, scan nhiều trang, font/ảnh/đồ họa phức tạp | Có thể tải/render chậm hoặc tốn bộ nhớ trên máy cũ dù dưới 20 MB. | Thử file đại diện trên cấu hình máy thấp nhất thực tế, đo thời gian mở và cuộn/zoom. |
| PDF hỏng, không hoàn chỉnh hoặc có tính năng đặc thù | Upload qua kiểm tra cơ bản không đồng nghĩa viewer đọc được toàn bộ file, form hoặc chữ ký. | Kiểm tra bằng phần mềm PDF và tài liệu đại diện; không tự sửa/ghi lại file gốc. |
| Reverse proxy, CSP, frame policy, cookie hoặc domain đăng nhập khác lab | Bị chặn nhúng, mất phiên đăng nhập trong khung hoặc lỗi tải PDF. | Kiểm tra header và cấu hình ở staging thật; giữ quyền truy cập, không chuyển file sang public để khắc phục. |

Ẩn menu chỉ là trạng thái giao diện được đề nghị cho viewer, **không phải biện pháp bảo vệ chống tải/in/sao chép**. Toolbar vẫn để người dùng thao tác; file gốc ở server không bị sửa bởi các thao tác ghi chú/lưu bản khác của viewer.

## Phân biệt khả năng preview với tính toàn vẹn file

- Preview hoạt động hay không phụ thuộc viewer và môi trường. Cùng bytes không có nghĩa mọi viewer hiển thị mọi tính năng giống nhau.
- Web export từng trang PDF trả bytes file đã nhập, không tạo lại từ HTML. Đã kiểm tra SHA-256 preview/export trùng nguồn ở lab cho PDF chữ/bảng/ảnh, scan, trộn chữ/scan, mã hóa và 51 trang.
- File tải xuống vẫn cần phần mềm phù hợp để mở; nếu file nguồn đã lỗi thì download nguyên vẹn cũng giữ lỗi nguồn.
- Không dùng kiểm tra chữ trích xuất hoặc chỉ HTTP 200 để kết luận bố cục, toolbar và menu đã hiển thị đúng.

## Trạng thái xác minh hiện tại

Đã xác nhận khung iframe, nút mở/tải, khóa sửa và response PDF đúng bytes trong lab. Khi kiểm tra bằng trình duyệt tích hợp của công cụ, khung PDF trống dù endpoint trả file; **chưa xác định đây là hạn chế viewer của trình duyệt đó hay yếu tố cấu hình khác**, và chưa có xác minh trực quan thành công trên Chrome/Edge thực tế. Không ghi nhận là đã hỗ trợ toàn bộ Chrome/Edge/Firefox hoặc mọi browser cũ.

Tham số cuối trong iframe đã lưu trong `source/BookStack-21.11.1/resources/views/pages/show.blade.php`: giữ toolbar, đề nghị đóng menu. Chưa có kết quả kiểm thử độc lập trên mọi viewer cho các tham số này.

## Bảng cần điền trước khi áp dụng Vlex thật

Ghi **phiên bản cụ thể**, không chỉ tên trình duyệt. Các dòng dưới đây là kế hoạch kiểm thử, chưa phải danh sách đã hỗ trợ.

| Máy/OS | Browser + phiên bản/chính sách PDF | File mẫu | Preview đủ trang, bảng/ảnh/font | Menu đóng, toolbar còn | Mở tab mới | Download trùng SHA-256 | Kết quả/ngày |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Máy người dùng phổ biến | Chưa xác định | PDF chữ/bảng/ảnh | Chưa thử | Chưa thử | Chưa thử | Chưa thử trên Vlex thật | Chờ kiểm thử |
| Máy có browser cũ nhất cần hỗ trợ | Chưa xác định | Cùng file mẫu | Chưa thử | Chưa thử | Chưa thử | Chưa thử trên Vlex thật | Chờ kiểm thử |
| Máy do đơn vị quản lý chính sách | Chưa xác định | Scan nhiều trang | Chưa thử | Chưa thử | Chưa thử | Chưa thử trên Vlex thật | Chờ kiểm thử |
| WebView/phần mềm nội bộ nếu sử dụng | Chưa xác định | PDF đại diện | Chưa thử | Chưa thử | Chưa thử | Chưa thử trên Vlex thật | Chờ kiểm thử |

Kiểm thử thêm PDF có mật khẩu, file gần 20 MB và file có form/chữ ký nếu nghiệp vụ thực dùng. Kiểm tra người bị thu hồi quyền không xem/tải được file; kiểm thử layout bằng mắt ở mức zoom thực tế, không chỉ xem tên file.

## Nếu cần hỗ trợ rộng hơn

Có thể đánh giá tích hợp PDF.js để ứng dụng chủ động hơn về giao diện và trạng thái menu. **Đây là phương án, chưa triển khai.** Mozilla có bản legacy cho các browser cũ hơn, nhưng vẫn quy định giới hạn hỗ trợ; không có nghĩa tương thích mọi browser cũ. Không tự chọn một bản PDF.js quá cũ chỉ để chạy trên một máy mà bỏ qua bảo trì và kiểm thử. [Mozilla PDF.js FAQ](https://github.com/mozilla/pdf.js/wiki/Frequently-Asked-Questions)

Nếu phải bổ sung viewer, cần đối chiếu yêu cầu không đổi dependency của Vlex, cách lưu asset nội bộ cho mạng hạn chế Internet, CSP/worker và phiên bản browser tối thiểu. Download vẫn phải đọc file nguồn như hiện tại để giữ nguyên bytes. Nếu chưa có yêu cầu viewer thống nhất, duy trì phương án mở/tải PDF gốc và công bố danh sách browser đã kiểm thử thực tế.
