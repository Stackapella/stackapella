<?php

declare(strict_types=1);

namespace App\Support\Languages;

use Illuminate\Contracts\Translation\Loader;

/**
 * The server's words in another language (docs/languages-plan.md), kept as the courses keep theirs
 * (stackapella-courses, i18n/<lang>/): one map per group, lang/<lang>/<group>.json, from the English
 * sentence to its translation. A sentence is in the language only while its English is the one it
 * was translated from: change the English and the English shows until the map has the new one, so a
 * stale translation is never shown. A translation that drops or adds a placeholder (`:name`) or a
 * plural's `|` is never used either. Where one English sentence means two things in a group, one key
 * may have its own: `"@<key>:<English>": "translation"` (e.g. `"@warmups.card.low:Low": "Grave"`),
 * used only while that key's English is still the one named. Everything not translated falls back to English key by key
 * (config/app.php `fallback_locale`). English, vendor namespaces and JSON strings load as Laravel
 * loads them.
 */
final class TranslationMaps implements Loader
{
    public function __construct(private readonly Loader $files, private readonly string $langPath) {}

    /**
     * @return array<string, mixed>
     */
    public function load($locale, $group, $namespace = null): array
    {
        if ($locale === 'en' || $group === '*' || ($namespace !== null && $namespace !== '*') || ! in_array($locale, Languages::ALL, true)) {
            return $this->files->load($locale, $group, $namespace);
        }

        return self::translate($this->files->load('en', $group, $namespace), self::map($this->langPath, $locale, $group));
    }

    /**
     * The group's English with each sentence the map translates replaced, and every other left in English (so a
     * page asking for a whole list or section still gets all of it, half in English until it's done).
     *
     * @param  array<array-key, mixed>  $english
     * @param  array<string, string>  $map
     * @return array<array-key, mixed>
     */
    public static function translate(array $english, array $map, string $path = ''): array
    {
        $out = [];

        foreach ($english as $key => $value) {
            $here = $path === '' ? (string) $key : $path.'.'.$key;

            if (is_array($value)) {
                $out[$key] = self::translate($value, $map, $here);

                continue;
            }

            if (! is_string($value)) {
                $out[$key] = $value;

                continue;
            }

            // One key's own translation first (`@<key>:<English>`, where the same English means two things), then the
            // sentence's: each only while its English is the one it was made from.
            $own = $map['@'.$here.':'.$value] ?? null;
            $translation = is_string($own) && self::fits($value, $own) ? $own : ($map[$value] ?? null);

            // The English where there's no translation: a page that asks for a whole list gets every item.
            $out[$key] = is_string($translation) && self::fits($value, $translation) ? $translation : $value;
        }

        return $out;
    }

    /**
     * A map entry's English: the key itself, or an override's (`@<key>:<English>`) after its key.
     *
     * @return array{0: string|null, 1: string} the override's key (null for a sentence) and its English
     */
    public static function entry(string $mapKey): array
    {
        if (str_starts_with($mapKey, '@') && preg_match('/^@([A-Za-z0-9_.\-]+):(.*)$/s', $mapKey, $m) === 1) {
            return [$m[1], $m[2]];
        }

        return [null, $mapKey];
    }

    /**
     * Whether a translation keeps the English's placeholders and plural forms, so it can stand in.
     */
    public static function fits(string $english, string $translation): bool
    {
        if (trim($translation) === '') {
            return false;
        }

        $placeholders = static function (string $text): array {
            preg_match_all('/:([A-Za-z_][A-Za-z0-9_]*)/', $text, $found);
            $names = array_unique($found[1]);
            sort($names);

            return $names;
        };

        return $placeholders($english) === $placeholders($translation) && substr_count($english, '|') === substr_count($translation, '|');
    }

    /**
     * @return array<string, string>
     */
    public static function map(string $langPath, string $locale, string $group): array
    {
        $path = $langPath.'/'.$locale.'/'.$group.'.json';

        if (! is_file($path)) {
            return [];
        }

        $map = json_decode((string) file_get_contents($path), true);

        return is_array($map) ? array_filter($map, static fn (mixed $value, mixed $key): bool => is_string($key) && is_string($value), ARRAY_FILTER_USE_BOTH) : [];
    }

    public function addNamespace($namespace, $hint): void
    {
        $this->files->addNamespace($namespace, $hint);
    }

    public function addJsonPath($path): void
    {
        $this->files->addJsonPath($path);
    }

    /**
     * @return array<string, string>
     */
    public function namespaces(): array
    {
        return $this->files->namespaces();
    }
}
