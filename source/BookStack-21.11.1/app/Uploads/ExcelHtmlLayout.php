<?php

namespace BookStack\Uploads;

use DOMDocument;
use DOMElement;
use DOMXPath;

class ExcelHtmlLayout
{
    public static function moveCommentsToTitles(DOMDocument $doc): void
    {
        foreach ((new DOMXPath($doc))->query('//comment[ancestor::td or ancestor::th]') as $comment) {
            $cell = $comment->parentNode;
            while (!in_array($cell->nodeName, ['td', 'th'], true)) { $cell = $cell->parentNode; }
            $note = trim($comment->textContent);
            if ($note !== '') { $cell->setAttribute('title', trim($cell->getAttribute('title') . "\n" . $note)); }
            $comment->parentNode->removeChild($comment);
        }
    }

    public static function prepare(string $html): string
    {
        if (!ExcelPageLayout::matches($html)) { return $html; }
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            if (!$doc->loadHTML(mb_convert_encoding('<body>' . $html . '</body>', 'HTML-ENTITIES', 'UTF-8'), LIBXML_NONET)) { return $html; }
        } finally {
            libxml_clear_errors(); libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($doc);
        foreach ($xpath->query('//table') as $tableIndex => $table) {
            $columns = 0; $width = 0;
            foreach ($xpath->query('./colgroup | ./col', $table) as $group) {
                $groups = $xpath->query('./col', $group);
                $items = $groups->length ? iterator_to_array($groups) : [$group];
                foreach ($items as $col) {
                    $pixels = self::width($col) ?: self::width($group);
                    $span = max(1, min(16384, (int) $col->getAttribute('span')));
                    $columns += $span;
                    $width += $pixels * $span;
                }
            }
            // Only source grids with explicit Calc column metadata are repaired.
            if ($columns < 1 || $columns > 16384) { continue; }
            if ($width > 0 && $width <= 200000) {
                if (!preg_match('/(?:^|\s)excel-source-grid(?:\s|$)/', $table->getAttribute('class'))) {
                    $table->setAttribute('class', trim($table->getAttribute('class') . ' excel-source-grid'));
                }
                $style = $table->getAttribute('style');
                if (!preg_match('/(?:^|;)\s*width\s*:/i', $style)) { $style .= ';width:' . $width . 'px'; }
                if (!preg_match('/(?:^|;)\s*table-layout\s*:/i', $style)) { $style .= ';table-layout:fixed'; }
                $table->setAttribute('style', trim($style, ';'));
            }
            $carry = []; $rowIndex = 0; $inserted = 0;
            foreach (iterator_to_array($xpath->query('.//tr', $table)) as $row) {
                $ancestor = $row->parentNode;
                while ($ancestor && $ancestor->nodeName !== 'table') { $ancestor = $ancestor->parentNode; }
                if ($ancestor !== $table) { continue; }
                $cells = $xpath->query('./td | ./th', $row);
                // Old editor output can omit a row entirely covered by rowspans.
                // Restore that row, rather than placing its successor beyond the grid.
                while ($cells->length && count($carry) === $columns && $inserted < 1000) {
                    $empty = $doc->createElement('tr');
                    $empty->setAttribute('id', 'bkmrk-excel-empty-' . $tableIndex . '-' . $rowIndex++);
                    $row->parentNode->insertBefore($empty, $row);
                    self::advance($carry);
                    $inserted++;
                }
                if (!$cells->length && !$row->hasAttribute('id')) {
                    // TinyMCE 4 removes empty TRs unless they carry an ID. Adding
                    // a TD would create a false column underneath fully merged cells.
                    $row->setAttribute('id', 'bkmrk-excel-empty-' . $tableIndex . '-' . $rowIndex);
                }
                $column = 0;
                foreach ($cells as $cell) {
                    while (isset($carry[$column])) { $column++; }
                    $colspan = max(1, min(16384, (int) $cell->getAttribute('colspan')));
                    $rowspan = max(1, min(1048576, (int) $cell->getAttribute('rowspan')));
                    if ($rowspan > 1 && $column + $colspan <= $columns) {
                        for ($c = $column; $c < $column + $colspan; $c++) { $carry[$c] = $rowspan; }
                    }
                    $column += $colspan;
                }
                self::advance($carry);
                $rowIndex++;
            }
        }
        $result = '';
        foreach ($doc->getElementsByTagName('body')->item(0)->childNodes as $node) { $result .= $doc->saveHTML($node); }
        return $result;
    }

    private static function advance(array &$carry): void
    {
        foreach ($carry as $column => $remaining) {
            if ($remaining <= 1) { unset($carry[$column]); } else { $carry[$column]--; }
        }
    }

    private static function width(DOMElement $element): int
    {
        if (preg_match('/(?:^|;)\s*width\s*:\s*([0-9.]+)px\s*(?:;|$)/i', $element->getAttribute('style'), $match)) {
            return max(0, min(16384, (int) round((float) $match[1])));
        }
        $width = $element->getAttribute('width');
        return preg_match('/^[0-9]+$/', $width) ? min(16384, (int) $width) : 0;
    }
}
