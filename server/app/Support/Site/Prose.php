<?php

declare(strict_types=1);

namespace App\Support\Site;

use Illuminate\Support\HtmlString;

/**
 * A guide's or a legal page's words (App\Support\Legal\LegalText's blocks) as the public site shows
 * them: always escaped, with only **bold** made bold.
 */
final class Prose
{
    public static function inline(string $text): HtmlString
    {
        return new HtmlString((string) preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', e($text)));
    }
}
