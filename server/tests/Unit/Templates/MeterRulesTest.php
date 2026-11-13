<?php

declare(strict_types=1);

namespace Tests\Unit\Templates;

use App\Support\Templates\TemplateRules;
use PHPUnit\Framework\TestCase;

/**
 * A song whose time signature changes (docs/meters-plan.md): the server's song check takes and refuses exactly what the
 * app's does (web/tests/meters.test.js, on its Wassail fixture: 6/8, then 4/4 at beat 16, then 6/8 again at 32).
 */
final class MeterRulesTest extends TestCase
{
    /** @return list<string> */
    private static function problems(mixed $meters, string $timeSignature = '6/8', float|int $pickup = 0): array
    {
        return TemplateRules::meterProblems(['timeSignature' => $timeSignature, 'pickupBeats' => $pickup, 'meters' => $meters]);
    }

    public function test_it_takes_the_apps_fixture_a_pickup_and_no_meters_at_all(): void
    {
        $this->assertSame([], self::problems([['beat' => 16, 'timeSignature' => '4/4'], ['beat' => 32, 'timeSignature' => '6/8']]));
        $this->assertSame([], self::problems([['beat' => 7, 'timeSignature' => '2/4']], '3/4', 1), 'bars of 3 from the pickup at 1');
        $this->assertSame([], TemplateRules::meterProblems(['timeSignature' => '4/4', 'pickupBeats' => 0]));
    }

    public function test_it_refuses_what_the_app_refuses(): void
    {
        $refused = [
            'a change mid-bar (6/8 bars at even beats)' => [[['beat' => 15, 'timeSignature' => '4/4']], 'not on a bar line'],
            'changes out of order' => [[['beat' => 16, 'timeSignature' => '4/4'], ['beat' => 10, 'timeSignature' => '6/8']], 'must come after'],
            'a change to the same metre' => [[['beat' => 16, 'timeSignature' => '6/8']], 'same time signature'],
            'a metre the app has no beat for' => [[['beat' => 16, 'timeSignature' => '5/4']], 'timeSignature must be one of'],
            'an empty list' => [[], 'must be a list'],
            'a change mid 4/4 bar' => [[['beat' => 16, 'timeSignature' => '4/4'], ['beat' => 30, 'timeSignature' => '6/8']], 'meters[1]: beat 30 is not on a bar line'],
            'one at the pickup' => [[['beat' => 0, 'timeSignature' => '4/4']], 'must come after'],
            'a beat as text' => [[['beat' => '16', 'timeSignature' => '4/4']], 'must come after'],
            'not a list' => [['beat' => 16, 'timeSignature' => '4/4'], 'must be a list'],
        ];
        foreach ($refused as $what => [$meters, $says]) {
            $this->assertStringContainsString($says, implode(' | ', self::problems($meters)), "took {$what}");
        }
    }
}
