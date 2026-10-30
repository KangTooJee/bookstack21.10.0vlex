<?php

namespace BookStack\Uploads;

use DOMDocument;
use DOMElement;
use DOMXPath;

/** Flatten the simple document CSS emitted by Writer before removing <head>. */
class OfficeHtmlStyles
{
    public function inline(DOMDocument $doc): void
    {
        $xpath = new DOMXPath($doc);
        $css = '';
        foreach ($doc->getElementsByTagName('style') as $style) { $css .= $style->textContent . "\n"; }
        $css = preg_replace('~/\*.*?\*/~s', '', $css);
        $css = preg_replace('/@[^{}]+\{[^{}]*\}/s', '', $css);
        $rules = [];
        preg_match_all('/([^{}]+)\{([^{}]*)\}/s', $css, $rules, PREG_SET_ORDER);
        $applied = new \SplObjectStorage();
        foreach ($rules as $rule) {
            foreach (explode(',', $rule[1]) as $selector) {
                $selector = trim($selector);
                // Intentionally bounded: tags, .classes and descendant selectors.
                if (!preg_match('/^[a-zA-Z0-9_.\s-]+$/', $selector)) { continue; }
                $parts = [];
                foreach (preg_split('/\s+/', $selector) as $part) {
                    preg_match('/^([a-zA-Z][\w-]*)?((?:\.[\w-]+)*)$/', $part, $match);
                    if (!$match) { continue 2; }
                    $step = strtolower($match[1] ?: '*');
                    foreach (array_filter(explode('.', $match[2] ?? '')) as $class) {
                        $step .= '[contains(concat(" ", normalize-space(@class), " "), " ' . $class . ' ")]';
                    }
                    $parts[] = $step;
                }
                $specificity = substr_count($selector, '.') * 100 + count($parts);
                foreach ($xpath->query('//' . implode('//', $parts)) as $node) {
                    $values = $applied[$node] ?? [];
                    foreach (explode(';', $rule[2]) as $declaration) {
                        $pair = explode(':', $declaration, 2);
                        if (count($pair) !== 2) { continue; }
                        $property = strtolower(trim($pair[0]));
                        if (!isset($values[$property]) || $specificity >= $values[$property][0]) { $values[$property] = [$specificity, trim($pair[1])]; }
                    }
                    $applied[$node] = $values;
                }
            }
        }
        foreach ($applied as $node) {
            $style = '';
            foreach ($applied[$node] as $property => $value) { $style .= $property . ': ' . $value[1] . '; '; }
            $node->setAttribute('style', $style . $node->getAttribute('style'));
        }
        // HTML align is a presentation hint: a CSS rule must not overwrite it.
        foreach ($xpath->query('//*[@align]') as $node) {
            if (in_array(strtolower($node->tagName), ['p', 'div', 'td', 'th'], true) && in_array($node->getAttribute('align'), ['left','right','center','justify'], true)) {
                $node->setAttribute('style', $node->getAttribute('style') . '; text-align: ' . $node->getAttribute('align'));
            }
        }
        // TinyMCE 4 converts <font size=3 style="font-size:13pt"> to medium,
        // discarding the explicit point size. Convert to spans before it sees it.
        foreach (iterator_to_array($doc->getElementsByTagName('font')) as $font) {
            $span = $doc->createElement('span');
            $style = '';
            if ($font->hasAttribute('face')) { $style .= 'font-family: ' . $font->getAttribute('face') . ';'; }
            if ($font->hasAttribute('color')) { $style .= 'color: ' . $font->getAttribute('color') . ';'; }
            $sizes = [1 => 7.5, 2 => 9.75, 3 => 12, 4 => 13.5, 5 => 18, 6 => 24, 7 => 36];
            if (isset($sizes[(int) $font->getAttribute('size')])) { $style .= 'font-size: ' . $sizes[(int) $font->getAttribute('size')] . 'pt;'; }
            $span->setAttribute('style', $style . $font->getAttribute('style'));
            while ($font->firstChild) { $span->appendChild($font->firstChild); }
            $font->parentNode->replaceChild($span, $font);
        }
    }

    public function metadata(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) { return []; }
        try {
            $document = new DOMDocument();
            $styles = new DOMDocument();
            $documentXml = $zip->getFromName('word/document.xml');
            $stylesXml = $zip->getFromName('word/styles.xml');
            if (!$documentXml || !$stylesXml || !$document->loadXML($documentXml, LIBXML_NONET)
                || !$styles->loadXML($stylesXml, LIBXML_NONET)) { return []; }
            $word = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
            $x = new DOMXPath($document); $x->registerNamespace('w', $word);
            $s = new DOMXPath($styles); $s->registerNamespace('w', $word);
            $size = $s->evaluate('string(//w:docDefaults/w:rPrDefault/w:rPr/w:sz/@w:val)');
            $family = $s->evaluate('string(//w:docDefaults/w:rPrDefault/w:rPr/w:rFonts/@w:ascii)');
            // Word may omit a default family but explicitly use one throughout.
            if (!$family) {
                $counts = [];
                foreach ($x->query('//w:rFonts/@w:ascii') as $font) { $counts[$font->nodeValue] = ($counts[$font->nodeValue] ?? 0) + 1; }
                arsort($counts); $family = $counts ? array_key_first($counts) : '';
            }
            $page = [];
            foreach (['w:pgSz/@w:w','w:pgSz/@w:h','w:pgMar/@w:top','w:pgMar/@w:right','w:pgMar/@w:bottom','w:pgMar/@w:left'] as $attribute) {
                $page[] = (int) $x->evaluate('string((//w:sectPr)[last()]/' . $attribute . ')');
            }
            return ['font' => $family, 'size' => $size ? (float) $size / 2 : 12, 'page' => $page];
        } finally { $zip->close(); }
    }

    public function wrap(DOMElement $body, array $metadata): void
    {
        if (!$metadata) { return; }
        $wrapper = $body->ownerDocument->createElement('div');
        $wrapper->setAttribute('class', 'office-document');
        $wrapper->setAttribute('data-office-document', 'true');
        $wrapper->setAttribute('data-office-page', implode(',', $metadata['page']));
        $width = ($metadata['page'][0] - $metadata['page'][3] - $metadata['page'][5]) / 15;
        $style = 'font-size: ' . $metadata['size'] . 'pt; color: #000; line-height: normal;';
        if ($metadata['font']) { $style .= ' font-family: "' . str_replace('"', '', $metadata['font']) . '";'; }
        if ($width > 100) { $style .= ' width: ' . round($width, 2) . 'px;'; }
        $wrapper->setAttribute('style', $style);
        foreach ((new DOMXPath($body->ownerDocument))->query('.//td|.//th', $body) as $cell) {
            $cell->setAttribute('style', 'border: 0; padding: 0; ' . $cell->getAttribute('style'));
        }
        while ($body->firstChild) { $wrapper->appendChild($body->firstChild); }
        $body->appendChild($wrapper);
    }
}
