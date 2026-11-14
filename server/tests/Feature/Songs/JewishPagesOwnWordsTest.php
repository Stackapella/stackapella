<?php

declare(strict_types=1);

namespace Tests\Feature\Songs;

use Tests\TestCase;

/**
 * A Jewish song's page (its file's language Hebrew or Yiddish) never uses God, Lord, Almighty, Dios, Señor or
 * Todopoderoso as a title in our own words (Hillel's rule; the courses lead and the arranger, 2026-11-14): its About,
 * its part lines and its description, in English and Spanish. The same words and exemptions as the content repo's
 * tools/divine_names_check.py, which sees the stories and the course; this sees the words that live here. Quoted text
 * (a title or a quotation) and italic book titles are left out; "Almighty Hashem" (our substitution) and Lord Byron
 * are fine.
 */
final class JewishPagesOwnWordsTest extends TestCase
{
    private const TITLE_EN = '/\b(God|Lord|LORD|Almighty)\b/';

    private const TITLE_ES = '/\b(Dios|Señor|Todopoderoso)\b/u';

    public function test_a_jewish_pages_own_words_never_use_a_divine_title(): void
    {
        $songs = [];
        foreach (glob(database_path('templates').'/*.json') ?: [] as $file) {
            $song = json_decode((string) file_get_contents($file), true);
            if (is_array($song) && in_array($song['language'] ?? null, ['he', 'yi'], true) && is_string($song['id'] ?? null)) {
                $songs[$song['id']] = is_string($song['description'] ?? null) ? $song['description'] : '';
            }
        }
        $this->assertNotEmpty($songs, 'the Jewish songs are found');

        /** @var array<string, string> $about */
        $about = require lang_path('en/song_about.php');
        /** @var array<string, array<string, string>> $parts */
        $parts = require lang_path('en/song_parts.php');
        $esAbout = (array) json_decode((string) file_get_contents(lang_path('es/song_about.json')), true);
        $esParts = (array) json_decode((string) file_get_contents(lang_path('es/song_parts.json')), true);
        $esDescriptions = (array) json_decode((string) file_get_contents(resource_path('data/song_descriptions/es.json')), true);

        $found = [];
        foreach ($songs as $id => $description) {
            $english = array_values(array_filter([$about[$id] ?? null, $description, ...array_values($parts[$id] ?? [])], 'is_string'));
            foreach ($english as $text) {
                $found = [...$found, ...self::titles($id, $text, self::TITLE_EN)];
                $spanish = $esAbout[$text] ?? $esParts[$text] ?? $esDescriptions[$text] ?? null;
                if (is_string($spanish)) {
                    $found = [...$found, ...self::titles($id, $spanish, self::TITLE_ES)];
                }
            }
        }

        $this->assertSame([], $found, 'a Jewish page says Hashem or Master (Amo) in our own words, never a title');
    }

    /**
     * @return list<string>
     */
    private static function titles(string $id, string $text, string $pattern): array
    {
        $own = (string) preg_replace('/"[^"]*"|“[^”]*”|\*[^*]+\*/u', '', $text);
        $own = str_replace(['Almighty Hashem', 'Lord Byron', 'Todopoderoso Hashem'], '', $own);
        preg_match_all($pattern, $own, $matches);

        return array_map(static fn (string $word): string => "{$id}: {$word}", $matches[0]);
    }
}
