# Dán ảnh raster thật từ Word qua clipboard Windows

**Đã ngừng dùng theo yêu cầu ngày 29/10/2026:** người dùng yêu cầu chỉ Ctrl+A/Ctrl+C/Ctrl+V trên web, không ứng dụng ngoài. Công cụ đã được đóng; lời gọi loopback đã bị gỡ khỏi word-paste.js. Các nội dung bên dưới chỉ lưu lịch sử thử nghiệm, không phải hướng dẫn triển khai hiện tại. Những bài kiểm thử cầu nối chỉ chứng minh phương án đã bị loại, không chứng minh đạt yêu cầu dán thuần web.

Ngày 29/10/2026. Đây là bản thử phía máy người dùng, không thay đổi Composer/package lock, không cài extension hoặc tiến trình theo dõi clipboard. Có chế độ thủ công và chế độ tự động qua một cổng loopback chỉ được mở khi người dùng bật.

## Nguyên nhân và giới hạn

Trang web chỉ nhận được các dạng clipboard mà trình duyệt chuyển giao. Một thẻ img trỏ tới file:///.../Temp/msohtmlclip.../clip_image001.png không chứa bytes ảnh. Máy chủ LAN không đọc được file đó trên máy người dùng. Việc đổi hoặc chỉnh TinyMCE không tự cấp quyền đọc file máy khách.

Hai file GIF 80×4 và 227×5 trong log đầu là hai tham chiếu ảnh rất mỏng của mẫu nét kẻ, chưa đại diện cho ảnh chụp PNG/JPEG. Không dùng chuyển vector làm giải pháp cho ảnh chụp.

## Phương án thử đã dựng

`scripts/prepare-word-clipboard.ps1` đọc trực tiếp clipboard Windows theo thao tác người dùng. Với ảnh đơn, lấy nguyên stream PNG từ Windows; với nội dung Word có chữ và ảnh, giữ HTML/CSS/text, đọc các file ảnh cục bộ được tham chiếu trong thư mục tạm rồi thay src bằng data URI. Ảnh PNG/JPEG/GIF giữ nguyên bytes, các bitmap khác có thể chuyển sang PNG. CF_HTML được tạo lại với offset UTF-8 đúng để giữ tiếng Việt.

Với vùng chọn nhiều chữ/ảnh, không yêu cầu định dạng PNG tổng hợp của clipboard vì Word có thể phải render cả selection lớn. Chỉ đọc từng ảnh tạm được tham chiếu; bitmap native chỉ dùng cho ảnh đơn khi phù hợp, không dùng ảnh tổng của chữ + ảnh thay cho ảnh gốc.

Khi Ctrl+V vào VLEX, bộ xử lý tải ảnh lên gallery trước khi chèn vào editor; nội dung lưu chỉ dùng URL ảnh trong kho nội bộ. Chế độ tự động trả map source → data URI và giữ nguyên HTML mà trình duyệt đã nhận, tránh thay cả đoạn bằng ngữ cảnh nằm ngoài StartFragment của CF_HTML.

## Chạy thử

Máy thử nghiệm có PowerShell 7 và WinForms. Trong thư mục bookstack-lab:

```powershell
pwsh -NoProfile -STA -File scripts/prepare-word-clipboard.ps1
```

Nếu pwsh không có trong PATH, môi trường Codex trên máy này có bản ở `C:/Users/Vissoft_PC/.cache/codex-runtimes/codex-primary-runtime/dependencies/native/powershell/pwsh.exe`.

Chế độ tự động (không cần Prepare cho từng lần copy):

1. Mở cửa sổ Word → VLEX clipboard, kiểm tra origin là `http://localhost:8080` hoặc origin gốc thực của VLEX trong LAN (không có /books hoặc đường dẫn khác).
2. Người dùng bật **Enable automatic Word image paste**. Mặc định tắt; việc bật cho phép trang VLEX đó yêu cầu đọc các ảnh thuộc selection Word đang nằm trong clipboard.
3. Giữ cửa sổ công cụ mở. Trong Word Ctrl+A/Ctrl+C hoặc copy một phần, đặt con trỏ trong editor VLEX rồi Ctrl+V. Không cần Prepare mỗi lần.
4. Chờ tải ảnh và decode ảnh trong editor hoàn tất rồi lưu.

Chế độ thủ công vẫn có: copy trong Word → nhấn Prepare clipboard → Ctrl+V trong VLEX.

Bản thử chỉ thay clipboard khi nhấn Prepare hoặc dùng -Prepare. Chế độ tự động không thay clipboard: chỉ đọc theo request dán. Không thiết lập autostart, không đọc clipboard định kỳ và không sửa chính sách thực thi PowerShell. Windows PowerShell 5 trên máy này chặn chạy script; đã dùng PowerShell 7 với chính sách RemoteSigned sẵn có. Không dùng ExecutionPolicy Bypass.

Khi bật tự động, công cụ bind `127.0.0.1:32145`, không bind LAN. Chỉ nhận JSON POST /prepare và preflight từ đúng Origin được chọn. Danh sách ảnh trong request phải là một tập con các ảnh file:// có trong clipboard Windows hiện tại trước khi đọc file. Chỉ trả dữ liệu các ảnh được yêu cầu, không trả toàn bộ text Word. Tắt checkbox hoặc đóng cửa sổ sẽ đóng cổng. Trình duyệt/chính sách LAN có thể yêu cầu người dùng cho phép kết nối local; bản localhost hiện tại đã được kiểm tra CORS/preflight, chưa nghiệm thu cấu hình trình duyệt của khách hàng.

Chế độ kiểm tra `-ExportHtml <path>` chỉ xuất HTML đã nhúng ảnh vào file, không thay clipboard. `-Prepare` là chế độ dòng lệnh thay clipboard theo yêu cầu.

## Giới hạn truy cập

Chỉ đọc file ảnh được tham chiếu dưới thư mục tạm của người dùng; từ chối UNC/network, file ngoài thư mục tạm, reparse/symlink và file không phải ảnh. Kiểm tra decode ảnh, dung lượng 20 MB/ảnh, tổng 30 MB và giới hạn pixel. Nếu thiếu một trong nhiều ảnh thì báo lỗi và giữ nguyên clipboard; không ghép đoán các ảnh thiếu từ một bitmap tổng.

Chế độ tự động cho phép Ctrl+V trực tiếp sau khi bật cầu nối một lần trên máy trạm. Vẫn cần cung cấp công cụ Windows trên từng máy và thống nhất việc đóng gói/khởi động cho môi trường khách hàng; chỉ sửa mã web trên máy chủ không thể cấp quyền đọc file tạm của máy khách.

## Bằng chứng kiểm tra

Log ảnh thật do người dùng cung cấp có 15 thẻ img, tên clip_image002.jpg đến clip_image030.gif, nhưng rtfCharacters=0, không có item kind=file và không có bytes ảnh. Đây là bằng chứng trực tiếp của trường hợp cần lấy ảnh trên máy Windows thay vì chỉ xử lý HTML/RTF trong trình duyệt.

- Đã đọc trực tiếp từ clipboard Windows một ảnh PNG 845×661, 319.043 byte; không dùng vector hoặc ảnh giả để thay thế.
- Chế độ ExportHtml không thay clipboard. HTML đó được đưa qua bài kiểm tra editor bằng ClipboardEvent mô phỏng; gallery upload, lưu trang, mở lại và tải ảnh đều là HTTP thật. Bytes ảnh lưu trùng hoàn toàn 319.043 byte gốc.
- `tests/word-paste/check-native-bridge.ps1`: CF_HTML tiếng Việt/UTF-8, đọc ảnh tạm, chữ + PNG/JPEG, ảnh lặp, CSS/thứ tự và chặn đường dẫn ngoài phạm vi.
- `tests/word-paste/check-native-upload.cjs`: PNG lấy từ clipboard Windows → HTML nhúng ảnh → gallery → lưu/mở lại; kết quả ở outputs/word-paste/native-raster/native-upload-result.json.
- Chưa nghiệm thu thao tác Ctrl+C cả chữ và nhiều ảnh từ Word → nút Prepare → Ctrl+V thật trên một máy khách khác; UI automation Word trên máy thử không ổn định. Không dùng kết quả ClipboardEvent mô phỏng để tuyên bố đã nghiệm thu thao tác đó.
- `serve-native-bridge-fixture.ps1` và `check-full-word-paste.cjs` đã chạy cùng HTTP bridge thật bằng PowerShell/WinForms với data object kiểu Word và 15 file JPEG tạm riêng biệt, mô phỏng đúng trường hợp HTML có file:// nhưng RTF rỗng và không có clipboard files. Kiểm tra toàn chuỗi Ctrl+V của TinyMCE, chèn giữa nội dung, gallery upload, SHA-256 của cả 15 ảnh theo thứ tự và lưu/mở lại đều qua. Data object này là fixture có kiểm soát, không phải thao tác Ctrl+A qua UI Microsoft Word của khách hàng.

## Vị trí chèn sau khi tải ảnh

Đã tái hiện lỗi ở bài test có keydown → paste → keyup: plugin paste của TinyMCE 4 tạo #mcepastebin và chuyển selection vào đó khi nhấn Ctrl+V. Khi keyup xóa node tạm, Range được lưu trong node bị dồn về cuối body. Bài test paste-only cũ không kích hoạt được lỗi này.

Luồng mới chặn handler keydown Ctrl+V/Shift+Insert của TinyMCE tại capture trên window, giữ default paste của trình duyệt và chụp native DOM selection trước khi có pastebin. Ctrl+Shift+V tiếp tục dùng luồng plain text của TinyMCE. Khi upload hoàn tất, khôi phục editability/focus/Range và chèn trong một undo transaction; chờ ảnh thực trong editor decode xong trước khi đánh dấu hoàn tất.

`tests/word-paste/check-paste-position.cjs` kiểm tra keydown/paste/keyup khi chèn giữa đoạn, thay vùng được chọn và chèn trong ô bảng. Bài kiểm tra cố tình chuyển selection về cuối body trong lúc upload đang chờ; cả ba ca vẫn chèn tại Range gốc. Thứ tự ảnh/đoạn bên trong clipboard được giữ theo HTML; vị trí ảnh nổi theo tọa độ tuyệt đối của Word vẫn nằm ngoài phạm vi bảo đảm.
