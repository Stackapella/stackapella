<?php

declare(strict_types=1);

namespace App\Support\Languages;

use App\Support\Flags;

/**
 * The app's languages (docs/languages-plan.md; Hillel, 2026-11-11: Spanish first, then German). An
 * account may keep any of them as its language (account.update `locale`), so a draft can be checked
 * before it opens; the app offers one, and the site serves it, only once its switch is on. English is
 * always on and is every other language's fallback, key by key (config/app.php `fallback_locale`).
 */
final class Languages
{
    /** Every language an account may choose, English first. */
    public const ALL = ['en', 'es', 'de'];

    /** The switch that opens each language other than English. */
    public const SWITCHES = ['es' => 'lang_es', 'de' => 'lang_de'];

    public function __construct(private readonly Flags $flags) {}

    /**
     * The languages open to everyone: English, then each one whose switch is on.
     *
     * @return list<string>
     */
    public function offered(): array
    {
        return ['en', ...array_keys(array_filter(self::SWITCHES, fn (string $flag): bool => $this->flags->on($flag)))];
    }

    public function isOffered(string $language): bool
    {
        return in_array($language, $this->offered(), true);
    }
}
