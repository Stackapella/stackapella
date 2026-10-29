<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use ReflectionMethod;
use Tests\TestCase;

/**
 * The claims test (docs/pricing-plan.md, "Keeping claims honest"): docs/features.md, the single
 * list of what each plan gets, says what the code does. Every number in its tables matches the
 * config it names, the free songs are the configured ones, and each Plus-only row is refused to a
 * free account (`plus_required`) where it says.
 */
final class FeaturesClaimsTest extends TestCase
{
    private function features(): string
    {
        return (string) file_get_contents(base_path('../docs/features.md'));
    }

    /**
     * A claim as a number (prices in cents, storage in bytes), or null for "Unlimited".
     */
    private static function claimed(string $claim, string $key): ?float
    {
        if (strcasecmp(trim($claim), 'Unlimited') === 0) {
            return null;
        }

        $number = (float) preg_replace('/[^0-9.]/', '', $claim);

        return match (true) {
            str_contains($key, 'storage_bytes') => $number * 1024 ** 3,
            str_starts_with($key, 'billing.amounts.') => round($number * 100),
            default => $number,
        };
    }

    public function test_every_number_in_features_md_is_the_codes(): void
    {
        $checked = 0;

        foreach (explode("\n", $this->features()) as $line) {
            if (preg_match('/^\|(.+)\|\s*`([a-z_.*]+)`\s*\|$/', $line, $row) !== 1) {
                continue;
            }

            $cells = array_map('trim', explode('|', trim($row[1])));
            // Free and Plus side by side (`usage.plans.*.x`), or one value.
            $values = str_contains($row[2], '*') ? ['free' => $cells[1], 'plus' => $cells[2]] : ['' => $cells[1]];

            foreach ($values as $plan => $claim) {
                $key = str_replace('*', $plan, $row[2]);
                $this->assertTrue(config()->has($key), "docs/features.md names {$key}, which isn't in the config.");
                $actual = config($key);
                $this->assertSame(self::claimed($claim, $key), $actual === null ? null : (float) $actual, "docs/features.md says \"{$cells[0]}\" is {$claim}, but {$key} is ".var_export($actual, true).'.');
                $checked++;
            }
        }

        $this->assertGreaterThanOrEqual(20, $checked);
    }

    public function test_the_free_songs_are_the_configured_ones(): void
    {
        preg_match('/\*\*The free songs\*\* \(`templates\.free`\):(.+?)\n\n/s', $this->features(), $free);
        preg_match_all('/\(`([a-z0-9-]+)`/', $free[1] ?? '', $slugs);

        $this->assertSame((array) config('templates.free'), $slugs[1]);
    }

    public function test_each_plus_only_row_is_refused_where_it_says(): void
    {
        preg_match_all('/^\| [^|]+ \| `(App\\\\[A-Za-z\\\\]+)::([a-zA-Z]+)` \|$/m', $this->features(), $rows, PREG_SET_ORDER);
        $this->assertNotEmpty($rows);

        foreach ($rows as [, $class, $method]) {
            $this->assertTrue(method_exists($class, $method), "docs/features.md names {$class}::{$method}, which doesn't exist.");
            $reflection = new ReflectionMethod($class, $method);
            $source = implode('', array_slice((array) file((string) $reflection->getFileName()), $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
            $this->assertStringContainsString("'plus_required'", $source, "{$class}::{$method} doesn't refuse a free account (plus_required), as docs/features.md says.");
        }
    }
}
