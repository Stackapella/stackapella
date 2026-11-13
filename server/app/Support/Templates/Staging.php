<?php

declare(strict_types=1);

namespace App\Support\Templates;

use App\Actions\Templates\CarryVoicesOver;
use App\Models\Template;
use App\Models\TemplateVersion;

/**
 * A library song's staged next version (docs/decisions.md, 2026-11-12, Amazing Grace: "keep the current ones but fix
 * when someone re-records"): the content's `<id>.next.json`, never current, that new recordings target. A part that
 * sings the same in it (or only gains breaths) needs no new voice: its voices move over at the switch
 * (CarryVoicesOver); the others are wanted.
 */
final class Staging
{
    /**
     * The parts whose notes change in the staged version, by key; empty when the song has none staged.
     *
     * @return list<string>
     */
    public static function changed(Template $template): array
    {
        $staged = $template->stagedVersion;
        if ($staged === null) {
            return [];
        }
        $live = (array) ($template->currentVersion->data ?? []);
        $next = (array) $staged->data;

        return array_values(array_map(static fn (array $part): string => (string) $part['key'], array_filter(
            (array) ($next['parts'] ?? []),
            static fn (mixed $part): bool => is_array($part) && is_string($part['key'] ?? null)
                && ! CarryVoicesOver::sameMusic($live, $next, $part['key']) && CarryVoicesOver::breaths($live, $next, $part['key']) === null,
        )));
    }

    /** Whether a version may be recorded: reviewed, or the song's staged next one. */
    public static function recordable(Template $template, ?TemplateVersion $version): bool
    {
        return $version !== null && ($version->reviewed || $version->id === $template->staged_version_id);
    }
}
