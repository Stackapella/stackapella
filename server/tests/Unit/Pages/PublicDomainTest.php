<?php

declare(strict_types=1);

namespace Tests\Unit\Pages;

use App\Support\Pages\PublicDomain;
use Tests\TestCase;

/**
 * The public domain roll's pieces (App\Support\Pages\PublicDomain): where a song's state differs by country, said as a
 * sentence says places; the terms we check, longest first; and the data files, each date cited and nothing guessed.
 */
final class PublicDomainTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function liftEveryVoice(): array
    {
        return ['makers' => [['name' => 'J. Rosamond Johnson', 'role' => 'music', 'died' => 1954], ['name' => 'James Weldon Johnson', 'role' => 'words', 'died' => 1938]], 'firstPublished' => ['year' => 1900, 'country' => 'US']];
    }

    private function terms(): void
    {
        config(['copyright_terms.countries' => [
            'US' => ['name' => 'United States', 'life' => 70, 'publicationBased' => ['freeBefore' => 1931, 'term' => 95], 'confidence' => 'high'],
            'GB' => ['name' => 'United Kingdom', 'life' => 70, 'confidence' => 'high'],
            'MX' => ['name' => 'Mexico', 'life' => 100, 'confidence' => 'high'],
            'CO' => ['name' => 'Colombia', 'life' => 80, 'confidence' => 'high'],
            'GN' => ['name' => 'Guinea', 'life' => 80, 'confidence' => 'high'],
            'SO' => ['name' => 'Somalia', 'life' => 50, 'confidence' => 'low'],
        ]]);
    }

    public function test_a_song_says_where_it_is_still_protected_and_from_when_it_is_free_there(): void
    {
        $this->terms();
        $held = PublicDomain::held(self::liftEveryVoice(), 2026, PublicDomain::places());

        $this->assertSame([
            ['year' => null, 'places' => ['United States', 'United Kingdom'], 'more' => []],
            ['year' => 2035, 'places' => ['Colombia', 'Guinea'], 'more' => []],
            ['year' => 2055, 'places' => ['Mexico'], 'more' => []],
        ], $held);
        // A low-confidence row (a law we couldn't read for certain) is never named: the strictest rule stands for it.
        $this->assertArrayNotHasKey('SO', PublicDomain::places());
    }

    public function test_places_are_said_as_a_sentence_says_them_and_a_long_list_is_cut_short(): void
    {
        $this->assertSame('Mexico', PublicDomain::listed(['Mexico'], []));
        $this->assertSame('Mexico and Colombia', PublicDomain::listed(['Mexico', 'Colombia'], []));
        $this->assertSame('Mexico, Colombia and Spain', PublicDomain::listed(['Mexico', 'Colombia', 'Spain'], []));
        $this->assertSame('Mexico, Colombia, Spain and 2 more', PublicDomain::listed(['Mexico', 'Colombia', 'Spain'], ['Peru', 'Chile']));
        $this->assertSame('Mexico, Colombia, Spain and one more', PublicDomain::listed(['Mexico', 'Colombia', 'Spain'], ['Peru']));
    }

    public function test_the_terms_are_listed_longest_first_with_the_united_states_by_publication_last(): void
    {
        $this->terms();
        $this->assertSame([
            ['life' => 100, 'count' => 1, 'places' => ['Mexico']],
            ['life' => 80, 'count' => 2, 'places' => ['Colombia', 'Guinea']],
            ['life' => 70, 'count' => 1, 'places' => ['United Kingdom']],
            ['life' => null, 'count' => 1, 'places' => ['United States']],
        ], PublicDomain::terms());
    }

    public function test_every_date_in_the_data_is_cited_and_a_maker_of_unknown_death_is_never_a_person_on_the_roll(): void
    {
        $file = json_decode((string) file_get_contents(resource_path('data/public-domain/coming.json')), true);
        $this->assertIsArray($file);
        $ids = [];
        foreach ($file['people'] as $person) {
            $ids[] = $person['id'];
            $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $person['id']);
            if ($person['died'] === null) {
                $this->assertNull($person['source'], "{$person['name']}: an unconfirmed death has no source to cite");

                continue;
            }
            $this->assertIsInt($person['died'], $person['name']);
            $this->assertStringStartsWith('https://', (string) $person['source'], "{$person['name']}'s dates need their source");
            if ($person['born'] !== null) {
                $this->assertLessThan($person['died'], $person['born'], $person['name']);
            }
        }
        $this->assertSame(count($ids), count(array_unique($ids)));
        foreach ($file['works'] as $work) {
            foreach ($work['makers'] as $maker) {
                $this->assertContains($maker['person'], $ids, "{$work['title']}: every maker is a person in the file");
                $this->assertContains($maker['role'], PublicDomain::ROLES, $work['title']);
            }
        }
        $raw = (string) file_get_contents(resource_path('data/public-domain/coming.json')).(string) file_get_contents(resource_path('data/public-domain/lives.json'));
        $this->assertStringNotContainsString("\u{2014}", $raw);
        $this->assertStringNotContainsString("\u{2013}", $raw);

        $coming = PublicDomain::coming();
        $this->assertArrayHasKey('drorah-setel', $coming['unknown']);
        $this->assertArrayNotHasKey('drorah-setel', $coming['people']);

        // A library maker's birth year shows only with its source.
        foreach (PublicDomain::lives() as $key => $life) {
            $this->assertStringContainsString('|', (string) $key);
            if (is_int($life['born'] ?? null)) {
                $this->assertStringStartsWith('https://', (string) ($life['source'] ?? ''), "{$key}: a birth year needs its source");
            }
        }
    }

    public function test_the_roll_reads_its_words_for_each_year_and_person(): void
    {
        $person = ['key' => 'x-1958', 'name' => 'Ralph Vaughan Williams', 'born' => 1872, 'died' => 1958, 'joins' => 2029, 'source' => 'https://example.com/rvw', 'library' => false, 'state' => 'coming', 'sort' => 'Williams', 'search' => '', 'works' => [
            ['title' => 'Forest Green', 'url' => null, 'roles' => ['arrangement'], 'waits' => null, 'elsewhere' => [['year' => null, 'places' => ['United States'], 'more' => []]]],
        ]];
        $page = PublicDomain::present([
            'year' => 2026, 'next' => null, 'nextIsThisYear' => false, 'joined' => [], 'unsigned' => [], 'terms' => [],
            'coming' => [['year' => 2029, 'died' => 1958, 'people' => [$person]]],
            'long' => [['century' => 1900, 'people' => []]],
        ]);
        $group = $page['coming'][0];
        $this->assertSame('1 January 2029', $group['date']);
        $this->assertSame('70 years after 1958', $group['line']);
        $this->assertSame('1872 to 1958', $group['people'][0]['life']);
        $this->assertSame(['Harmony'], $group['people'][0]['works'][0]['roles']);
        // Each note says its state in words, and is marked for its dot: filled, free now; hollow, a later year.
        $this->assertSame(['kind' => 'now', 'text' => 'Free now: United States', 'more' => []], $group['people'][0]['works'][0]['notes'][0]);
        $this->assertSame([['href' => '#y2029', 'label' => '2029', 'part' => 'coming'], ['href' => '#long', 'label' => 'Earlier', 'part' => 'long']], $page['years']);
        // The writers before the yearly roll, by century.
        $this->assertSame('Writers of the 1900s', $page['long'][0]['label']);
    }
}
