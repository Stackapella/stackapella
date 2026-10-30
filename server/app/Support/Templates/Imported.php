<?php

declare(strict_types=1);

namespace App\Support\Templates;

use App\Exceptions\Refusal;
use App\Models\Harmony;
use App\Models\Project;
use App\Models\Template;
use App\Models\TemplateVersion;
use Illuminate\Support\Facades\Context;

/**
 * Songs made from imported sheet music (Hillel: "please try your best to keep us out of any
 * trouble"): private to their owner and the choir groups they're added to. Never unlisted, public,
 * in the library, an idea or the gallery, and nothing sung from one is shared beyond the singer.
 * Every way out refuses with 422 imported_private.
 */
final class Imported
{
    public const SOURCE = 'sheet-music';

    public static function refuse(): never
    {
        throw new Refusal(422, 'imported_private');
    }

    /**
     * Whether a project was made from an imported song (its own copy keeps the song's slug).
     */
    public static function project(Project $project): bool
    {
        return $project->template_slug !== null && self::once('slugs', $project->template_slug, static fn (): bool => Template::query()->where('slug', $project->template_slug)->whereNotNull('imported_at')->exists());
    }

    /**
     * Whether a harmony was sung from an imported song: its version's song, or its project's.
     */
    public static function harmony(Harmony $harmony): bool
    {
        if ($harmony->relationLoaded('templateVersion') && $harmony->templateVersion?->relationLoaded('template') === true) {
            return $harmony->templateVersion->template?->isImported() === true;
        }

        if ($harmony->template_version_id !== null) {
            $id = $harmony->template_version_id;

            return self::once('versions', (string) $id, static fn (): bool => TemplateVersion::query()->whereKey($id)->whereHas('template', fn ($query) => $query->whereNotNull('imported_at'))->exists());
        }

        $harmony->loadMissing('project');

        return $harmony->project !== null && self::project($harmony->project);
    }

    /**
     * Asked once a request (or job): a page asks about many harmonies of the same song.
     *
     * @param  callable(): bool  $ask
     */
    private static function once(string $kind, string $key, callable $ask): bool
    {
        /** @var array<string, bool> $known */
        $known = (array) (Context::getHidden('imported.'.$kind) ?? []);

        if (! array_key_exists($key, $known)) {
            $known[$key] = $ask();
            Context::addHidden('imported.'.$kind, $known);
        }

        return $known[$key];
    }
}
