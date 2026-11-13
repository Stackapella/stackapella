<?php

declare(strict_types=1);

namespace Tests\Unit\Pages;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Hillel's rule for the Jewish song pages: no full divine name, in Hebrew script or in transliteration, in any language.
 * Only the substituted forms (Kel, Keli, Elokeinu, Elokim, Adoshem, Shakai, Hashem, ה'). The content repo checks its own
 * files (tools/divine_names_check.py, the lists here are its); this checks the page text the site keeps itself: each
 * Jewish song's entries in lang/en/song_about.php and song_parts.php, the comments above them included, and their
 * Spanish in lang/es/song_about.json and song_parts.json. And our own words there say Hashem, never God or Dios
 * (decisions, 2026-11-12); the sources comments may quote a printing as it is. Their descriptions (the song file's,
 * and resources/data/song_descriptions/es.json) too, where Lord or Señor alone doesn't stand for the Name either.
 */
final class DivineNamesTest extends TestCase
{
    private const TRANSLIT = "/(?<![\\p{L}\\p{N}])(Elohim|Elohei|Eloheinu|Elohenu|Elohai|Elohecha|Elohekha|Eloha|Adonai|Adonoy|Adonay|Adonoi|Adonaj|Adonoj|Shaddai|Shadai|Yahweh|Yahveh|Jehovah|Jehová|YHWH|Tzevaot|Tzeva'ot|Tsevaot|Sabaoth)(?![\\p{L}\\p{N}])/u";

    /** "El" and "Eli" as the name: English text only (Spanish "El" is the article). */
    private const EL = '/(?<![\p{L}\p{N}])(El|Eli)(?![\p{L}\p{N}])/u';

    /** In Spanish the article "El" starts a sentence; capitalised after a lowercase word or a comma, it's the name. */
    private const EL_MID_SENTENCE = '/(?<=[\p{Ll},]\s)(El|Eli)(?![\p{L}\p{N}])/u';

    private const EL_ALLOWED = ['Eli Ziyyon', 'Eli Tziyon', 'Eli Zion', 'El Paso'];

    /** Compared with vowels and cantillation removed, as whole words. */
    private const HEB_FULL = ['יהוה', 'אלהים', 'אלוהים', 'אלהינו', 'אלוהינו', 'אלהי', 'אלוהי', 'אלהיך', 'אלוהיך', 'אדני', 'שדי', 'צבאות'];

    /** El and Eli in Hebrew, told from "el" (to) and "al" (don't) by the tsere under the alef. */
    private const HEB_EL = '/(?<![\x{05D0}-\x{05EA}\x{0591}-\x{05C7}])\x{05D0}[\x{0591}-\x{05AF}]*\x{05B5}[\x{0591}-\x{05AF}]*\x{05DC}[\x{0591}-\x{05AF}]*(\x{05B4}[\x{0591}-\x{05AF}]*\x{05D9})?(?![\x{05D0}-\x{05EA}\x{0591}-\x{05C7}])/u';

    /** Our own words say Hashem (decisions, 2026-11-12): "Lord" and "Señor" are fine. */
    private const GOD = ['en' => '/(?<![\p{L}\p{N}])God(?![\p{L}\p{N}])/u', 'es' => '/(?<![\p{L}\p{N}])Dios(?![\p{L}\p{N}])/u'];

    /**
     * In a song's description, Lord or Señor standing alone for the Name ("Yours, Lord, is the kingdom"); a title is fine:
     * "Lord of the world", "Señor del mundo", "our Lord", "nuestro Señor".
     */
    private const LORD = '/(?<!\bour |\bmy |\bhis |\bnuestro |\bmi |\bsu )(?<![\p{L}\p{N}])(Lord|Señor)(?![\p{L}\p{N}])(?! of\b| del?\b)/u';

    /** @return list<string> */
    public static function hits(string $text, bool $english): array
    {
        preg_match_all(self::TRANSLIT, $text, $m);
        $found = $m[0];
        if (preg_match_all($english ? self::EL : self::EL_MID_SENTENCE, $text, $el, PREG_OFFSET_CAPTURE) > 0) {
            foreach ($el[0] as [$word, $at]) {
                $around = substr($text, max(0, $at - 12), strlen($word) + 24);
                if (! array_filter(self::EL_ALLOWED, static fn (string $a): bool => str_contains($around, $a))) {
                    $found[] = $word;
                }
            }
        }
        preg_match_all('/[\x{05D0}-\x{05EA}\x{0591}-\x{05C7}]+/u', $text, $words);
        foreach ($words[0] as $word) {
            if (in_array(preg_replace('/[\x{0591}-\x{05C7}]/u', '', $word), self::HEB_FULL, true)) {
                $found[] = $word;
            }
        }
        preg_match_all(self::HEB_EL, $text, $heb);

        return [...$found, ...$heb[0]];
    }

    /** @return array<string, array<string, mixed>> the songs sung in Hebrew or Yiddish, from the library and the free songs */
    private static function jewishSongs(): array
    {
        $root = dirname(base_path());
        $slugs = [];
        foreach ([...File::glob(base_path('database/templates/*.json')), ...File::glob($root.'/web/templates/*.json')] as $file) {
            if (str_ends_with($file, '.next.json')) {
                continue;
            }
            $song = json_decode((string) File::get($file), true);
            if (is_array($song) && in_array($song['language'] ?? null, ['he', 'yi'], true)) {
                $slugs[(string) ($song['id'] ?? basename($file, '.json'))] = $song;
            }
        }

        return $slugs;
    }

    /**
     * Each top-level entry of a lang file by its slug, as written: the comment lines above it and every line to the next.
     *
     * @return array<string, list<array{int, string}>> slug => [line number, text]
     */
    private static function entries(string $file): array
    {
        $entries = [];
        $pending = [];
        $current = null;
        foreach (explode("\n", (string) File::get($file)) as $i => $line) {
            if (preg_match('/^    \/\//', $line) === 1) {
                $pending[] = [$i + 1, $line];
                $current = null;
            } elseif (preg_match("/^    '([a-z0-9-]+)' =>/", $line, $m) === 1) {
                $current = $m[1];
                $entries[$current] = [...$pending, [$i + 1, $line]];
                $pending = [];
            } elseif ($current !== null) {
                $entries[$current][] = [$i + 1, $line];
            }
        }

        return $entries;
    }

    public function test_the_lists_catch_the_full_names_and_let_the_substitutes_through(): void
    {
        foreach (['Elohim', 'Adonai', 'Shaddai', 'YHWH', 'Jehová', 'Kel, for El', 'אֱלֹהִים', 'יְהוָה', 'אֵל', 'אֵלִי'] as $name) {
            $this->assertNotSame([], self::hits($name, true), $name);
        }
        foreach (['Kel', 'Keli', 'Elokeinu', 'Elokim', 'Kelokeinu', 'Adoshem', 'Shakai', 'Hashem', "ה'", 'יְיָ', 'אֶל', 'אַל', 'Eli Ziyyon', 'Eliyahu', 'Elohims'] as $ok) {
            $this->assertSame([], self::hits($ok, true), $ok);
        }
        $this->assertSame([], self::hits('El Adir es un himno. El coro canta', false), 'Spanish "El" is the article');
        foreach (['Yours, Lord, is the kingdom' => ['Lord'], 'tuyo, Señor, es el reino' => ['Señor'], 'Lord of the world, our Lord, Señor del mundo, nuestro Señor, Lordship' => []] as $text => $names) {
            preg_match_all(self::LORD, $text, $m);
            $this->assertSame($names, $m[0], $text);
        }
        $this->assertSame(['El'], self::hits('Kel, en lugar de El.', false), 'mid-sentence, it is the name');
    }

    public function test_no_jewish_song_page_writes_a_full_divine_name(): void
    {
        $songs = self::jewishSongs();
        $slugs = array_keys($songs);
        $this->assertGreaterThanOrEqual(11, count($slugs), 'the Jewish songs are found');

        $problems = [];
        foreach (['song_about', 'song_parts'] as $name) {
            // English, as written: each entry with the comments above it.
            foreach (array_intersect_key(self::entries(lang_path("en/{$name}.php")), array_flip($slugs)) as $slug => $lines) {
                foreach ($lines as [$n, $text]) {
                    $said = str_starts_with(ltrim($text), '//') ? [] : (preg_match_all(self::GOD['en'], $text, $god) > 0 ? $god[0] : []);
                    foreach ([...self::hits($text, true), ...$said] as $hit) {
                        $problems[] = "lang/en/{$name}.php:{$n} ({$slug}): \"{$hit}\"";
                    }
                }
            }
            // Spanish: the translation of each of those English strings (lang/es/*.json is keyed by the English).
            $english = (array) require lang_path("en/{$name}.php");
            $spanish = (array) json_decode((string) File::get(lang_path("es/{$name}.json")), true);
            foreach (array_intersect_key($english, array_flip($slugs)) as $slug => $entry) {
                $entry = (array) $entry;
                array_walk_recursive($entry, static function (mixed $text) use ($spanish, $name, $slug, &$problems): void {
                    $es = (string) ($spanish[(string) $text] ?? '');
                    $said = preg_match_all(self::GOD['es'], $es, $god) > 0 ? $god[0] : [];
                    foreach ([...self::hits($es, false), ...$said] as $hit) {
                        $problems[] = "lang/es/{$name}.json ({$slug}): \"{$hit}\"";
                    }
                });
            }
        }
        // Each song's description (the song file's) and its Spanish, with any older Spanish still kept for an earlier
        // wording of it (the same opening): God, Dios, and Lord or Señor standing for the Name.
        $spanish = (array) json_decode((string) File::get(resource_path('data/song_descriptions/es.json')), true);
        foreach ($songs as $slug => $song) {
            $english = (string) ($song['description'] ?? '');
            if ($english === '') {
                continue;
            }
            $texts = [['en', 'en', $english]];
            foreach ($spanish as $key => $es) {
                if ($key === $english || strncmp((string) $key, $english, 30) === 0) {
                    $texts[] = ['es', $key === $english ? 'es' : 'es, an older wording', (string) $es];
                }
            }
            foreach ($texts as [$lang, $label, $text]) {
                preg_match_all(self::GOD[$lang], $text, $god);
                preg_match_all(self::LORD, $text, $lord);
                foreach ([...self::hits($text, $lang === 'en'), ...$god[0], ...$lord[0]] as $hit) {
                    $problems[] = "song description {$label} ({$slug}): \"{$hit}\"";
                }
            }
        }
        $this->assertSame([], $problems, 'Write the substituted form (Kel, Keli, Elokeinu, Elokim, Adoshem, Shakai, Hashem), and Hashem for God or Dios.');
    }
}
