"""Rebuild the supplied VLEX paths without replacing existing BookStack code.

Uses only Python's standard library; this is a local preparation tool, not an
application dependency. Re-running preserves every existing destination file.
"""
import base64
import json
import re
import shutil
from pathlib import Path

LAB = Path(__file__).resolve().parents[1]
SOURCE = LAB / 'source' / 'BookStack-21.11.1'
REFERENCE = LAB / 'docs/references/vlex-app-tree.txt'
DOCS = LAB / 'docs'
DOCS.mkdir(exist_ok=True)
records = []

def record(path, status, note=''):
    records.append({'path': str(path).replace('\\', '/'), 'status': status, 'note': note})

def put(relative, content, status, note=''):
    target = SOURCE / relative
    if target.exists():
        record(relative, 'existing', 'Giữ file hiện có; ' + note)
        return
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(content, encoding='utf-8', newline='\n')
    record(relative, status, note)

# Archive the earlier independent prototype. Restrict all moves to this lab.
archive_paths = [
    'source/BookStack-21.11.1/app/Http/Controllers/VlexWorkspaceController.php',
    'source/BookStack-21.11.1/resources/views/vlex',
    'source/BookStack-21.11.1/public/dist/vlex-workspace.css',
    'tests/check-vlex.ps1', 'tests/check-vlex-isolation.php',
    'tests/make-vlex-fixtures.py', 'docs/vlex-workspace.md',
]
for relative in archive_paths:
    origin = (LAB / relative).resolve()
    destination = (LAB / 'archive/workspace-prototype' / relative).resolve()
    if not origin.is_relative_to(LAB.resolve()) or not destination.is_relative_to(LAB.resolve()):
        raise RuntimeError('Archive path escapes the lab')
    if origin.exists():
        if destination.exists():
            raise RuntimeError(f'Archive destination already exists: {relative}')
        destination.parent.mkdir(parents=True, exist_ok=True)
        origin.rename(destination)

originals = {}
for path in (SOURCE / 'app').rglob('*.php'):
    if 'VLEX compatibility path' in path.read_text(encoding='utf-8-sig') or 'VLEX SCAFFOLD' in path.read_text(encoding='utf-8-sig'):
        continue
    originals.setdefault(path.name, []).append(path.relative_to(SOURCE).as_posix())

special = {
    'app/Auth/Guards/ApiTokenAuthenticationGuard.php': 'app/Api/ApiTokenGuard.php',
    'app/Permissions/PermissionRepo.php': 'app/Auth/Permissions/PermissionsRepo.php',
    'app/Entities/Tools/PermissionsUpdate.php': 'app/Entities/Tools/PermissionsUpdater.php',
    'app/Entities/Tools/SiblingFinder.php': 'app/Entities/Tools/SiblingFetcher.php',
    'app/Entities/Tools/Markdown/CustomStrikeThroughRenderer.php': 'app/Entities/Tools/Markdown/CustomStrikethroughRenderer.php',
}
purposes = {
    'AttachedFile': 'Tài liệu gắn với thực thể VLEX; chờ schema và quan hệ gốc.',
    'AttachedFileRepo': 'Upload/download/move tài liệu; chờ API của mã VLEX gốc.',
    'Group': 'Nhóm người dùng do admin quản lý; chờ schema và quyền gốc.',
    'PermissionGroup': 'Liên kết quyền với nhóm; chờ schema gốc.',
    'PermissionUser': 'Quyền theo người dùng; chờ schema gốc.',
    'GroupRepo': 'Quản lý nhóm người dùng; chưa nối vào quyền BookStack.',
    'GroupPermissionRepo': 'Quản lý quyền xem/thêm/sửa/xóa cho nhóm.',
    'PermissionRepo': 'Phân quyền VLEX theo thực thể/người dùng/nhóm.',
    'FileAttachedController': 'Thao tác tệp trực tiếp trong sách/chương/trang BookStack.',
    'GroupController': 'Admin quản lý nhóm; chưa đăng ký route.',
    'PermissionsAdminController': 'Admin cấp quyền người dùng và nhóm; chưa đăng ký route.',
    'ImportLibraryController': 'Nhập thư viện tài liệu vào cấu trúc BookStack.',
    'ImporterV2Controller': 'API nhập tài liệu VLEX; chờ định dạng request gốc.',
    'HierarchyFolder': 'Hiển thị cây thư mục cha/con trong giao diện BookStack.',
    'LibraryMap': 'Ánh xạ cây thư viện VLEX với thực thể BookStack.',
    'ZipFolder': 'Xuất cây thư mục thành ZIP; chỉ dùng thư viện sẵn có.',
}

# Parse the supplied directory tree as data. Never execute its contents.
levels = {0: 'app'}
for line in REFERENCE.read_text(encoding='utf-8-sig').splitlines():
    match = re.match(r'^(.*?)(?:├──|└──)\s+(.+)$', line)
    if not match:
        continue
    prefix, name = match.groups()
    depth = len(prefix) // 4 + 1
    parent = levels.get(depth - 1)
    if not parent:
        raise RuntimeError(f'Unresolved reference parent: {line}')
    if '…' in name or '(tên' in name or '(một số' in name:
        record(parent + '/' + name, 'unresolved', 'Tên không đọc rõ; không tự đoán tên file.')
        continue
    if name.endswith('/'):
        relative = parent + '/' + name[:-1]
        (SOURCE / relative).mkdir(parents=True, exist_ok=True)
        levels[depth] = relative
        continue
    if not re.fullmatch(r'[A-Za-z_][A-Za-z_0-9-]*\.php', name):
        raise RuntimeError(f'Unexpected reference filename: {name}')
    relative = parent + '/' + name
    if (SOURCE / relative).exists():
        existing = (SOURCE / relative).read_text(encoding='utf-8-sig')
        if 'VLEX compatibility path' in existing:
            original = re.search(r'Source: (.+)', existing)
            record(relative, 'compatibility-alias', original.group(1) if original else '')
        elif 'VLEX SCAFFOLD' in existing:
            record(relative, 'scaffold', 'Chờ mã VLEX gốc; không được nối vào luồng chạy.')
        else:
            record(relative, 'existing', 'Giữ nguyên mã BookStack đang có.')
        continue
    equivalent = special.get(relative)
    if not equivalent and len(originals.get(name, [])) == 1:
        equivalent = originals[name][0]
    namespace = 'BookStack\\' + parent.removeprefix('app/').replace('/', '\\')
    class_name = name[:-4]
    if equivalent:
        target_class = 'BookStack\\' + equivalent.removeprefix('app/').removesuffix('.php').replace('/', '\\')
        # class_alias preserves Eloquent identity and existing type hints.
        body = f'''<?php

namespace {namespace};

/**
 * VLEX compatibility path. Uses the existing BookStack implementation.
 * Source: {equivalent}
 * Replace this entire file when the real VLEX implementation is available.
 * Runtime bindings and existing BookStack classes are unchanged.
 */
class_alias(\\{target_class}::class, __NAMESPACE__ . '\\\\{class_name}');
'''
        put(relative, body, 'compatibility-alias', equivalent)
    elif parent == 'app/Config':
        put(relative, '<?php\n\n// VLEX SCAFFOLD: replace with original configuration; no features enabled.\nreturn [];\n', 'scaffold', 'Cấu hình rỗng, chưa bật tính năng/module.')
    else:
        purpose = purposes.get(class_name, 'Chờ nội dung file VLEX gốc; chưa suy đoán hành vi.')
        extends = ' extends \\BookStack\\Http\\Controllers\\Controller' if '/Controllers' in parent else ''
        body = f'''<?php

namespace {namespace};

/**
 * VLEX SCAFFOLD — not an implemented feature.
 * {purpose}
 * Replace this entire file with VLEX source, then check imports/schema/permissions.
 * This file is not registered in routes, service providers or auth bindings.
 */
class {class_name}{extends}
{{
    public function __construct()
    {{
        throw new \\LogicException('VLEX scaffold: {relative} requires original implementation.');
    }}
}}
'''
        put(relative, body, 'scaffold', purpose)

# Seed path from the reference. Keep the original database/seeders untouched.
for name in ['DatabaseSeeder', 'DummyContentSeeder']:
    put(f'database/seeds/{name}.php', f'''<?php

// VLEX compatibility path; no seeding is performed by including this file.
// Composer continues to load Database\\Seeders from database/seeders.
require_once __DIR__ . '/../seeders/{name}.php';
''', 'compatibility-loader', f'database/seeders/{name}.php')
put('database/seeds/LegacyContentSeeder.php', '''<?php

// VLEX SCAFFOLD: not registered in Composer or invoked by DatabaseSeeder.
// Replace with the original VLEX file before enabling any seed operation.
''', 'scaffold', 'Chờ mã seeder VLEX; không tạo dữ liệu giả.')

migrations = [
    '2021_12_02_143028_create_attached_file',
    '2021_12_29_064429_add_new_role_chapter_file',
    '2022_01_15_034813_create_entity_permission_table',
    '2022_01_15_034920_create_entity_permission_role_table',
    '2022_01_15_035901_create_entity_permission_user_table',
    '2022_02_25_070035_add_new_column_in_user_table',
    '2022_02_25_070946_create_groups_table',
    '2022_02_25_071406_create_permissions_table',
    '2022_02_27_112844_create_group_user_table',
    '2022_03_02_132433_create_permission_user_table',
    '2022_03_11_071345_create_group_permission_table',
    '2022_03_16_234953_add_type_to_users_table',
    '2022_03_16_235625_insert_data_to_permissions_table',
]
for name in migrations:
    class_name = ''.join(word.capitalize() for word in name[18:].split('_'))
    put(f'database/migrations/{name}.php.stub', f'''<?php

use Illuminate\\Database\\Migrations\\Migration;

/** VLEX SCAFFOLD: date/name normalized from transcription; verify original. */
class {class_name} extends Migration
{{
    public function up()
    {{
        throw new \\LogicException('Copy original VLEX schema before enabling {name}.');
    }}

    public function down()
    {{
        throw new \\LogicException('VLEX rollback schema has not been supplied.');
    }}
}}
''', 'inactive-migration', 'Tên chuẩn hóa từ bản chép; chưa biết schema. Đuôi .stub ngăn migrate tự chạy.')
for path in sorted((SOURCE / 'database/migrations').glob('*.php')):
    record(path.relative_to(SOURCE).as_posix(), 'existing', 'Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai.')

# API sample files already shipped with BookStack are copied as reference only.
for path in sorted((SOURCE / 'dev/api').rglob('*.json')):
    relative = 'api/' + path.relative_to(SOURCE / 'dev/api').as_posix()
    put(relative, path.read_text(encoding='utf-8'), 'reference-copy', path.relative_to(SOURCE).as_posix())
for name in ['api/requests/attachments-group.json', 'api/requests/search-all.json']:
    put(name, '{}\n', 'scaffold', 'Chưa có payload VLEX gốc; {} là chỗ giữ, không phải request đã hỗ trợ.')
for original, target in [('components.md','components.md'), ('logical-theme-system.md','legal-theme-system.md'), ('visual-theme-system.md','visual-theme-system.md')]:
    origin = SOURCE / 'dev/docs' / original
    put('docs/' + target, origin.read_text(encoding='utf-8'), 'reference-copy', 'Tài liệu BookStack gốc: dev/docs/' + original + '; tên legal cần đối chiếu VLEX.')

for folder in ['docker/initdb', 'public/images', 'public/js', 'public/public', 'app/Auth/Old']:
    put(folder + '/.gitkeep', '', 'directory', 'Giữ đường dẫn từ cây VLEX.')
for name in ['Dockerfile','entrypoint.sh','entrypoint.redhat.sh']:
    put('docker/' + name + '.stub', '# VLEX SCAFFOLD: replace with original; local runtime uses bookstack-lab/Dockerfile and docker/entrypoint.sh.\n', 'inactive-runtime', 'Không thay đổi môi trường Docker đang chạy.')

# Real existing assets can be reused; absent images are explicitly placeholders.
png = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a5XcAAAAASUVORK5CYII=')
images = ['date-error.png','document.png','icons-add.png','icons-reduce.png','icons-microsoft-excel-24.png','img-add-border.png','img-add-history.png','img-add-library.png','img-add-resize.png','sheet.png']
public_images = ['btn_search_group.png','error.png','error_2x.png','error_3x.png','Hide_show_pass.png','logo2.png']
for relative in ['public/images/' + name for name in images] + ['public/public/' + name for name in public_images]:
    destination = SOURCE / relative
    if not destination.exists():
        destination.parent.mkdir(parents=True, exist_ok=True)
        destination.write_bytes(png)
        record(relative, 'asset-placeholder', 'PNG 1 pixel giữ đường dẫn; cần ảnh VLEX gốc, chưa gắn vào UI.')
    else:
        record(relative, 'existing')
for name in ['favicon.ico','loading.gif','logo.png','user_avatar.png','web.config']:
    destination = SOURCE / 'public/public' / name
    if not destination.exists():
        shutil.copyfile(SOURCE / 'public' / name, destination)
    record('public/public/' + name, 'reference-copy', 'Bản sao public/' + name + '; public/public không phải document root.')
put('public/public/index.php', "<?php\n// VLEX path placeholder. The active entry point remains public/index.php.\nhttp_response_code(501);\necho 'VLEX scaffold: nested entry point is not configured.';\n", 'scaffold', 'Không tạo entry point thứ hai cho ứng dụng.')
put('.editorconfig', 'root = true\n\n[*]\ncharset = utf-8\nend_of_line = lf\ninsert_final_newline = true\nindent_style = space\nindent_size = 4\n', 'scaffold', 'Thiết lập soạn thảo cục bộ.')
put('modules_statuses.json', '{}\n', 'scaffold', 'Không có module ngoài được cài hoặc kích hoạt.')
put('requirements.txt', '# VLEX reference path. No Python application dependencies are required.\n', 'scaffold', 'Không cài thêm thư viện.')
put('cache.sh', '#!/bin/sh\n# Local BookStack cache maintenance; no external dependencies.\nset -eu\ncd "$(dirname "$0")"\nphp artisan cache:clear\nphp artisan view:clear\n', 'scaffold', 'Dùng các lệnh artisan đã có, chạy thủ công trong container.')
put('.styleci.yml', '# VLEX reference path; no external CI service configured.\npreset: laravel\n', 'scaffold', 'Chưa tích hợp CI ngoài.')
record('public/images/Group … .png; public/public/Mark Group … .png; public/public/logo.gif', 'unresolved', 'Tên ảnh nhóm và ảnh logo GIF chưa có nguồn gốc đầy đủ; không tự tạo thương hiệu giả.')
record('public/dist/*', 'existing', 'Giữ assets BookStack có sẵn; không thay JS/CSS hay cài npm để mô phỏng tên file khác phiên bản.')

(DOCS / 'vlex-file-map.json').write_text(json.dumps(records, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
lines = ['# Đối chiếu đường dẫn VLEX / BookStack 21.11.1', '', 'Danh sách theo bản chép người dùng cung cấp. `existing`: giữ file hiện có; `compatibility-alias/loader`: dùng lại mã gốc; `scaffold`: chờ mã VLEX; `inactive-migration/runtime`: chưa kích hoạt; `reference-copy`: sao chép tài liệu/mẫu của BookStack; `asset-placeholder`: ảnh giữ chỗ; `unresolved`: chưa rõ tên/nội dung.', '', '| Đường dẫn trong source/BookStack-21.11.1 | Trạng thái | Ghi chú |', '| --- | --- | --- |']
for item in records:
    lines.append('| `' + item['path'].replace('|','\\|') + '` | ' + item['status'] + ' | ' + item['note'].replace('|','\\|') + ' |')
(DOCS / 'vlex-file-map.md').write_text('\n'.join(lines) + '\n', encoding='utf-8')
(DOCS / 'vlex-source-tree.txt').write_text('\n'.join(sorted(item['path'] for item in records)) + '\n', encoding='utf-8')
print(json.dumps({status: sum(item['status'] == status for item in records) for status in sorted({item['status'] for item in records})}, ensure_ascii=False))
