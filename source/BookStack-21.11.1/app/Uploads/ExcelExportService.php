<?php

namespace BookStack\Uploads;

use BookStack\Entities\Models\Page;
use BookStack\Entities\Tools\PageContent;
use DOMDocument;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ExcelExportService
{
    private $images;

    public function __construct(ImageService $images)
    {
        $this->images = $images;
    }

    public function export(Page $page): string
    {
        $directory = sys_get_temp_dir() . '/bookstack-excel-export-' . bin2hex(random_bytes(16));
        if (!mkdir($directory, 0700)) {
            throw new RuntimeException('Không tạo được thư mục xuất Excel.');
        }
        try {
            $html = (new PageContent($page))->render();
            if (strlen($html) > 20 * 1024 * 1024) {
                throw new RuntimeException('Nội dung quá lớn để xuất Excel. Hãy chia nhỏ trang.');
            }
            $document = new DOMDocument();
            $previous = libxml_use_internal_errors(true);
            try {
                $document->loadHTML(mb_convert_encoding('<body>' . $html . '</body>', 'HTML-ENTITIES', 'UTF-8'), LIBXML_NONET);
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }
            $total = 0;
            foreach ($document->getElementsByTagName('img') as $index => $image) {
                $src = $image->getAttribute('src');
                $data = strpos($src, 'data:image/') === 0 ? $src : $this->images->imageUriToBase64($src);
                if (!$data || !preg_match('~^data:image/(png|jpe?g|gif|webp);base64,(.+)$~s', $data, $match)) {
                    throw new RuntimeException('Có ảnh không thể nhúng vào Excel. Hãy tải ảnh vào kho ảnh của trang rồi thử lại.');
                }
                $bytes = base64_decode($match[2], true);
                $size = $bytes === false ? false : @getimagesizefromstring($bytes);
                if (!$size || !in_array($size[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) {
                    throw new RuntimeException('Ảnh trong trang không đúng định dạng hỗ trợ.');
                }
                $total += strlen($bytes);
                if ($total > 30 * 1024 * 1024) {
                    throw new RuntimeException('Tổng dung lượng ảnh vượt quá 30 MB.');
                }
                $name = 'image-' . $index . '.' . image_type_to_extension($size[2], false);
                file_put_contents($directory . '/' . $name, $bytes);
                $image->setAttribute('src', $name);
            }
            $html = (new WordImportService())->cleanHtml($document->saveHTML(), $directory);
            $output = $directory . '/page.xlsx';
            (new ExcelWorkbookWriter())->write($html, $page->name, $output);
            if (filesize($output) > 30 * 1024 * 1024) {
                throw new RuntimeException('File Excel vượt quá 30 MB. Hãy chia nhỏ trang.');
            }
            return file_get_contents($output);
        } finally {
            File::deleteDirectory($directory);
        }
    }
}
