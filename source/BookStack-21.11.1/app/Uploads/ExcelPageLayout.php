<?php

namespace BookStack\Uploads;

class ExcelPageLayout
{
    public static function matches(string $html): bool
    {
        if (strpos($html, 'name="bookstack-excel-content"') !== false) {
            return true;
        }

        // Calc pages imported before the explicit marker was introduced.
        return preg_match('/<table\b(?=[^>]*\bcellspacing="0")(?=[^>]*\bborder="0")/i', $html) === 1
            && preg_match('/<colgroup\b[^>]*width="[0-9]+"/i', $html) === 1;
    }
}
