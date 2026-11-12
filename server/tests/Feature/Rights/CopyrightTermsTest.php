<?php

declare(strict_types=1);

namespace Tests\Feature\Rights;

use App\Support\Rights\SongRights;
use ResourceBundle;
use Tests\TestCase;

/**
 * The copyright terms table (resources/data/copyright-terms.json, docs/geo-rights-plan.md): every country, each with
 * its source, for the lawyer; and the cases the plan was written for, on the real table.
 */
final class CopyrightTermsTest extends TestCase
{
    public function test_every_country_has_its_own_row_or_its_parents(): void
    {
        $regions = ResourceBundle::create('en', 'ICUDATA-region')?->get('Countries');
        $this->assertNotNull($regions);
        $missing = [];
        foreach ($regions as $code => $name) {
            $code = (string) $code;
            if (preg_match('/^[A-Z]{2}$/', $code) === 1 && ! in_array($code, (array) config('copyright_terms.notCountries'), true)
                && config('copyright_terms.countries.'.$code) === null && config('copyright_terms.inherits.'.$code) === null) {
                $missing[] = $code;
            }
        }
        $this->assertSame([], $missing, 'a country with no row would silently take the strictest rule');

        foreach ((array) config('copyright_terms.countries') as $code => $row) {
            $this->assertIsInt($row['life'] ?? $row['publicationBased']['term'] ?? null, (string) $code);
            $this->assertFalse($row['reviewed'] ?? true, $code.': the lawyer marks a row reviewed, never the build');
            if (($row['confidence'] ?? '') !== 'low') {
                $this->assertStringStartsWith('http', (string) ($row['source'] ?? ''), $code.': its source');
            }
        }
    }

    public function test_the_plans_examples_on_the_real_table(): void
    {
        $lift = ['makers' => [['name' => 'J. Rosamond Johnson', 'role' => 'music', 'died' => 1954], ['name' => 'James Weldon Johnson', 'role' => 'words', 'died' => 1938]], 'firstPublished' => ['year' => 1900, 'country' => 'US']];
        $at = static fn (string $country): array => SongRights::status($lift, $country, 2026);

        $this->assertTrue($at('US')['free']);
        $this->assertTrue($at('GB')['free']);
        $this->assertSame(2055, $at('MX')['until']);
        $this->assertSame(2035, $at('CO')['until']);
        // Spain applies the rule of the shorter term: a US work printed in 1900 is free there because it's free in the US.
        $this->assertTrue($at('ES')['free']);
        // A Spanish work by an author who died in 1954 takes Spain's own term: life+80 for deaths before 7 December 1987.
        $spanish = ['makers' => [['name' => 'A', 'died' => 1954]], 'firstPublished' => ['year' => 1940, 'country' => 'ES']];
        $this->assertSame(2035, SongRights::status($spanish, 'ES', 2026)['until']);
        // Unknown: the strictest row, at least Mexico's. France's longest exception (an author "mort pour la France",
        // which we can't know, so counted as met) runs longer still.
        $this->assertGreaterThanOrEqual(2055, $at('XX')['until']);
        $this->assertFalse($at('XX')['free']);
        $this->assertSame($at('FR'), $at('RE'), 'Réunion takes France\'s rule');

        $silent = ['makers' => [['name' => 'Franz Xaver Gruber', 'role' => 'music', 'died' => 1863], ['name' => 'Joseph Mohr', 'role' => 'words', 'died' => 1848]], 'firstPublished' => ['year' => 1833, 'country' => 'DE']];
        foreach (array_keys((array) config('copyright_terms.countries')) as $code) {
            $this->assertTrue(SongRights::status($silent, (string) $code, 2026)['free'], (string) $code);
        }
    }
}
