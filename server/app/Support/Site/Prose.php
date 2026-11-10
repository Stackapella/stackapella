<?php

declare(strict_types=1);

namespace App\Support\Site;

use Illuminate\Support\HtmlString;

/**
 * A guide's or a legal page's words (App\Support\Legal\LegalText's blocks) as the public site shows
 * them: always escaped, with only **bold** made bold and a [link](/path) to one of our own pages made a link (a
 * site-relative path only, never another site's address or a script).
 */
final class Prose
{
    public static function inline(string $text): HtmlString
    {
        $html = (string) preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', e($text));

        return new HtmlString((string) preg_replace('#\[([^\]]+)\]\((/(?!/)[a-z0-9/_\-]*(?:\#[a-z0-9\-]+)?)\)#', '<a href="$2" class="s-lk">$1</a>', $html));
    }
}
