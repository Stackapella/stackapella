<?php

declare(strict_types=1);

namespace App\Support\Rights;

use App\Models\Template;
use App\Support\Flags;
use App\Support\Templates\Catalogue;
use App\Support\Trust\Country;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Which songs, and which songs' words, are withheld where the visitor is (docs/geo-rights-plan.md, step 3), behind the
 * `geo_rights` switch. The app and the site never learn the country, only what's out: a song's slug, or its words.
 * No country (local, or a request not through the edge), XX and T1 take every country's rule at once.
 */
final class RightsHere
{
    public function __construct(private readonly Flags $flags, private readonly Catalogue $catalogue) {}

    public function on(): bool
    {
        return $this->flags->on('geo_rights');
    }

    public static function country(Request $request): string
    {
        return Country::of($request) ?? '';
    }

    /**
     * Whether the song is withheld in the country: null when it's free there, the switch is off, or it's someone's own
     * song (their rights confirmation covers it); else when it becomes free (null when that can't be known).
     *
     * @param  list<string>|null  $roles  SongRights::WORDS for the words alone
     * @return array{freeFrom: int|null}|null
     */
    public function withheld(Template $template, string $country, ?array $roles = null): ?array
    {
        if (! $this->on() || $template->owner_id !== null || $template->currentVersion === null) {
            return null;
        }
        $status = SongRights::status((array) $template->currentVersion->data, $country, null, $roles);

        return $status['free'] ? null : ['freeFrom' => $status['until']];
    }

    /** Whether the song's words are withheld in the country (the page's lyric lines, its full words, its meanings). */
    public function wordsWithheld(Template $template, string $country): bool
    {
        return $this->withheld($template, $country, SongRights::WORDS) !== null;
    }

    /** Whether the song is protected somewhere, so its page differs by country and no shared cache may keep it. */
    public function variesByCountry(Template $template): bool
    {
        return $this->withheld($template, '') !== null;
    }

    /**
     * The catalogue's songs withheld in the country, by slug; kept an hour by country, year, the songs' versions and
     * the terms, so a new version or a change to the table answers at once.
     *
     * @return list<string>
     */
    public function withheldSlugs(string $country): array
    {
        if (! $this->on()) {
            return [];
        }
        // Keyed by the catalogue's fingerprint (one cheap row), so the songs' notes are read only when it changes: this
        // runs at every app start (auth.config) and for the library.
        $key = 'rights:withheld:'.($country === '' ? '-' : $country).':'.now()->year.':'.hash('sha256', $this->catalogue->fingerprint().'|'.self::terms());

        /** @var list<string> */
        return Cache::remember($key, now()->addHour(), fn (): array => $this->catalogue->songs()
            ->filter(fn (Template $template): bool => $this->withheld($template, $country) !== null)
            ->map(static fn (Template $template): string => (string) $template->slug)
            ->values()->all());
    }

    /** The terms table's fingerprint, read once a process. */
    private static function terms(): string
    {
        static $hash = null;

        return $hash ??= hash('xxh128', (string) json_encode(config('copyright_terms.countries')));
    }
}
