<?php

namespace BookStack\Uploads;

use DOMDocument;
use DOMElement;
use DOMXPath;

/** Preserve formatting which Writer's HTML reader drops or resets in tables. */
class WordExportAppearance
{
    private const WORD = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private function styles(DOMElement $node): array
    {
        $styles = [];
        foreach (explode(';', $node->getAttribute('style')) as $declaration) {
            $parts = explode(':', $declaration, 2);
            if (count($parts) === 2) { $styles[strtolower(trim($parts[0]))] = trim($parts[1]); }
        }
        return $styles;
    }

    public function prepareHtml(DOMElement $body): array
    {
        $tables = [];
        $xpath = new DOMXPath($body->ownerDocument);
        foreach ($body->getElementsByTagName('table') as $table) {
            $format = ['borders' => $this->borders($table, ['style' => 'none', 'width' => '0', 'color' => '#dddddd']), 'cells' => []];
            $occupied = [];
            foreach ($xpath->query('./tr|./thead/tr|./tbody/tr|./tfoot/tr', $table) as $rowIndex => $row) {
                $column = 0;
                foreach ($xpath->query('./td|./th', $row) as $cell) {
                    while (($occupied[$column] ?? 0) > $rowIndex) { $column++; }
                    $span = max(1, min(100, (int) $cell->getAttribute('colspan')));
                    $rows = max(1, min(1000, (int) $cell->getAttribute('rowspan')));
                    // These defaults match resources/sass/_tables.scss on the web.
                    $borders = $this->borders($cell, ['style' => 'solid', 'width' => '1px', 'color' => '#dddddd']);
                    for ($offset = 0; $offset < $rows; $offset++) {
                        $edges = $borders;
                        if ($offset > 0) { $edges['top'] = ['val' => 'nil']; }
                        if ($offset < $rows - 1) { $edges['bottom'] = ['val' => 'nil']; }
                        $format['cells'][$rowIndex + $offset][$column] = $edges;
                    }
                    for ($i = $column; $i < $column + $span; $i++) { $occupied[$i] = $rowIndex + $rows; }
                    $column += $span;
                }
            }
            $tables[] = $format;
        }
        $this->fonts($body, 'Segoe UI', 10.5);
        return $tables;
    }

    private function fontSize(string $value, float $parent): float
    {
        $value = strtolower(trim($value));
        $keywords = ['xx-small' => 6.75, 'x-small' => 7.5, 'small' => 9.75, 'medium' => 12, 'large' => 13.5, 'x-large' => 18, 'xx-large' => 24, 'xxx-large' => 36];
        if (isset($keywords[$value])) { return $keywords[$value]; }
        if ($value === 'smaller') { return $parent / 1.2; }
        if ($value === 'larger') { return $parent * 1.2; }
        if (!preg_match('/^([0-9]+(?:\.[0-9]+)?)\s*(px|pt|em|rem|%|in|cm|mm|pc)$/', $value, $match)) { return $parent; }
        $scales = ['px' => 0.75, 'pt' => 1, 'em' => $parent, 'rem' => 12, '%' => $parent / 100, 'in' => 72, 'cm' => 72 / 2.54, 'mm' => 72 / 25.4, 'pc' => 12];
        return max(0.5, min(1638, (float) $match[1] * $scales[$match[2]]));
    }

    private function fontFamily(string $value, string $parent): string
    {
        foreach (explode(',', $value) as $candidate) {
            $candidate = trim($candidate, " \t\n\r\0\x0B\"'");
            $lower = strtolower($candidate);
            if ($candidate === '' || in_array($lower, ['-apple-system', 'blinkmacsystemfont'], true)) { continue; }
            if (in_array($lower, ['inherit', 'unset', 'initial'], true)) { return $parent; }
            $generic = ['sans-serif' => 'Segoe UI', 'system-ui' => 'Segoe UI', 'serif' => 'Times New Roman', 'monospace' => 'Lucida Console'];
            return $generic[$lower] ?? $candidate;
        }
        return $parent;
    }

    private function fonts(DOMElement $node, string $family, float $size): void
    {
        $styles = $this->styles($node);
        $tag = strtolower($node->tagName);
        $parentSize = $size;
        $headings = ['h1' => 3.425, 'h2' => 2.8275, 'h3' => 2.333, 'h4' => 1.666, 'h5' => 1.4, 'h6' => 1.2];
        if (isset($headings[$tag])) { $size *= $headings[$tag]; }
        if ($tag === 'pre' || $tag === 'code') {
            $family = 'Lucida Console';
            if ($tag === 'pre') { $size = 9; } // Web pre defaults to 12px.
            elseif ($node->parentNode->nodeName !== 'pre') { $size *= 0.84; }
        }
        if ($tag === 'font') {
            if ($node->hasAttribute('face')) { $family = $this->fontFamily($node->getAttribute('face'), $family); }
            if ($node->hasAttribute('size')) {
                $legacy = [1 => 7.5, 2 => 9.75, 3 => 12, 4 => 13.5, 5 => 18, 6 => 24, 7 => 36];
                $size = $legacy[max(1, min(7, (int) $node->getAttribute('size')))];
            }
        }
        if (isset($styles['font-family'])) { $family = $this->fontFamily($styles['font-family'], $family); }
        // CSS relative sizes are relative to the parent, not the heading's default size.
        if (isset($styles['font-size'])) {
            $size = $this->fontSize($styles['font-size'], $parentSize);
        }
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $this->fonts($child, $family, $size);
            } elseif ($child instanceof \DOMText && trim($child->nodeValue) !== '') {
                $span = $node->ownerDocument->createElement('span');
                // Word stores font sizes in half-points. Resolve CSS keywords,
                // percentages and px before Writer applies table/font defaults.
                $span->setAttribute('style', 'font-family: "' . str_replace('"', '', $family) . '"; font-size: ' . (round($size * 2) / 2) . 'pt');
                $node->replaceChild($span, $child);
                $span->appendChild($child);
            }
        }
    }

    private function fourValues(string $value): array
    {
        $parts = preg_split('/\s+(?![^()]*\))/', trim($value));
        return [$parts[0], $parts[1] ?? $parts[0], $parts[2] ?? $parts[0], $parts[3] ?? $parts[1] ?? $parts[0]];
    }

    private function shorthand(string $value, array $border): array
    {
        foreach (preg_split('/\s+(?![^()]*\))/', trim($value)) as $part) {
            if (preg_match('/^(none|hidden|solid|dashed|dotted|double|groove|ridge|inset|outset)$/i', $part)) { $border['style'] = strtolower($part); }
            elseif (preg_match('/^(?:[0-9.]+(?:px|pt|in|cm|mm|pc)?|thin|medium|thick)$/i', $part)) { $border['width'] = $part; }
            else { $border['color'] = $part; }
        }
        return $border;
    }

    private function color(string $value): string
    {
        $value = strtolower(trim($value));
        if (preg_match('/^#([a-f0-9]{3}|[a-f0-9]{6})$/', $value, $m)) {
            return strtoupper(strlen($m[1]) === 3 ? preg_replace('/(.)/', '$1$1', $m[1]) : $m[1]);
        }
        if (preg_match('/^rgba?\(\s*([0-9.]+)\s*,\s*([0-9.]+)\s*,\s*([0-9.]+)(?:\s*,\s*([0-9.]+))?\s*\)$/', $value, $m)) {
            $alpha = isset($m[4]) ? min(1, max(0, (float) $m[4])) : 1;
            return sprintf('%02X%02X%02X', ...array_map(function ($channel) use ($alpha) { return (int) round(min(255, max(0, (float) $channel)) * $alpha + 255 * (1 - $alpha)); }, [$m[1], $m[2], $m[3]]));
        }
        $names = ['black' => '000000', 'white' => 'FFFFFF', 'red' => 'FF0000', 'green' => '008000', 'blue' => '0000FF', 'yellow' => 'FFFF00', 'gray' => '808080', 'grey' => '808080', 'silver' => 'C0C0C0', 'navy' => '000080', 'teal' => '008080', 'purple' => '800080', 'maroon' => '800000', 'lime' => '00FF00', 'aqua' => '00FFFF', 'fuchsia' => 'FF00FF', 'olive' => '808000', 'orange' => 'FFA500'];
        return $names[$value] ?? 'DDDDDD';
    }

    private function borders(DOMElement $node, array $default): array
    {
        $styles = $this->styles($node);
        if ($node->tagName === 'table' && $node->hasAttribute('border')) {
            $default['width'] = max(0, (float) $node->getAttribute('border')) . 'px';
            $default['style'] = (float) $node->getAttribute('border') > 0 ? 'solid' : 'none';
        }
        $edges = array_fill_keys(['top', 'right', 'bottom', 'left'], $default);
        foreach ($styles as $property => $value) {
            if ($property === 'border') {
                foreach ($edges as $edge => $border) { $edges[$edge] = $this->shorthand($value, $border); }
            } elseif (preg_match('/^border-(width|style|color)$/', $property, $m)) {
                foreach (array_combine(array_keys($edges), $this->fourValues($value)) as $edge => $part) { $edges[$edge][$m[1]] = $part; }
            } elseif (preg_match('/^border-(top|right|bottom|left)(?:-(width|style|color))?$/', $property, $m)) {
                if (isset($m[2])) { $edges[$m[1]][$m[2]] = $value; }
                else { $edges[$m[1]] = $this->shorthand($value, $edges[$m[1]]); }
            }
        }
        foreach ($edges as $edge => $border) {
            $widths = ['thin' => '1px', 'medium' => '3px', 'thick' => '5px'];
            $width = $this->fontSize($widths[$border['width']] ?? $border['width'], 0);
            if ($width <= 0 || preg_match('/^0(?:\.0+)?(?:px|pt|in|cm|mm|pc)?$/i', $border['width']) || in_array($border['style'], ['none', 'hidden'], true) || $border['color'] === 'transparent') { $edges[$edge] = ['val' => 'nil']; }
            else { $edges[$edge] = ['val' => ['dashed' => 'dashed', 'dotted' => 'dotted', 'double' => 'double'][$border['style']] ?? 'single', 'sz' => max(2, min(96, (int) round($width * 8))), 'color' => $this->color($border['color'])]; }
        }
        return $edges;
    }

    private function property(DOMElement $parent, string $name): DOMElement
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $name && $child->namespaceURI === self::WORD) { return $child; }
        }
        $node = $parent->ownerDocument->createElementNS(self::WORD, 'w:' . $name);
        $before = null;
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && in_array($child->localName, ['shd', 'tblLayout', 'tblCellMar', 'tblLook', 'noWrap', 'tcMar', 'textDirection', 'vAlign', 'hideMark'], true)) { $before = $child; break; }
        }
        $parent->insertBefore($node, $before);
        return $node;
    }

    private function writeBorders(DOMElement $parent, string $name, array $edges): void
    {
        $container = $this->property($parent, $name);
        while ($container->firstChild) { $container->removeChild($container->firstChild); }
        foreach (['top', 'left', 'bottom', 'right', 'insideH', 'insideV'] as $edge) {
            if (!isset($edges[$edge])) { continue; }
            $attributes = $edges[$edge];
            $node = $parent->ownerDocument->createElementNS(self::WORD, 'w:' . $edge);
            foreach ($attributes as $attribute => $value) { $node->setAttributeNS(self::WORD, 'w:' . $attribute, (string) $value); }
            $container->appendChild($node);
        }
    }

    public function applyDocument(DOMDocument $document, array $formats): void
    {
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', self::WORD);
        foreach ($xpath->query('//w:tbl') as $index => $table) {
            if (!isset($formats[$index])) { continue; }
            $format = $formats[$index];
            $properties = $xpath->query('w:tblPr', $table)->item(0);
            $this->writeBorders($properties, 'tblBorders', $format['borders'] + ['insideH' => ['val' => 'nil'], 'insideV' => ['val' => 'nil']]);
            $rows = $xpath->query('w:tr', $table);
            $columns = $xpath->query('w:tblGrid/w:gridCol', $table)->length;
            foreach ($rows as $rowIndex => $row) {
                $column = (int) $xpath->evaluate('string(w:trPr/w:gridBefore/@w:val)', $row);
                foreach ($xpath->query('w:tc', $row) as $cell) {
                    $span = max(1, (int) $xpath->evaluate('string(w:tcPr/w:gridSpan/@w:val)', $cell));
                    if (isset($format['cells'][$rowIndex][$column])) {
                        $edges = $format['cells'][$rowIndex][$column];
                        // In Word a cell's nil border suppresses the table outline.
                        // Resolve the visible outer edge before writing cell borders.
                        $outside = ['top' => $rowIndex === 0, 'bottom' => $rowIndex === $rows->length - 1, 'left' => $column === 0, 'right' => $column + $span === $columns];
                        $priority = ['nil' => 0, 'dotted' => 1, 'dashed' => 2, 'single' => 3, 'double' => 4];
                        foreach ($outside as $edge => $isOutside) {
                            $outer = $format['borders'][$edge];
                            if ($isOutside && $outer['val'] !== 'nil' && ($edges[$edge]['val'] === 'nil'
                                || ($outer['sz'] ?? 0) > ($edges[$edge]['sz'] ?? 0)
                                || (($outer['sz'] ?? 0) === ($edges[$edge]['sz'] ?? 0) && $priority[$outer['val']] > $priority[$edges[$edge]['val']]))) {
                                $edges[$edge] = $outer;
                            }
                        }
                        $this->writeBorders($xpath->query('w:tcPr', $cell)->item(0), 'tcBorders', $edges);
                    }
                    $column += $span;
                }
            }
        }
    }
}
