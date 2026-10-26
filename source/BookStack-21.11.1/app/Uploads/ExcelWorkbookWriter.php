<?php

namespace BookStack\Uploads;

use DOMDocument;
use DOMElement;
use RuntimeException;

/** A saved HTML page becomes one text-valued worksheet, without executing formulas. */
class ExcelWorkbookWriter
{
    private $rows = [];
    private $merges = [];
    private $occupied = [];
    private $styles = [];
    private $images = [];
    private $row = 1;
    private $cells = 0;
    private $heights = [];

    private function xml(string $value): string
    {
        return htmlspecialchars(preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function column(int $column): string
    {
        $name = '';
        do { $column--; $name = chr(65 + $column % 26) . $name; $column = intdiv($column, 26); } while ($column > 0);
        return $name;
    }

    public function write(string $html, string $title, string $path): void
    {
        $this->rows = $this->merges = $this->occupied = $this->styles = $this->images = [];
        $this->row = 1; $this->cells = 0;
        $this->heights = [];
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML(mb_convert_encoding('<body><h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>' . $html . '</body>', 'HTML-ENTITIES', 'UTF-8'), LIBXML_NONET);
        } finally {
            libxml_clear_errors(); libxml_use_internal_errors($previous);
        }
        $this->walk($document->getElementsByTagName('body')->item(0));
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Không tạo được file Excel.');
        }
        try {
            $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
            $rels = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
            $package = 'http://schemas.openxmlformats.org/package/2006/relationships';
            $sheet = '<worksheet xmlns="' . $ns . '" xmlns:r="' . $rels . '"><sheetFormatPr defaultColWidth="24" defaultRowHeight="22"/><sheetData>';
            ksort($this->rows);
            foreach ($this->rows as $number => $cells) {
                $sheet .= '<row r="' . $number . '" ht="' . ($this->heights[$number] ?? 22) . '" customHeight="1">' . implode('', $cells) . '</row>';
            }
            $sheet .= '</sheetData>';
            if ($this->merges) { $sheet .= '<mergeCells count="' . count($this->merges) . '">' . implode('', $this->merges) . '</mergeCells>'; }
            if ($this->images) { $sheet .= '<drawing r:id="drawing1"/>'; }
            $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?>' . $sheet . '</worksheet>');
            $zip->addFromString('xl/workbook.xml', '<workbook xmlns="' . $ns . '" xmlns:r="' . $rels . '"><sheets><sheet name="Nội dung" sheetId="1" r:id="sheet1"/></sheets></workbook>');
            $zip->addFromString('_rels/.rels', '<Relationships xmlns="' . $package . '"><Relationship Id="workbook" Type="' . $rels . '/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="' . $package . '"><Relationship Id="sheet1" Type="' . $rels . '/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="styles" Type="' . $rels . '/styles" Target="styles.xml"/></Relationships>');
            $zip->addFromString('xl/styles.xml', $this->stylesXml($ns));
            $types = '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Default Extension="png" ContentType="image/png"/>' .
                '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' .
                '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            if ($this->images) {
                $this->writeImages($zip, $rels, $package);
                $types .= '<Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>';
            }
            $zip->addFromString('[Content_Types].xml', $types . '</Types>');
        } finally {
            $zip->close();
        }
    }

    private function walk(\DOMNode $parent): void
    {
        foreach ($parent->childNodes as $node) {
            if ($node instanceof DOMElement && $node->tagName === 'table') {
                $start = $this->row;
                $last = $start;
                foreach ($node->getElementsByTagName('tr') as $tr) {
                    $ancestor = $tr->parentNode;
                    while ($ancestor && $ancestor->nodeName !== 'table') { $ancestor = $ancestor->parentNode; }
                    if ($ancestor !== $node) { continue; }
                    $column = 1;
                    foreach ($tr->childNodes as $cell) {
                        if (!$cell instanceof DOMElement || !in_array($cell->tagName, ['td', 'th'], true)) { continue; }
                        while (isset($this->occupied[$this->row][$column])) { $column++; }
                        $colspan = max(1, (int) $cell->getAttribute('colspan'));
                        $rowspan = max(1, (int) $cell->getAttribute('rowspan'));
                        if ($column + $colspan > 257 || $this->row + $rowspan > 10001) {
                            throw new RuntimeException('Bảng vượt giới hạn xuất Excel (256 cột, 10.000 hàng).');
                        }
                        for ($r = $this->row; $r < $this->row + $rowspan; $r++) {
                            for ($c = $column; $c < $column + $colspan; $c++) {
                                if (isset($this->occupied[$r][$c])) { throw new RuntimeException('Các ô gộp trong bảng bị chồng lấn.'); }
                                $this->occupied[$r][$c] = true;
                            }
                        }
                        $this->cell($cell, $column);
                        if ($colspan > 1 || $rowspan > 1) {
                            $this->merges[] = '<mergeCell ref="' . $this->column($column) . $this->row . ':' . $this->column($column + $colspan - 1) . ($this->row + $rowspan - 1) . '"/>';
                        }
                        $last = max($last, $this->row + $rowspan - 1);
                        $column += $colspan;
                    }
                    $this->row++;
                }
                $this->row = max($this->row, $last + 1);
            } elseif ($node instanceof DOMElement && in_array($node->tagName, ['div', 'section', 'body', 'blockquote', 'ul', 'ol'], true)) {
                $this->walk($node);
            } elseif (trim($node->textContent) !== '' || ($node instanceof DOMElement && ($node->tagName === 'img' || $node->getElementsByTagName('img')->length))) {
                $this->cell($node, 1);
                $this->row++;
            }
        }
    }

    private function text(\DOMNode $node): string
    {
        if ($node->nodeName === 'br') { return "\n"; }
        if (!$node->hasChildNodes()) { return $node->nodeType === XML_TEXT_NODE ? $node->nodeValue : ''; }
        $result = '';
        foreach ($node->childNodes as $child) {
            $result .= $this->text($child);
            if (in_array($child->nodeName, ['p', 'div', 'tr', 'li'], true)) { $result .= "\n"; }
            if (in_array($child->nodeName, ['td', 'th'], true)) { $result .= "\t"; }
        }
        return $result;
    }

    private function cell(\DOMNode $node, int $column): void
    {
        if ($this->row > 10000 || ++$this->cells > 100000) {
            throw new RuntimeException('Nội dung vượt giới hạn xuất Excel (10.000 hàng, 100.000 ô).');
        }
        $text = $this->text($node);
        if (mb_strlen($text) > 32767) { throw new RuntimeException('Một ô vượt quá 32.767 ký tự của Excel. Hãy chia nhỏ nội dung.'); }
        $style = $this->style($node);
        $key = json_encode($style);
        if (!isset($this->styles[$key])) { $this->styles[$key] = ['index' => count($this->styles), 'value' => $style]; }
        $index = $this->styles[$key]['index'];
        // Inline strings retain zeros/date text and cannot execute as spreadsheet formulas.
        $content = $node instanceof DOMElement ? $this->richText($node) : '<t xml:space="preserve">' . $this->xml($text) . '</t>';
        $this->rows[$this->row][] = '<c r="' . $this->column($column) . $this->row . '" s="' . $index . '" t="inlineStr"><is>' . $content . '</is></c>';
        $this->heights[$this->row] = max($this->heights[$this->row] ?? 22, min(409, 15 * max(1, ceil(mb_strlen($text) / 24), substr_count($text, "\n") + 1)));
        $images = $node instanceof DOMElement ? iterator_to_array($node->getElementsByTagName('img')) : [];
        if ($node->nodeName === 'img') { $images[] = $node; }
        foreach ($images as $image) {
            if (count($this->images) >= 200) { throw new RuntimeException('Trang có quá 200 ảnh để xuất Excel.'); }
            if (!preg_match('~^data:image/[^;]+;base64,(.+)$~s', $image->getAttribute('src'), $match)) { throw new RuntimeException('Không đọc được ảnh Excel.'); }
            $bytes = base64_decode($match[1], true);
            $bitmap = @imagecreatefromstring($bytes);
            if (!$bitmap) { throw new RuntimeException('Không chuyển được ảnh Excel.'); }
            $width = imagesx($bitmap); $height = imagesy($bitmap);
            $displayWidth = min(160, max(1, (int) $image->getAttribute('width') ?: $width));
            $displayHeight = max(1, (int) round($height * $displayWidth / $width));
            $scale = min(1, 500 / $displayHeight);
            imagesavealpha($bitmap, true);
            ob_start(); imagepng($bitmap); $png = ob_get_clean(); imagedestroy($bitmap);
            $this->images[] = ['bytes' => $png, 'column' => $column - 1, 'row' => $this->row - 1, 'width' => (int) round($displayWidth * $scale * 9525), 'height' => (int) round($displayHeight * $scale * 9525)];
            $this->heights[$this->row] = max($this->heights[$this->row], (int) ceil(($displayHeight * $scale + 26) * 0.75));
        }
    }

    private function richText(\DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE || $node->nodeName === 'br') {
            $value = $node->nodeName === 'br' ? "\n" : $node->nodeValue;
            $s = $this->style($node->parentNode);
            return '<r><rPr><rFont val="' . $this->xml($s['font']) . '"/><sz val="' . $s['size'] . '"/><color rgb="FF' . $s['color'] . '"/>' . ($s['bold'] ? '<b/>' : '') . ($s['italic'] ? '<i/>' : '') . ($s['underline'] ? '<u/>' : '') . '</rPr><t xml:space="preserve">' . $this->xml($value) . '</t></r>';
        }
        $result = '';
        foreach ($node->childNodes as $child) {
            $result .= $this->richText($child);
            if (in_array($child->nodeName, ['p','div','tr','li'], true)) { $result .= '<r><t xml:space="preserve">&#10;</t></r>'; }
        }
        return $result ?: '<t/>';
    }

    private function color(string $value, string $default): string
    {
        $value = strtolower(trim($value));
        $names = ['black'=>'000000', 'white'=>'FFFFFF', 'red'=>'FF0000', 'green'=>'008000', 'blue'=>'0000FF', 'yellow'=>'FFFF00', 'gray'=>'808080'];
        if (isset($names[$value])) { return $names[$value]; }
        if (preg_match('/^#([a-f0-9]{3}|[a-f0-9]{6})$/i', $value, $match)) {
            $hex = $match[1];
            if (strlen($hex) === 3) { $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2]; }
            return strtoupper($hex);
        }
        if (preg_match('/^rgb\(\s*(\d+)[,\s]+(\d+)[,\s]+(\d+)\s*\)$/', $value, $match)) {
            return sprintf('%02X%02X%02X', min(255, $match[1]), min(255, $match[2]), min(255, $match[3]));
        }
        return $default;
    }

    private function style(\DOMNode $node): array
    {
        $chain = [];
        for ($parent = $node; $parent instanceof DOMElement; $parent = $parent->parentNode) { array_unshift($chain, $parent); }
        $style = ['font'=>'Calibri', 'size'=>11, 'color'=>'000000', 'background'=>'', 'bold'=>false, 'italic'=>false, 'underline'=>false, 'align'=>'left', 'vertical'=>'top', 'border'=>'thin', 'borderColor'=>'AAAAAA'];
        foreach ($chain as $element) {
            if (in_array($element->tagName, ['b','strong','th'], true) || preg_match('/^h[1-6]$/', $element->tagName)) { $style['bold'] = true; }
            if (preg_match('/^h([1-6])$/', $element->tagName, $heading)) { $style['size'] = [1=>22,2=>18,3=>16,4=>14,5=>12,6=>11][(int)$heading[1]]; }
            if (in_array($element->tagName, ['i','em'], true)) { $style['italic'] = true; }
            if ($element->tagName === 'u') { $style['underline'] = true; }
            if ($element->hasAttribute('face')) { $style['font'] = $element->getAttribute('face'); }
            if ($element->tagName === 'font' && preg_match('/^[1-7]$/', $element->getAttribute('size'))) { $style['size'] = [1=>8,2=>10,3=>12,4=>14,5=>18,6=>24,7=>36][(int)$element->getAttribute('size')]; }
            if ($element->hasAttribute('color')) { $style['color'] = $this->color($element->getAttribute('color'), $style['color']); }
            if (in_array($element->getAttribute('align'), ['left','center','right','justify'], true)) { $style['align'] = $element->getAttribute('align'); }
            foreach (explode(';', $element->getAttribute('style')) as $declaration) {
                $parts = explode(':', $declaration, 2);
                if (count($parts) !== 2) { continue; }
                [$property, $value] = array_map('trim', $parts);
                $property = strtolower($property);
                if ($property === 'font-family') { $style['font'] = trim(explode(',', $value)[0], " \t\"'"); }
                if ($property === 'font-size' && preg_match('/^([0-9.]+)(px|pt)?$/i', $value, $size)) { $style['size'] = max(1, min(409, (float)$size[1] * (strtolower($size[2] ?? '') === 'px' ? 0.75 : 1))); }
                if ($property === 'font-weight') { $style['bold'] = in_array(strtolower($value), ['bold','bolder','600','700','800','900'], true); }
                if ($property === 'font-style') { $style['italic'] = strtolower($value) === 'italic'; }
                if ($property === 'text-decoration') { $style['underline'] = strpos($value, 'underline') !== false; }
                if ($property === 'color') { $style['color'] = $this->color($value, $style['color']); }
                if ($property === 'background-color') { $style['background'] = $this->color($value, ''); }
                if ($property === 'text-align' && in_array($value, ['left','center','right','justify'], true)) { $style['align'] = $value; }
                if ($property === 'vertical-align' && in_array($value, ['top','middle','bottom'], true)) { $style['vertical'] = $value === 'middle' ? 'center' : $value; }
                if ($property === 'border') {
                    foreach (['left','right','top','bottom'] as $edge) { $style['edges'][$edge] = $this->border($value); }
                }
                if (preg_match('/^border-(left|right|top|bottom)$/', $property, $edge)) {
                    $style['edges'][$edge[1]] = $this->border($value);
                }
            }
        }
        return $style;
    }

    private function border(string $value): array
    {
        $line = 'thin'; $color = 'AAAAAA';
        if (preg_match('/none|hidden|^0(?:px|pt)?(?:\s|$)/i', $value)) { $line = ''; }
        elseif (preg_match('/double|dashed|dotted/i', $value, $match)) { $line = strtolower($match[0]); }
        elseif (preg_match('/([0-9.]+)(px|pt)/i', $value, $match)) { $line = (float)$match[1] > 3 ? 'thick' : ((float)$match[1] > 1.5 ? 'medium' : 'thin'); }
        if (preg_match('/#[a-f0-9]{3,6}\b|rgb\([^)]*\)|\b(?:black|white|red|green|blue|yellow|gray)\b/i', $value, $match)) { $color = $this->color($match[0], $color); }
        return ['line'=>$line, 'color'=>$color];
    }

    private function stylesXml(string $ns): string
    {
        $fonts = $fills = $borders = $formats = '';
        foreach ($this->styles as $entry) {
            $s = $entry['value']; $i = $entry['index'];
            $fonts .= '<font><sz val="' . $s['size'] . '"/><color rgb="FF' . $s['color'] . '"/><name val="' . $this->xml($s['font']) . '"/>' . ($s['bold'] ? '<b/>' : '') . ($s['italic'] ? '<i/>' : '') . ($s['underline'] ? '<u/>' : '') . '</font>';
            $fills .= '<fill><patternFill patternType="' . ($s['background'] ? 'solid' : 'none') . '">' . ($s['background'] ? '<fgColor rgb="FF' . $s['background'] . '"/><bgColor indexed="64"/>' : '') . '</patternFill></fill>';
            $borders .= '<border>';
            foreach (['left','right','top','bottom'] as $edge) {
                $border = $s['edges'][$edge] ?? ['line'=>$s['border'], 'color'=>$s['borderColor']];
                $borders .= '<' . $edge . ($border['line'] ? ' style="' . $border['line'] . '"' : '') . '><color rgb="FF' . $border['color'] . '"/></' . $edge . '>';
            }
            $borders .= '<diagonal/></border>';
            $formats .= '<xf numFmtId="49" fontId="' . $i . '" fillId="' . ($i + 2) . '" borderId="' . $i . '" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1" applyNumberFormat="1"><alignment horizontal="' . $s['align'] . '" vertical="' . $s['vertical'] . '" wrapText="1"/></xf>';
        }
        $count = count($this->styles);
        return '<styleSheet xmlns="' . $ns . '"><fonts count="' . $count . '">' . $fonts . '</fonts><fills count="' . ($count + 2) . '"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>' . $fills . '</fills><borders count="' . $count . '">' . $borders . '</borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="' . $count . '">' . $formats . '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }

    private function writeImages(\ZipArchive $zip, string $rels, string $package): void
    {
        $drawing = '<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="' . $rels . '">';
        $relationships = '<Relationships xmlns="' . $package . '">';
        foreach ($this->images as $index => $image) {
            $id = $index + 1;
            $zip->addFromString('xl/media/image' . $id . '.png', $image['bytes']);
            $relationships .= '<Relationship Id="image' . $id . '" Type="' . $rels . '/image" Target="../media/image' . $id . '.png"/>';
            $drawing .= '<xdr:oneCellAnchor><xdr:from><xdr:col>' . $image['column'] . '</xdr:col><xdr:colOff>9525</xdr:colOff><xdr:row>' . $image['row'] . '</xdr:row><xdr:rowOff>228600</xdr:rowOff></xdr:from><xdr:ext cx="' . $image['width'] . '" cy="' . $image['height'] . '"/><xdr:pic><xdr:nvPicPr><xdr:cNvPr id="' . $id . '" name="Image ' . $id . '"/><xdr:cNvPicPr/></xdr:nvPicPr><xdr:blipFill><a:blip r:embed="image' . $id . '"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill><xdr:spPr><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr></xdr:pic><xdr:clientData/></xdr:oneCellAnchor>';
        }
        $zip->addFromString('xl/drawings/drawing1.xml', $drawing . '</xdr:wsDr>');
        $zip->addFromString('xl/drawings/_rels/drawing1.xml.rels', $relationships . '</Relationships>');
        $zip->addFromString('xl/worksheets/_rels/sheet1.xml.rels', '<Relationships xmlns="' . $package . '"><Relationship Id="drawing1" Type="' . $rels . '/drawing" Target="../drawings/drawing1.xml"/></Relationships>');
    }
}
