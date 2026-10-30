param([string]$SnapshotDate = '2026-10-30')
$ErrorActionPreference = 'Stop'
$repositoryRoot = Split-Path $PSScriptRoot -Parent

# Explicit inventory: git status alone also contains unrelated Word/PDF/search work.
# path | group | origin | purpose | production integration decision
$inventory = @'
source/BookStack-21.11.1/app/Config/vlex-storage.php|Tiếp nhận file|new-lab|Danh sách DOC/DOCX/XLS/XLSX/PDF và giới hạn mặc định 20 MiB qua VLEX_STORAGE_MAX_MB.|Ghép vào cấu hình tiếp nhận hiện có; xác nhận giới hạn nghiệp vụ và PHP/proxy.
source/BookStack-21.11.1/app/Uploads/StorageFileInspector.php|Tiếp nhận file|new-lab|Kiểm upload/đuôi/dung lượng/rỗng; OLE, ZIP CRC/XML; mở thử Office; kiểm và dựng PDF; cảnh báo tương thích.|Ứng viên ghép phần kiểm tra; đối chiếu UploadedFile, ZipArchive, DOM, mbstring, Process, công cụ hệ điều hành và các ngân sách hardcode.
source/BookStack-21.11.1/app/Entities/Models/AttachedFile.php|Tiếp nhận file|vlex-slot|Dùng vị trí model VLEX, trỏ bảng lab vlex_book_files; book_id, metadata, warnings dạng mảng; ẩn path khi serialize.|VLEX đã có model/schema thật: map tên bảng/cột/quan hệ, không tạo model thứ hai hoặc chép đè.
source/BookStack-21.11.1/app/Entities/Repos/AttachedFileRepo.php|Tiếp nhận file|vlex-slot|Lưu nguyên bytes trên disk private, hash SHA-256, dọn blob khi lỗi; move, xóa và truy vấn book đích có quyền.|Ghép vào repo VLEX; giữ backend storage, transaction và chính sách xóa hiện có.
source/BookStack-21.11.1/app/Http/Controllers/FileAttachedController.php|Tiếp nhận file|vlex-slot|Upload vào Book, kết quả từng file, download; GET màn move/tìm đích/xóa; POST move và DELETE có kiểm quyền.|Nối vào controller VLEX cùng vai trò; map route, ACL, request/response, CSRF và thông báo.
source/BookStack-21.11.1/app/View/LibraryMap.php|Hierarchy lab|vlex-slot|Ánh xạ shelf/book cha-con, breadcrumb/path; tìm tên shelf/book/ancestor; move đơn cha, gỡ pivot cũ và chặn vòng lặp.|VLEX đã có hierarchy: tái sử dụng quan hệ thật; chỉ ghép phần tìm đích/clear membership cần thiết.
source/BookStack-21.11.1/app/ViewComponents/HierarchyFolder.php|Hierarchy lab|vlex-slot|Gom book con, page/chapter và file trong Book; cung cấp path/breadcrumb cho giao diện và kết quả tìm đích.|Ghép với cây thư mục VLEX đang chạy; không tạo cây folder song song.
source/BookStack-21.11.1/app/Entities/Models/Book.php|Hierarchy lab|modified-existing|Thêm parentBook, childBooks, uploadedFiles và getParent cho book lồng.|Chủ yếu để lab mô phỏng VLEX; đối chiếu tên quan hệ thật, tránh thêm quan hệ trùng.
source/BookStack-21.11.1/app/Entities/Models/Bookshelf.php|Hierarchy lab|modified-existing|visibleBooks chỉ lấy root; appendBook chuyển về một shelf qua LibraryMap.|Không thay quan hệ VLEX đã sửa đúng; đối chiếu việc gỡ mọi membership cũ khi move.
source/BookStack-21.11.1/app/Entities/Repos/BookRepo.php|Hierarchy lab|modified-existing|Danh sách Books gốc chỉ lấy book không có parent_book_id.|Điểm mô phỏng lab; VLEX thật có thể đã lọc root bằng schema khác.
source/BookStack-21.11.1/app/Entities/Repos/BookshelfRepo.php|Hierarchy lab|modified-existing|Cập nhật danh sách Book trong shelf bằng move, kiểm quyền và transaction; không nhân bản membership.|So sánh với luồng move/sắp xếp thư viện hiện có; không làm mất ACL hoặc quy tắc thứ tự VLEX.
source/BookStack-21.11.1/app/Http/Controllers/BookController.php|Hierarchy lab|modified-existing|Tạo book con, màn chuyển book; hiển thị book con/page/file trong Book; hành động upload/move.|Ghép điểm hiển thị file/action vào controller thật, giữ luồng hierarchy VLEX đang có.
source/BookStack-21.11.1/app/Entities/BreadcrumbsViewComposer.php|Hierarchy lab|modified-existing|Dùng HierarchyFolder để dựng breadcrumb theo cha thực tế.|Đối chiếu composer/breadcrumb VLEX, đặc biệt sau move khác shelf.
source/BookStack-21.11.1/app/Entities/Tools/SiblingFetcher.php|Hierarchy lab|modified-existing|Dropdown sibling theo book cha hoặc shelf thật, không dùng shelf cũ trong session.|Đối chiếu SiblingFinder alias trong cây VLEX; không chép đè nếu VLEX đã xử lý đúng.
source/BookStack-21.11.1/app/Entities/Tools/TrashCan.php|Hierarchy lab|modified-existing|Trash/restore book con; không khôi phục book đã xóa riêng; purge đệ quy và xóa blob file.|Phải map theo semantics recycle bin/xóa VLEX thật; tránh thay bằng xóa vĩnh viễn ngoài ý muốn.
source/BookStack-21.11.1/routes/web.php|Route và giao diện|shared-existing|Route upload trong Book, download file, màn move/tìm đích/xóa riêng; route book con/move folder; gỡ route kho riêng.|File còn chứa thay đổi Word/Excel/PDF/dán Word; chỉ ghép các route tương ứng, giữ prefix và middleware thật.
source/BookStack-21.11.1/resources/views/books/show.blade.php|Route và giao diện|shared-existing|Gom book con, Page và bảng file; thêm action upload/book con/move; nạp CSS file có hash.|File dùng chung với menu nhập Word/Excel/PDF; ghép từng vùng vào layout VLEX, không chép đè toàn bộ.
source/BookStack-21.11.1/resources/views/books/create-child.blade.php|Route và giao diện|new-lab|Form tạo Book con, dùng form Book hiện có và breadcrumb hierarchy.|Chỉ cần cho lab nếu VLEX đã có màn tạo folder/book con.
source/BookStack-21.11.1/resources/views/books/move-folder.blade.php|Route và giao diện|new-lab|Màn chuyển cả Book/subtree tới Book hoặc Shelf; không phải màn chuyển file.|Chủ yếu mô phỏng lab; giữ màn move Book có sẵn của VLEX thật.
source/BookStack-21.11.1/resources/views/books/upload-files.blade.php|Route và giao diện|new-lab|Form nhiều file trong Book, CSRF, kết quả riêng từng file; nạp CSS/JS có hash.|Map vào form upload VLEX hiện có; giữ contract và giới hạn request phía server.
source/BookStack-21.11.1/resources/views/books/parts/upload-result.blade.php|Route và giao diện|new-lab|Kết quả fallback Blade: tên/loại file, dấu xanh/đỏ; chỉ thất bại mới hiện lý do.|Đồng bộ với renderer JS và quy tắc lỗi thật; mọi tên file/lý do đều escape.
source/BookStack-21.11.1/resources/views/books/parts/uploaded-files.blade.php|Route và giao diện|new-lab|Danh sách tên/loại/dung lượng/trạng thái; link download, màn chuyển và màn xóa; không có form dropdown.|Ghép vào danh sách file đang có của Book VLEX, giữ quyền từng action.
source/BookStack-21.11.1/resources/views/files/move.blade.php|Route và giao diện|new-lab|Màn riêng chuyển file, vị trí hiện tại, tìm shelf/book, chọn book đích, Hủy/Chuyển.|Dùng bố cục và entities.selector hiện có; không coi đây là bản view VLEX gốc đã nhận.
source/BookStack-21.11.1/resources/views/files/delete.blade.php|Route và giao diện|new-lab|Màn xác nhận xóa riêng: tên file, vị trí, Hủy/Xác nhận; GET không xóa.|Ghép vào màn xóa VLEX sẵn có, xác minh permanent delete hay recycle bin.
source/BookStack-21.11.1/resources/views/files/parts/move-destinations.blade.php|Route và giao diện|new-lab|Kết quả tìm đích dùng list-item-basic native; hiện đủ đường dẫn shelf/book.|Giữ data-entity-type/id và entity-list-item-name cho bộ chọn; path lấy từ hierarchy thật.
source/BookStack-21.11.1/resources/views/entities/selector.blade.php|Frontend dùng chung|modified-existing|Bộ chọn hiện có nhận thêm selectorSearchUrl và nhãn tìm kiếm có aria-label.|Tham số tùy chọn; không làm đổi mặc định các màn chọn Page/Chapter khác.
source/BookStack-21.11.1/resources/js/components/entity-selector.js|Frontend dùng chung|modified-existing|Ưu tiên URL tìm kiếm tùy chọn; mặc định vẫn dùng ajax/search/entities.|Ghép thay đổi nhỏ vào component tương ứng, dùng toolchain/version frontend của VLEX thật.
source/BookStack-21.11.1/public/dist/app.js|Frontend dùng chung|shared-existing|Bundle chạy thực tế, gồm thay đổi optional searchUrl cho entity-selector.|Còn chứa sửa tìm kiếm trước đó; không chép đè bundle production. Ghép source rồi build theo VLEX; lab đã cập nhật cả source và bundle.
source/BookStack-21.11.1/public/dist/storage-upload.js|Frontend dùng chung|new-lab|Preflight, upload tuần tự từng file; render tên/loại/trạng thái xanh đỏ; chỉ lỗi mới hiện lý do; dùng textContent.|So sánh cơ chế upload/CSRF/response VLEX; không tự ghép giới hạn import Excel sang file lưu trữ.
source/BookStack-21.11.1/public/dist/storage-upload.css|Frontend dùng chung|new-lab|CSS scoped cho kết quả upload, tên/loại file và bảng file; responsive và dark mode.|Đối chiếu class/theme/layout VLEX để tránh ảnh hưởng CSS chung.
source/BookStack-21.11.1/resources/views/layouts/base.blade.php|Frontend dùng chung|shared-existing|Fingerprint app.js để tải bản selector mới; giữ CSP nonce.|File còn nạp asset của tìm kiếm/Office/dán Word trước đó; ghép riêng cache/CSP cần thiết.
source/BookStack-21.11.1/database/migrations/2026_10_29_110000_add_book_folders_and_uploaded_files.php|Schema lab|new-lab|Thêm books.parent_book_id, vlex_book_files và vlex_book_hierarchy_lock.|Schema mô phỏng. Không chạy nguyên migration trên VLEX đã có hierarchy/file table; map schema và lập migration riêng nếu thực sự thiếu.
source/BookStack-21.11.1/database/migrations/2026_10_29_090000_create_vlex_storage.php|Lịch sử đã ngừng|retired-lab|Tạo vlex_storage_folders/files của bản kho riêng đã gỡ; giữ lịch sử migration đã chạy trong lab.|Không triển khai vào VLEX thật; không kích hoạt lại kho riêng hoặc tự xóa dữ liệu cũ.
Dockerfile|Môi trường|modified-existing|Bổ sung qpdf/poppler-utils để kiểm PDF; dùng LibreOffice và PHP/extensions của runtime lab.|Đối chiếu PATH, quyền chạy process/temp/font và công cụ được phép cài ở server VLEX; không dùng nguyên image lab làm yêu cầu production.
compose.yaml|Môi trường|shared-existing|Truyền VLEX_STORAGE_MAX_MB vào app container; tham chiếu volume/private storage và cổng lab.|Chỉ tham chiếu lab; giữ config/secrets/deployment production. File còn có thay đổi của tác vụ khác.
tests/storage/check-service.php|Kiểm thử|new-lab|File hợp lệ/giả/hỏng/CRC/XML/limit; hierarchy/move/hash; tìm đích, quyền, trash/restore/purge; rollback DB.|Chỉ chạy trong lab; dùng web user www-data và fixture tổng hợp, đối chiếu schema/auth trước khi tái dùng.
tests/storage/check-http.ps1|Kiểm thử|new-lab|Tạo Shelf/Book/Page/file, kiểm màn move/delete, tìm theo shelf/book, cross-shelf, hash, guest và xóa; dọn dữ liệu thử.|Chỉ chạy lab: script chứa tài khoản lab và tạo/xóa dữ liệu. Không chạy nguyên script trên VLEX production.
tests/check-vlex-structure.php|Kiểm thử|modified-existing|Xác minh 5 vị trí đã triển khai, alias native, scaffold còn chờ và migration stub chưa kích hoạt.|Kiểm cấu trúc lab, không phải test chức năng production; cần vlex-file-map.json đúng bản.
docs/vlex-file-map.md|Bảng kê và tài liệu|modified-existing|Bảng đường dẫn người dùng cung cấp; đánh dấu 5 vị trí implemented-lab, các vị trí còn chờ.|Dùng nhận diện chỗ ghép, không xem implemented-lab là đã nhận mã gốc.
docs/vlex-file-map.json|Bảng kê và tài liệu|modified-existing|Bản máy đọc của bảng đường dẫn/trạng thái; đầu vào test cấu trúc.|Giữ phân biệt scaffold, alias, implemented-lab và migration chưa biết schema.
docs/vlex-local-development.md|Bảng kê và tài liệu|modified-existing|Ghi nhận cây VLEX đã xác nhận và cập nhật trạng thái triển khai lab.|Phần nền ngày 22/10 là lịch sử, ưu tiên ghi chú cập nhật và hồ sơ mới.
docs/storage-files.md|Bảng kê và tài liệu|new-lab|Mô tả nghiệp vụ, quan hệ, quyền, kiểm file, luồng move/delete và cách kiểm thử hiện tại.|Đọc cùng hồ sơ bàn giao và manifest để đối chiếu hành vi với VLEX thật.
README.md|Bảng kê và tài liệu|shared-existing|Điểm vào hướng dẫn lab, mô tả cây Book/file và liên kết hồ sơ.|Còn có ghi chú Word/Excel/PDF/dán Word; không xem toàn bộ README là phạm vi riêng tác vụ này.
'@

Push-Location $repositoryRoot
try {
    $head = (git rev-parse HEAD).Trim()
    $branch = (git branch --show-current).Trim()
    $files = foreach ($line in ($inventory -split '\r?\n')) {
        if (!$line.Trim()) { continue }
        $parts = $line.Split('|')
        if ($parts.Count -ne 5) { throw "Invalid inventory row: $line" }
        $relativePath = $parts[0]
        $fullPath = Join-Path $repositoryRoot $relativePath
        if (!(Test-Path -LiteralPath $fullPath -PathType Leaf)) { throw "Missing inventoried file: $relativePath" }
        $status = (git status --porcelain --untracked-files=all -- $relativePath) -join ' '
        [pscustomobject][ordered]@{
            path = $relativePath
            group = $parts[1]
            origin = $parts[2]
            purpose = $parts[3]
            production_integration = $parts[4]
            sha256 = (Get-FileHash -LiteralPath $fullPath -Algorithm SHA256).Hash
            bytes = (Get-Item -LiteralPath $fullPath).Length
            git_status = $status
        }
    }
    $manifest = [ordered]@{
        snapshot_date = $SnapshotDate
        timezone = 'Asia/Saigon'
        feature = 'VLEX Book raw-file upload, validation, move/search/delete screens and lab hierarchy'
        application_root = 'source/BookStack-21.11.1'
        branch = $branch
        base_head = $head
        source_state = 'Working-tree snapshot with uncommitted/new files; HEAD does not contain the whole feature.'
        hash_scope = 'Whole current files, including unrelated changes in shared files. Hashes identify source bytes, not uploaded user documents.'
        compatibility_status = 'Verified on local BookStack 21.11.1/PHP 8.0.30/Laravel 8.70.2/MySQL 8.0.36. Not yet verified against real VLEX source/schema/deployment or every Microsoft Office version.'
        generator = 'scripts/write-file-upload-vlex-handover.ps1'
        file_count = @($files).Count
        files = @($files)
        excluded_removed_paths = @(
            'app/Http/Controllers/StorageController.php', 'app/Uploads/StorageFile.php',
            'app/Uploads/StorageFolder.php', 'resources/views/storage/index.blade.php',
            'app/Entities/Tools/BookHierarchy.php'
        )
        exclusions_note = 'Retired standalone storage code was removed; temporary BookHierarchy implementation was moved into the existing VLEX LibraryMap slot. No deployable source at those paths.'
        self_hash_note = 'The manifest, generated handover and generator are not included in their own source hash list.'
    }
    $manifestPath = Join-Path $repositoryRoot 'docs/file-upload-vlex-manifest.json'
    [System.IO.File]::WriteAllText($manifestPath, ($manifest | ConvertTo-Json -Depth 10) + "`n")

    $intro = @'
# Hồ sơ tra soát file upload để ghép vào VLEX thật

Ngày chốt: **{{date}}**, Asia/Saigon. Đây là bảng kê cho phần file gốc trong Book, kiểm tra tiếp nhận, giao diện kết quả, màn chuyển/tìm đích và màn xóa riêng, cùng các sửa đổi hierarchy để mô phỏng trên lab.

## Mốc nguồn và cách đọc

- Gốc repo: `bookstack-lab`; gốc ứng dụng: `source/BookStack-21.11.1/`. Mọi đường dẫn trong bảng dưới tính từ gốc repo.
- Nhánh: `{{branch}}`. HEAD nền: `{{head}}`.
- **Có file mới/thay đổi chưa commit. HEAD trên không chứa toàn bộ tính năng.** Không dùng riêng checkout HEAD này làm bản bàn giao hoàn chỉnh.
- [Manifest SHA-256](file-upload-vlex-manifest.json) lưu tên, tác dụng, điểm ghép production, trạng thái Git, kích thước và hash từng file. Hash là của toàn bộ file hiện tại; file dùng chung có thể chứa cả sửa Word/Excel/PDF/tìm kiếm/dán Word từ các tác vụ trước.
- `vlex-slot`: dùng đúng vị trí trong cây file VLEX người dùng cung cấp. `new-lab`: file bổ sung trong lab. `modified-existing`: sửa file sẵn có. `shared-existing`: có thay đổi dùng chung, phải ghép từng vùng. `retired-lab`: lịch sử đã ngừng, không triển khai.
- VLEX thật **đã có** cấu trúc shelves → book lồng nhiều cấp → page + file và màn move/delete. Ưu tiên dùng implementation sẵn có. Không thay toàn bộ hierarchy, model, controller, ACL, view hoặc bundle bằng bản BookStack lab.
- Đã kiểm trên lab BookStack 21.11.1, PHP 8.0.30, Laravel 8.70.2, MySQL 8.0.36. Chưa có đầy đủ nội dung/schema/deployment VLEX gốc để xác nhận tương thích production.

## Bảng file và tác dụng

'@
    $document = $intro.Replace('{{date}}', $SnapshotDate).Replace('{{branch}}', $branch).Replace('{{head}}', $head)
    foreach ($group in ($files | Group-Object group)) {
        $document += "`n### $($group.Name)`n`n| File trong repo | Nguồn | Tác dụng | Khi ghép VLEX thật |`n| --- | --- | --- | --- |`n"
        foreach ($entry in $group.Group) {
            $document += '| [' + $entry.path + '](../' + $entry.path + ') | ' + $entry.origin + ' | ' + $entry.purpose + ' | ' + $entry.production_integration + " |`n"
        }
    }
    $details = @'

## Hợp đồng hành vi cần giữ

1. File gốc thuộc đúng một Book thật; Page và file có thể nằm cùng Book. Upload không tạo Page đại diện và không sửa bytes gốc.
2. Kết quả từng file: tên/loại, dấu xanh khi đã lưu, đỏ khi thất bại; chỉ thất bại mới hiện lý do. Metadata kiểm tra vẫn được giữ.
3. Chuyển mở màn riêng, tìm theo tên shelf/book/ancestor, hiện đủ đường dẫn. Có thể chuyển từ Book của shelf này sang Book của shelf khác. Chỉ Book có quyền xem/thêm file được chọn; Book nguồn không phải đích hợp lệ.
4. Move cập nhật chính book_id trong transaction; không copy blob hoặc giữ membership/pivot trỏ về Book cũ. ID, hash và bytes giữ nguyên. Không hiểu “gỡ dấu vết ở vị trí cũ” là xóa audit log hoặc backup.
5. Xóa mở màn xác nhận riêng. GET và Hủy không xóa dữ liệu; chỉ request DELETE có CSRF/quyền mới thực hiện. Lab xóa file vĩnh viễn; đối chiếu lại semantics xóa VLEX trước khi ghép.

## Route và request/response trong lab

| Method / đường dẫn | Vai trò / contract |
| --- | --- |
| GET /books/{bookSlug}/upload-files | Form upload theo Book nguồn. |
| POST /books/{bookSlug}/upload-files | `files[]`; JSON `results[]` gồm name, status stored/rejected, errors, warnings và id khi lưu được; HTML fallback dùng flash session. |
| GET /files/{id}/download | Tải nguyên bytes, Content-Disposition attachment; quyền theo Book hiện tại. |
| GET /files/{id}/move | Màn chuyển riêng dùng bộ chọn entities.selector. |
| GET /files/{id}/move/destinations | Tìm `term`, trả partial HTML; tối đa 30 Book, có data-entity-type/id và entity-list-item-name cho selector. |
| POST /files/{id}/move | UI gửi `entity_selection=book:id`; còn nhận book_id cho request tích hợp cũ. Kiểm lại quyền nguồn/đích, từ chối đích không phải Book/Book hiện tại. |
| GET /files/{id}/delete | Chỉ hiển thị xác nhận, tên file và vị trí hiện tại. |
| DELETE /files/{id} | Xóa file/blob và metadata sau kiểm quyền. |

Các route tạo/move cả Book trong lab chỉ mô phỏng hierarchy; xem routes/web.php và BookController. VLEX thật có thể dùng tên route, method và request khác: map contract, không bắt buộc đổi production theo lab.

## Các điểm phải đối chiếu trước khi ghép

| Hạng mục | Cần xác minh trên VLEX thật |
| --- | --- |
| Model/schema | Namespace/model AttachedFile, bảng file, khóa Book, tên cột parent, soft delete, warnings JSON/text, kiểu ID/index/FK. Không chạy migration lab khi quan hệ/bảng đã có. |
| Quyền | Map Book view, page-create, attachment-create-all và attachment-delete trong lab sang ACL nhóm/người dùng VLEX. Kiểm cả gọi trực tiếp endpoint tìm đích/download/move/delete, không chỉ ẩn nút. |
| Di chuyển | Giữ repo/transaction/khóa chống vòng lặp của VLEX. Gỡ tất cả membership cũ, cập nhật breadcrumb/cache/session/sibling theo cây thật; file target thuộc shelf khác vẫn được tìm. |
| Xóa/khôi phục | Quy tắc xóa file, Book, subtree, revision, audit và recycle bin. Bản TrashCan lab không phải đặc tả tự động thay thế chính sách VLEX. |
| Storage | Disk local_secure_attachments và prefix vlex-book-files/ chỉ là lựa chọn lab. Map tới local/private/S3 của VLEX, quyền worker, tên download, cleanup khi DB/storage lỗi; không để file private tải trực tiếp qua public URL. |
| Upload limit | Tạm 20 MiB/file, tối đa 20 file/request; JS gửi từng file để tránh tổng POST limit. Đối chiếu PHP upload_max_filesize, post_max_size, max_file_uploads và proxy. Không dùng limit import Excel 2 MiB/5 sheet cho file lưu trữ. |
| Runtime kiểm file | PATH tới libreoffice, qpdf, pdfinfo, pdftoppm; PHP zip/DOM/mbstring; quyền tạo/xóa temp, chạy Process, profile LibreOffice, font và thời gian xử lý. Composer/npm versions và lockfiles của lab không được đổi cho tính năng này. |
| Ngân sách kiểm tra | Hardcode trong StorageFileInspector: ZIP 10.000 thành phần/200 MiB giải nén; PDF 2.000 trang; 60 giây/công cụ. VLEX cần xác nhận/chỉnh theo dữ liệu thật. VLEX_STORAGE_MAX_MB chỉ đổi dung lượng file, không đổi các ngân sách này. |
| Frontend | Selector hiện có nhận URL tìm kiếm tùy chọn; không làm đổi màn Page/Chapter khác. Ghép source rồi build bundle theo toolchain VLEX, giữ escaping và data attributes; kiểm bàn phím, loading, không có kết quả, lỗi xác thực. |
| Asset/layout | app.js, base.blade.php và books/show.blade.php chứa thay đổi của nhiều tính năng. Giữ CSP nonce, cơ chế cache/hash và theme của VLEX. Kiểm tên file dài/tiếng Việt, loại file, màu trạng thái, màn hình nhỏ/dark mode. |

## Giới hạn xác minh định dạng và phiên bản

- Kiểm upload, đuôi/dung lượng/rỗng; OLE/stream đúng loại cho DOC/XLS; ZIP CRC/XML cho DOCX/XLSX; thử mở Office bằng LibreOffice và xuất PDF; qpdf/pdfinfo/Poppler kiểm PDF và dựng mọi trang thumbnail. Macro/cập nhật liên kết bị tắt trong profile kiểm tra riêng, file gốc không bị sửa.
- File mã hóa/đặt mật khẩu, quá ngân sách, timeout hoặc công cụ chưa sẵn sàng không được coi là đã xác minh. qpdf có cảnh báo cấu trúc cũng bị từ chối trong lab.
- Mốc người dùng yêu cầu là Office 2010 trở lên. DOC/XLS cũ và DOCX compatibilityMode trước 2010 có metadata cảnh báo; không kết luận file lỗi chỉ từ định dạng cũ.
- **Mở được bằng LibreOffice/Poppler không bảo đảm hiển thị/công thức/font/đối tượng nhúng đúng trên từng phiên bản Microsoft Office hoặc phần mềm PDF.** Cần mở thử bằng phiên bản thực tế đang dùng tại khách hàng. Kết quả upload thành công không phải chứng nhận tương thích mọi phiên bản.

## Nghiệm thu khi tích hợp

- [ ] Upload lẫn DOC/DOCX/XLS/XLSX/PDF hợp lệ và file lỗi trong một đợt: mỗi file có kết quả riêng; lỗi một file không ngăn các file còn lại.
- [ ] File rỗng, giả đuôi, ZIP hỏng/CRC sai/XML lỗi, PDF lỗi/đặt mật khẩu, vượt dung lượng và timeout có lý do rõ; không lưu blob/metadata mồ côi.
- [ ] Tên dài, tiếng Việt và ký tự HTML được escape; Word/Excel/PDF phân biệt rõ; thành công không lặp thông báo dài.
- [ ] Tải xuống trước/sau move có SHA-256 giống file gốc; không có luồng xem/sửa nội dung file được tự thêm.
- [ ] Tìm đích bằng tên shelf, book, book cha; phân biệt book trùng tên qua đầy đủ đường dẫn; thử sang shelf khác.
- [ ] Không thể chọn đích sai loại/Book nguồn hoặc đích thiếu quyền bằng request giả mạo. User thiếu quyền không đọc được kết quả tìm đích hay tải/xóa file.
- [ ] Sau move, Book cũ không còn liên kết file; Book đích có đúng một file, không tạo bản copy; quyền được tính lại theo vị trí hiện tại.
- [ ] Mở màn xóa và Hủy giữ file; xác nhận xóa đúng file/đúng chính sách; kiểm xóa/khôi phục/purge Book/subtree trên dữ liệu thử riêng.
- [ ] Màn selector Page/Chapter, nhập/xuất Word/Excel/PDF, tìm kiếm và dán Word vẫn hoạt động sau khi ghép các file dùng chung.
- [ ] Mở file mẫu thực tế bằng Office 2010 trở lên và các trình đọc PDF thực tế; kiểm font, bảng, công thức, ảnh/đối tượng và nội dung hiển thị.

## Kết quả đã ghi nhận và giới hạn của hồ sơ

Các lượt kiểm thử trước khi lập hồ sơ đã qua: tests/storage/check-service.php (định dạng, CRC/XML, limit, hierarchy, move, quyền, trash/restore/purge); tests/storage/check-http.ps1 (màn move/delete, tìm đích shelf/book, chuyển khác shelf, hash nguyên bản, GET xác nhận không xóa); kiểm trực tiếp trên trình duyệt tìm shelf đích → chọn Book → chuyển → mở xóa → Hủy. Test cấu trúc lab cũng đã xác nhận 5 vị trí VLEX đã triển khai và các migration stub vẫn chưa kích hoạt.

Lần tạo tài liệu này không tự chạy lại toàn bộ kiểm thử và không phải nghiệm thu trên VLEX production. Test HTTP dùng tài khoản/đường dẫn lab và tạo/xóa dữ liệu; test service dùng schema lab và rollback DB. Không chạy nguyên test script trên production.

## Phần đã gỡ / không mang sang VLEX

- Kho riêng /storage đã gỡ. Không có code hoạt động tại StorageController, StorageFile, StorageFolder và resources/views/storage/index.blade.php; không tạo lại các file này khi tích hợp.
- BookHierarchy thử nghiệm đã chuyển vào đúng vị trí app/View/LibraryMap.php; không tạo một service hierarchy song song từ đường dẫn cũ.
- Migration 2026_10_29_090000_create_vlex_storage.php chỉ giữ lịch sử lab. Các bảng vlex_storage_* cũ không có route/model/view hoạt động; không tự xóa dữ liệu của bảng cũ và không triển khai kho này sang VLEX.
- resources/views/common/header.blade.php không còn menu kho riêng; diff còn lại thuộc tìm kiếm trước đó. resources/views/entities/breadcrumbs.blade.php chỉ còn khác newline, phần xử lý thật nằm trong composer. Hai file này không được tính là thay đổi nghiệp vụ upload cần ghép.
- Không kích hoạt bằng cách đổi đuôi các migration .php.stub hoặc scaffold chưa có nội dung gốc (ACL/nhóm, ImportLibraryController…).

## Tra cứu và làm mới bảng kê

- [Mô tả triển khai](storage-files.md), [cây file VLEX](vlex-file-map.md), [mục lục hồ sơ tất cả nhóm tính năng](vlex-handover-index.md).
- Tạo lại bảng kê/hash sau khi sửa mã: chạy scripts/write-file-upload-vlex-handover.ps1 với -SnapshotDate YYYY-MM-DD đúng ngày chốt mong muốn. Script chỉ ghi hồ sơ/manifest, không deploy, commit hoặc sửa ứng dụng.
- Đối chiếu hash một file bằng Get-FileHash -Algorithm SHA256; hash khác nghĩa là file đã đổi, cần tra diff trước khi dùng hồ sơ cũ. Manifest không tự hash chính nó hoặc tài liệu sinh ra để tránh vòng tham chiếu.
'@
    $document += $details
    if ($document -match '(?m)^###\s*$') { throw 'Generated handover has an empty group heading' }
    [System.IO.File]::WriteAllText((Join-Path $repositoryRoot 'docs/file-upload-vlex-handover.md'), $document + "`n")
    Write-Output "Saved $(@($files).Count) file entries: docs/file-upload-vlex-handover.md and docs/file-upload-vlex-manifest.json"
} finally {
    Pop-Location
}
