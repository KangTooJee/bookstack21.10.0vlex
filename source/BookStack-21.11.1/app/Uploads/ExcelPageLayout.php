<?php

namespace BookStack\Uploads;

class ExcelPageLayout
{
    public static function assetUrl(string $file): string
    {
        // App version is pinned, so its normal asset query cannot invalidate
        // browser caches when the standalone Excel layout assets are updated.
        return versioned_asset('dist/' . $file) . '&excel=' . substr(hash_file('sha256', public_path('dist/' . $file)), 0, 12);
    }

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
