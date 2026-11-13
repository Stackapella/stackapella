<?php

declare(strict_types=1);

namespace App\Support\Site;

use Illuminate\Http\Request;

/**
 * Suggest a correction (docs/corrections-plan.md): a page that takes corrections shares its type and title here, and
 * the site's footer shows the link and its sheet just above itself (components/site/correction.blade.php). Kept on the
 * request, never shared view data, so no other page (a long-running worker's next request) ever inherits it.
 */
final class Correction
{
    /** The page types the inbox knows (App\Models\Correction::PAGE_TYPES). */
    public static function share(string $type, string $title): void
    {
        app(Request::class)->attributes->set('correction', ['type' => $type, 'title' => $title]);
    }

    /** @return array{type: string, title: string}|null this request's page, if it takes corrections */
    public static function here(): ?array
    {
        $here = app(Request::class)->attributes->get('correction');
        $type = is_array($here) ? ($here['type'] ?? null) : null;
        $title = is_array($here) ? ($here['title'] ?? null) : null;

        return is_string($type) && is_string($title) ? ['type' => $type, 'title' => $title] : null;
    }
}
