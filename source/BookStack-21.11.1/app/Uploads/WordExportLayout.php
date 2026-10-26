<?php

namespace BookStack\Uploads;

use DOMDocument;
use DOMElement;
use DOMXPath;

/** Resolve web dimensions inside their containing block before/after Writer. */
class WordExportLayout
{
    private const WORD = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    private const PAGE_WIDTH = 9600; // A4, with 0.8 inch margins, in twips.
    // Leave room for a heading/caption, cell padding and the image's paragraph.
    private const PAGE_HEIGHT = 12000;

    private function styles(DOMElement $node): array
    {
        $result = [];
        foreach (explode(';', $node->getAttribute('style')) as $declaration) {
            $parts = explode(':', $declaration, 2);
            if (count($parts) === 2) {
                $result[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
        }
        return $result;
    }

    private function length(string $value, float $available, float $default): float
    {
        if (!preg_match('/^(-?[0-9]+(?:\.[0-9]+)?)\s*(%|px|pt|in|cm|mm|pc)?$/i', trim($value), $m)) {
            return $default;
        }
        $scale = ['%' => $available / 100, 'px' => 15, 'pt' => 20, 'in' => 1440, 'cm' => 1440 / 2.54, 'mm' => 1440 / 25.4, 'pc' => 240, '' => 15];
        return (float) $m[1] * $scale[strtolower($m[2] ?? '')];
    }

    private function saveStyles(DOMElement $node, array $styles): void
    {
        $values = [];
        foreach ($styles as $property => $value) {
            $values[] = $property . ': ' . $value;
        }
        $node->setAttribute('style', implode('; ', $values));
    }

    public function prepareHtml(DOMElement $body): void
    {
        $this->fitHtml($body, self::PAGE_WIDTH);
    }

    private function fitHtml(DOMElement $node, float $available): void
    {
        $styles = $this->styles($node);
        $tag = strtolower($node->tagName);
        $available = max(120, $available);
        if ($tag === 'table') {
            $this->fitHtmlTable($node, $available);
            return;
        }
        if ($tag === 'img') {
            $naturalWidth = max(1, (float) $node->getAttribute('width') * 15);
            $naturalHeight = max(1, (float) $node->getAttribute('height') * 15);
            $width = max(15, $this->length($styles['width'] ?? '', $available, $naturalWidth));
            $height = max(15, $this->length($styles['height'] ?? '', self::PAGE_HEIGHT, $naturalHeight * $width / $naturalWidth));
            $maxWidth = min($available, $this->length($styles['max-width'] ?? '', $available, $available));
            $maxHeight = min(self::PAGE_HEIGHT, $this->length($styles['max-height'] ?? '', self::PAGE_HEIGHT, self::PAGE_HEIGHT));
            $scale = min(1, max(15, $maxWidth) / $width, max(15, $maxHeight) / $height);
            $width = max(1, (int) floor($width * $scale / 15));
            $height = max(1, (int) floor($height * $scale / 15));
            $node->setAttribute('width', (string) $width);
            $node->setAttribute('height', (string) $height);
            $node->setAttribute('align', 'bottom');
            unset($styles['max-width'], $styles['max-height'], $styles['margin'], $styles['margin-left'], $styles['margin-right']);
            $styles['width'] = $width . 'px';
            $styles['height'] = $height . 'px';
            $this->saveStyles($node, $styles);
            return;
        }
        if (in_array($tag, ['tr', 'td', 'th'], true)) {
            unset($styles['height']);
            $node->removeAttribute('height');
            $node->removeAttribute('nowrap');
        }
        if (in_array($tag, ['div', 'p', 'blockquote', 'pre', 'ul', 'ol', 'li'], true)) {
            // Keep indentation, but reserve enough usable width for text/children.
            $remaining = $available;
            foreach (['margin-left', 'margin-right', 'padding-left', 'padding-right'] as $property) {
                if (isset($styles[$property])) {
                    $value = min(max(0, $this->length($styles[$property], $available, 0)), max(0, $remaining - 720));
                    $styles[$property] = round($value / 20, 3) . 'pt';
                    $remaining -= $value;
                }
            }
            $available = max(120, $remaining);
            if (isset($styles['width']) || $node->hasAttribute('width')) {
                $available = min($available, max(120, $this->length($styles['width'] ?? $node->getAttribute('width'), $available, $available)));
                $styles['width'] = round($available / 20, 3) . 'pt';
                $node->removeAttribute('width');
            }
            unset($styles['height']);
        }
        if (($styles['white-space'] ?? '') === 'nowrap' || $tag === 'pre' || $tag === 'code') {
            $styles['white-space'] = 'normal';
        }
        $this->saveStyles($node, $styles);
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $this->fitHtml($child, $available);
            } elseif ($child instanceof \DOMText) {
                // Word does not implement CSS overflow-wrap. Add non-printing
                // break opportunities to long tokens in the export copy only.
                $child->nodeValue = preg_replace_callback('/[^\s\x{200B}]{40,}/u', function ($match) {
                    return implode("\u{200B}", mb_str_split($match[0], 24));
                }, $child->nodeValue);
            }
        }
    }

    private function fitHtmlTable(DOMElement $table, float $available): void
    {
        $styles = $this->styles($table);
        $width = min($available, max(120, $this->length($styles['width'] ?? $table->getAttribute('width'), $available, $available)));
        unset($styles['height'], $styles['margin'], $styles['margin-left'], $styles['margin-right']);
        $styles['width'] = round($width / 20, 3) . 'pt';
        $styles['table-layout'] = 'fixed';
        $styles['margin-left'] = '0';
        $styles['white-space'] = 'normal';
        $table->removeAttribute('height');
        $table->setAttribute('width', (string) max(1, (int) floor($width / 15)));
        $table->setAttribute('cellspacing', '0');
        $this->saveStyles($table, $styles);
        $xpath = new DOMXPath($table->ownerDocument);
        $rows = $xpath->query('./tr|./thead/tr|./tbody/tr|./tfoot/tr', $table);
        $placements = [];
        $occupied = [];
        $columns = 0;
        foreach ($rows as $rowIndex => $row) {
            $column = 0;
            foreach ($xpath->query('./td|./th', $row) as $cell) {
                while (($occupied[$column] ?? 0) > $rowIndex) {
                    $column++;
                }
                $span = max(1, min(100, (int) $cell->getAttribute('colspan')));
                $rowspan = max(1, min(1000, (int) $cell->getAttribute('rowspan')));
                $placements[] = [$cell, $column, $span];
                for ($i = $column; $i < $column + $span; $i++) {
                    $occupied[$i] = $rowIndex + $rowspan;
                }
                $column += $span;
                $columns = max($columns, $column);
            }
        }
        if (!$columns) {
            return;
        }
        $widths = array_fill(0, $columns, null);
        $column = 0;
        foreach ($xpath->query('./col|./colgroup/col', $table) as $col) {
            $style = $this->styles($col);
            $colWidth = $this->length($style['width'] ?? $col->getAttribute('width'), $width, 0);
            $span = max(1, min(100, (int) $col->getAttribute('span')));
            for ($i = 0; $i < $span && $column < $columns; $i++, $column++) {
                if ($colWidth > 0) {
                    $widths[$column] = $colWidth;
                }
            }
        }
        foreach ($placements as [$cell, $column, $span]) {
            $style = $this->styles($cell);
            $cellWidth = $this->length($style['width'] ?? $cell->getAttribute('width'), $width, 0);
            if ($cellWidth > 0) {
                for ($i = $column; $i < $column + $span; $i++) {
                    if ($widths[$i] === null) {
                        $widths[$i] = $cellWidth / $span;
                    }
                }
            }
        }
        $missing = count(array_filter($widths, function ($value) { return $value === null; }));
        $fallback = $missing ? max($width / $columns / 4, ($width - array_sum($widths)) / $missing) : 0;
        $widths = array_map(function ($value) use ($fallback) { return max(60, $value ?? $fallback); }, $widths);
        $factor = $width / array_sum($widths);
        $widths = array_map(function ($value) use ($factor) { return $value * $factor; }, $widths);
        foreach (iterator_to_array($xpath->query('./col|./colgroup', $table)) as $col) {
            $table->removeChild($col);
        }
        $group = $table->ownerDocument->createElement('colgroup');
        foreach ($widths as $colWidth) {
            $col = $table->ownerDocument->createElement('col');
            $col->setAttribute('width', (string) max(1, (int) floor($colWidth / 15)));
            $group->appendChild($col);
        }
        $table->insertBefore($group, $table->firstChild);
        foreach ($rows as $row) {
            $style = $this->styles($row);
            unset($style['height'], $style['width']);
            $style['white-space'] = 'normal';
            $row->removeAttribute('height');
            $this->saveStyles($row, $style);
        }
        foreach ($placements as [$cell, $column, $span]) {
            $cellWidth = array_sum(array_slice($widths, $column, $span));
            $style = $this->styles($cell);
            unset($style['height'], $style['white-space'], $style['padding-left'], $style['padding-right']);
            $style['width'] = round($cellWidth / 20, 3) . 'pt';
            $style['padding'] = '5pt';
            $style['white-space'] = 'normal';
            $cell->setAttribute('width', (string) max(1, (int) floor($cellWidth / 15)));
            $cell->removeAttribute('height');
            $cell->removeAttribute('nowrap');
            if (!$cell->hasAttribute('valign') && !isset($style['vertical-align'])) {
                $cell->setAttribute('valign', 'top');
            }
            $this->saveStyles($cell, $style);
            // Writer can attach a table at the start of a cell to the previous
            // cell (especially with rowspans). Give it an explicit paragraph anchor.
            foreach (iterator_to_array($cell->childNodes) as $child) {
                if ($child instanceof DOMElement && $child->tagName === 'table') {
                    if (!$child->previousSibling || !($child->previousSibling instanceof DOMElement) || $child->previousSibling->tagName !== 'p') {
                        $paragraph = $cell->ownerDocument->createElement('p');
                        $paragraph->setAttribute('style', 'margin: 0; font-size: 1pt');
                        $paragraph->appendChild($cell->ownerDocument->createElement('br'));
                        $cell->insertBefore($paragraph, $child);
                    }
                    $paragraph = $cell->ownerDocument->createElement('p');
                    $paragraph->setAttribute('style', 'margin: 0; font-size: 1pt');
                    $paragraph->appendChild($cell->ownerDocument->createElement('br'));
                    $cell->insertBefore($paragraph, $child->nextSibling);
                }
            }
            foreach (iterator_to_array($cell->childNodes) as $child) {
                if ($child instanceof DOMElement) {
                    $this->fitHtml($child, max(120, $cellWidth - 240));
                } elseif ($child instanceof \DOMText) {
                    $child->nodeValue = preg_replace('/([^\s\x{200B}]{24})(?=[^\s\x{200B}]{16})/u', '$1' . "\u{200B}", $child->nodeValue);
                }
            }
        }
    }

    public function fitDocument(DOMDocument $document): void
    {
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', self::WORD);
        $section = $xpath->query('//w:sectPr')->item(0);
        $width = self::PAGE_WIDTH;
        if ($section) {
            $pageWidth = (int) $xpath->evaluate('string(w:pgSz/@w:w)', $section);
            $margins = (int) $xpath->evaluate('string(w:pgMar/@w:left)', $section) + (int) $xpath->evaluate('string(w:pgMar/@w:right)', $section);
            if ($pageWidth > $margins) {
                $width = $pageWidth - $margins;
            }
        }
        $body = $xpath->query('//w:body')->item(0);
        $this->fitWordBlock($body, $width, $xpath);
    }

    private function property(DOMElement $parent, string $name, array $attributes = []): DOMElement
    {
        $node = null;
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $name && $child->namespaceURI === self::WORD) {
                $node = $child;
                break;
            }
        }
        if (!$node) {
            $node = $parent->ownerDocument->createElementNS(self::WORD, 'w:' . $name);
            if (in_array($name, ['pPr', 'tcPr', 'tblPr'], true)) {
                $parent->insertBefore($node, $parent->firstChild);
            } else {
                // Word's property elements have a defined schema order.
                $orders = [
                    'tblPr' => 'tblStyle tblpPr tblOverlap bidiVisual tblStyleRowBandSize tblStyleColBandSize tblW jc tblCellSpacing tblInd tblBorders shd tblLayout tblCellMar tblLook tblCaption tblDescription tblPrChange',
                    'tcPr' => 'cnfStyle tcW gridSpan hMerge vMerge tcBorders shd noWrap tcMar textDirection tcFitText vAlign hideMark headers cellIns cellDel cellMerge tcPrChange',
                ];
                $order = array_flip(explode(' ', $orders[$parent->localName] ?? ''));
                $before = null;
                foreach ($parent->childNodes as $child) {
                    if ($child instanceof DOMElement && isset($order[$name], $order[$child->localName]) && $order[$child->localName] > $order[$name]) {
                        $before = $child;
                        break;
                    }
                }
                $parent->insertBefore($node, $before);
            }
        }
        foreach ($attributes as $key => $value) {
            $node->setAttributeNS(self::WORD, 'w:' . $key, (string) $value);
        }
        return $node;
    }

    private function fitWordBlock(DOMElement $block, int $available, DOMXPath $xpath): void
    {
        foreach (iterator_to_array($block->childNodes) as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }
            if ($node->localName === 'tbl') {
                $properties = $this->property($node, 'tblPr');
                $requested = $xpath->query('w:tblW', $properties)->item(0);
                $width = $available;
                if ($requested && $requested->getAttributeNS(self::WORD, 'type') === 'dxa' && (int) $requested->getAttributeNS(self::WORD, 'w') > 0) {
                    $width = min($width, (int) $requested->getAttributeNS(self::WORD, 'w'));
                }
                $this->property($properties, 'tblW', ['type' => 'dxa', 'w' => $width]);
                $this->property($properties, 'tblLayout', ['type' => 'fixed']);
                $this->property($properties, 'tblInd', ['type' => 'dxa', 'w' => 0]);
                foreach (iterator_to_array($xpath->query('w:tblpPr|w:tblCellSpacing', $properties)) as $floating) {
                    $properties->removeChild($floating);
                }
                $grid = $xpath->query('w:tblGrid/w:gridCol', $node);
                $total = 0;
                foreach ($grid as $col) { $total += max(1, (int) $col->getAttributeNS(self::WORD, 'w')); }
                $widths = [];
                $used = 0;
                foreach ($grid as $i => $col) {
                    $colWidth = $i === $grid->length - 1 ? $width - $used : (int) floor($width * max(1, (int) $col->getAttributeNS(self::WORD, 'w')) / max(1, $total));
                    $widths[] = $colWidth;
                    $used += $colWidth;
                    $col->setAttributeNS(self::WORD, 'w:w', (string) $colWidth);
                }
                $cellMargins = $this->property($properties, 'tblCellMar');
                foreach (['left', 'right'] as $edge) { $this->property($cellMargins, $edge, ['type' => 'dxa', 'w' => 100]); }
                foreach ($xpath->query('w:tr', $node) as $row) {
                    foreach (iterator_to_array($xpath->query('w:trPr/w:trHeight|w:trPr/w:cantSplit', $row)) as $height) { $height->parentNode->removeChild($height); }
                    $column = (int) $xpath->evaluate('string(w:trPr/w:gridBefore/@w:val)', $row);
                    foreach ($xpath->query('w:tc', $row) as $cell) {
                        $span = max(1, (int) $xpath->evaluate('string(w:tcPr/w:gridSpan/@w:val)', $cell));
                        $cellWidth = array_sum(array_slice($widths, $column, $span)) ?: $width;
                        $cellProperties = $this->property($cell, 'tcPr');
                        $this->property($cellProperties, 'tcW', ['type' => 'dxa', 'w' => $cellWidth]);
                        foreach (iterator_to_array($xpath->query('w:noWrap|w:tcFitText', $cellProperties)) as $nowrap) { $cellProperties->removeChild($nowrap); }
                        $margins = $this->property($cellProperties, 'tcMar');
                        foreach (['left', 'right'] as $edge) { $this->property($margins, $edge, ['type' => 'dxa', 'w' => 100]); }
                        $this->fitWordBlock($cell, max(120, $cellWidth - 240), $xpath);
                        $column += $span;
                    }
                }
            } elseif ($node->localName === 'p') {
                $properties = $this->property($node, 'pPr');
                // Avoid paragraph indents leaving no room in narrow table cells.
                $indent = $xpath->query('w:ind', $properties)->item(0);
                $left = 0;
                if ($indent) {
                    $remaining = max(0, $available - min(720, $available / 2));
                    foreach (['start', 'left', 'end', 'right'] as $attribute) {
                        if ($indent->hasAttributeNS(self::WORD, $attribute)) {
                            $value = min($remaining, max(0, (int) $indent->getAttributeNS(self::WORD, $attribute)));
                            $indent->setAttributeNS(self::WORD, 'w:' . $attribute, (string) $value);
                            $remaining -= $value;
                            if (in_array($attribute, ['start', 'left'], true)) { $left = $value; }
                        }
                    }
                }
                $wp = 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing';
                $drawing = 'http://schemas.openxmlformats.org/drawingml/2006/main';
                foreach ($node->getElementsByTagNameNS($wp, 'inline') as $inline) {
                    $extent = $inline->getElementsByTagNameNS($wp, 'extent')->item(0);
                    if (!$extent) { continue; }
                    $cx = max(1, (int) $extent->getAttribute('cx'));
                    $cy = max(1, (int) $extent->getAttribute('cy'));
                    $scale = min(1, max(120, $available - $left) * 635 / $cx, self::PAGE_HEIGHT * 635 / $cy);
                    $newWidth = (int) floor($cx * $scale);
                    $newHeight = (int) floor($cy * $scale);
                    $extent->setAttribute('cx', (string) $newWidth);
                    $extent->setAttribute('cy', (string) $newHeight);
                    foreach ($inline->getElementsByTagNameNS($drawing, 'ext') as $size) {
                        if ($size->parentNode->localName === 'xfrm') {
                            $size->setAttribute('cx', (string) $newWidth);
                            $size->setAttribute('cy', (string) $newHeight);
                        }
                    }
                }
            } elseif (!in_array($node->localName, ['sectPr', 'pPr', 'tcPr', 'tblPr'], true)) {
                $this->fitWordBlock($node, $available, $xpath);
            }
        }
    }
}
