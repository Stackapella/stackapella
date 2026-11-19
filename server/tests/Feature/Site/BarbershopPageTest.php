<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Site\SitePages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Barbershop course page (site/courses/barbershop, Hillel's playbill design of 2026-11-18): its melody chart is
 * the course's own phrase, as unit 3 sings it, and every count it says is the course's own.
 */
final class BarbershopPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_melody_chart_is_the_phrase_unit_3_sings(): void
    {
        $step = $this->leadsPlayStep();

        // The lead is your part (the guide notes before it only listened to); the other three sing with you, tenor,
        // baritone and bass in that order. The chart starts on "Down", the lead's first sung note.
        $lead = array_values(array_filter($step['exercise']['notes'], fn (array $n): bool => ! ($n['listenOnly'] ?? false)));
        $start = (float) $lead[0]['beat'];
        $line = fn (array $notes): array => array_map(fn (array $n): array => [(float) $n['beat'] - $start, (float) $n['length'], (int) $n['step'], (int) ($n['cents'] ?? 0)], $notes);
        $expected = ['tenor' => $line($step['exercise']['with'][0]['notes']), 'lead' => $line($lead), 'baritone' => $line($step['exercise']['with'][1]['notes']), 'bass' => $line($step['exercise']['with'][2]['notes'])];

        $html = (string) $this->get('/courses/barbershop')->assertOk()->getContent();
        $this->assertSame(1, preg_match('/data-bb-phrase="([^"]+)"/', $html, $found));
        $drawn = (array) json_decode(html_entity_decode($found[1] ?? '', ENT_QUOTES), true);

        $this->assertEquals($expected, $drawn);
    }

    public function test_the_ticket_the_bill_and_the_answers_count_the_courses_own_lessons(): void
    {
        $outline = SitePages::courseOutline('barbershop');
        $this->assertNotNull($outline);
        $lessons = $outline['lessons'];

        $html = (string) $this->get('/courses/barbershop')->assertOk()->getContent();

        $this->assertStringContainsString('A free course · '.$lessons.' lessons', $html);
        $this->assertStringContainsString($lessons.' short lessons. At one or two a day', $html);
        $words = (new \NumberFormatter('en', \NumberFormatter::SPELLOUT))->format($lessons);
        $acts = (new \NumberFormatter('en', \NumberFormatter::SPELLOUT))->format(count($outline['units']));
        $this->assertStringContainsString(ucfirst((string) $words).' short lessons, <em>in '.$acts.' acts.</em>', $html);
        // No placeholder is left showing.
        $this->assertSame(1, preg_match('/<main class="cp cp-barbershop.*?<\/main>/s', $html, $main));
        $this->assertDoesNotMatchRegularExpression('/:(n|count|acts)\b/', strip_tags($main[0] ?? ''));
    }

    /**
     * Unit 3's first lesson (bb-3-lead), its step where you sing your part with the other three.
     *
     * @return array<string, mixed>
     */
    private function leadsPlayStep(): array
    {
        $course = (array) json_decode((string) file_get_contents(dirname(base_path()).'/web/course/plus/barbershop/course.json'), true);
        foreach ((array) ($course['units'] ?? []) as $unit) {
            foreach ((array) ($unit['lessons'] ?? []) as $lesson) {
                if (($lesson['id'] ?? null) !== 'bb-3-lead') {
                    continue;
                }
                foreach ((array) ($lesson['steps'] ?? []) as $step) {
                    if (is_array($step) && ($step['kind'] ?? null) === 'play') {
                        return $step;
                    }
                }
            }
        }
        $this->fail('bb-3-lead has no play step');
    }
}
