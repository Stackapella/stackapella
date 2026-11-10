<?php

declare(strict_types=1);

namespace Tests\Unit\Site;

use App\Support\Site\CourseCss;
use PHPUnit\Framework\TestCase;

/**
 * A page's course stylesheet (docs/css-split-plan.md, step 1): courses.css with only the rules of the courses the page
 * shows and those of none, in courses.css's own order and each in its @media, so the page looks as with the whole file.
 */
final class CourseCssTest extends TestCase
{
    /** Enough rules for a course of its own (2 KB or more). */
    private static function own(string $prefix): string
    {
        return implode('', array_map(static fn (int $i): string => ".cp-{$prefix}-r{$i}{color:red;margin:{$i}px;padding:{$i}px;border:1px solid blue}", range(1, 40)));
    }

    private static function css(): string
    {
        return '/*! tailwindcss */'.self::own('aa').self::own('bb').self::own('dk')
            .'.s-note{display:flex}'
            .'.cp-faq{margin:0}'
            .'.cp-aa-pick{margin-top:0}'
            .'@media (min-width:900px){.cp-aa-wide{gap:4px}.cp-bb-wide{gap:8px}.s-note{gap:2px}}'
            .'.cp-dk-pick{margin-top:18px}'
            .'.cp-bb-pick{margin-top:6px}'
            .'.cp-aa-x,.cp-bb-x{color:green}'
            .'.cp-aa .cp-bb-y{color:pink}'
            .':root:not([data-skin=game]) .cp-bb-z{color:gold}'
            .':is(.cp-aa-q,.cp-bb-q) p{color:teal}'
            .'@keyframes cp-bb-spin{to{rotate:1turn}}';
    }

    public function test_a_sheet_keeps_its_courses_rules_and_everyones_in_order(): void
    {
        $parsed = CourseCss::parse(self::css());
        // faq has a few bytes only: kept on every page, never a key.
        $this->assertSame(['aa', 'bb', 'dk'], $parsed['prefixes']);

        $aa = CourseCss::assemble($parsed['rules'], ['aa', 'dk']);
        foreach (['.cp-aa-r40{', '.cp-dk-r1{', '.s-note{display:flex}', '.cp-faq{margin:0}', '.cp-aa-x,.cp-bb-x{color:green}', ':is(.cp-aa-q,.cp-bb-q) p{color:teal}'] as $rule) {
            $this->assertStringContainsString($rule, $aa);
        }
        // In courses.css's order: the kit's later 18 px still comes after the course's 0, as in the whole file.
        $this->assertLessThan(strpos($aa, '.cp-dk-pick'), (int) strpos($aa, '.cp-aa-pick'));
        $this->assertStringContainsString('@media (min-width:900px){.cp-aa-wide{gap:4px}.s-note{gap:2px}}', $aa);
        // Another course's rules are left out: its own, one that needs it beside aa, its keyframes, one with a :not().
        foreach (['.cp-bb-r1{', '.cp-bb-pick', '.cp-aa .cp-bb-y', 'cp-bb-spin', 'cp-bb-z', '.cp-bb-wide', 'tailwindcss'] as $absent) {
            $this->assertStringNotContainsString($absent, $aa);
        }

        $bb = CourseCss::assemble($parsed['rules'], ['bb']);
        $this->assertStringContainsString('@media (min-width:900px){.cp-bb-wide{gap:8px}.s-note{gap:2px}}', $bb);
        foreach (['.cp-aa .cp-bb-y{color:pink}', ':root:not([data-skin=game]) .cp-bb-z{color:gold}', '@keyframes cp-bb-spin{to{rotate:1turn}}'] as $rule) {
            $this->assertStringContainsString($rule, $bb);
        }

        // Every course together is the whole file, rule for rule and in order.
        $this->assertSame(str_replace('/*! tailwindcss */', '', self::css()), CourseCss::assemble($parsed['rules'], $parsed['prefixes']));
    }

    public function test_the_key_names_the_shown_courses_once_and_in_order(): void
    {
        $html = '<main class="s-wrap"><div class="cp-bb cp-bb-hero"><p class="cp-dk-pick cp-aa-pick">x</p><a class="link s-cp-not">y</a></div></main>';

        $this->assertSame(['bb', 'dk', 'aa'], CourseCss::shown($html));
        $this->assertSame([], CourseCss::shown('<main class="s-wrap"><p class="s-lede">no course</p></main>'));
        $this->assertSame('aa.bb.dk', CourseCss::key(['bb', 'dk', 'aa', 'bb']));
        $this->assertSame(CourseCss::BASE, CourseCss::key([]));
    }
}
