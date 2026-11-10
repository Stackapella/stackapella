<?php

declare(strict_types=1);

namespace App\Support\Tools;

use App\Support\Site\SitePages;

/**
 * What an embedded tool's page hands embed.js's embedFrame (resources/views/embed/tool.blade.php): the tool, its
 * module and mount, its words from lang, the credit's words, the app's address and the counts' ref prefix.
 */
final class EmbedBoot
{
    /**
     * @param  array<string, mixed>  $embed  ToolEmbedController's page data
     * @return array<string, mixed>
     */
    public static function of(array $embed): array
    {
        $page = SitePages::tool((string) $embed['tool']);

        return [
            'tool' => $embed['tool'],
            'src' => $page['module'],
            'mount' => $page['mount'],
            'words' => (array) ($page['card'] ?? []),
            'credit' => (string) __('site.tools.index.by'),
            'appUrl' => $embed['appUrl'],
            'refPrefix' => $embed['refPrefix'],
        ];
    }
}
