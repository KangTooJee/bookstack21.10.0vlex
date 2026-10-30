<?php

namespace BookStack\Entities\Tools;

class SearchText
{
    public static function separateElements(string $html): string
    {
        return preg_replace('/<\/?(?:td|th|tr|p|div|br|h[1-6]|li|table|section|hr)\b[^>]*>/i', ' $0 ', $html);
    }

    public static function fromHtml(string $html): string
    {
        $html = preg_replace('/<(script|style|head)\b[^>]*>.*?<\/\1\s*>/is', ' ', $html);
        $text = html_entity_decode(strip_tags(self::separateElements($html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/[\s\x{00a0}]+/u', ' ', $text));
    }
}
