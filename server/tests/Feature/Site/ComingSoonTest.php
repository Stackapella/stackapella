<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Models\Flag;
use App\Support\Flags;
use App\Support\Site\ComingSoon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * "Coming soon" follows each feature's flag, or its `live` setting, and nothing else.
 */
final class ComingSoonTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_feature_names_a_real_flag_or_says_whether_its_live(): void
    {
        foreach ((array) config('site.features') as $feature => $definition) {
            $this->assertIsArray($definition);
            if (isset($definition['flag'])) {
                $this->assertTrue(app(Flags::class)->exists($definition['flag']), "{$feature}: no flag [{$definition['flag']}].");
                $this->assertSame('feature', config('flags.'.$definition['flag'].'.kind'), "{$feature}: [{$definition['flag']}] isn't a feature flag.");
            } else {
                $this->assertIsBool($definition['live'] ?? null, "{$feature}: neither a flag nor live.");
            }
        }
    }

    public function test_a_flagged_feature_is_coming_soon_until_its_flag_is_on(): void
    {
        $this->assertTrue(ComingSoon::soon('gallery'));

        Flag::query()->create(['name' => 'gallery', 'value' => 'on']);
        $this->app->forgetScopedInstances();

        $this->assertTrue(ComingSoon::open('gallery'));
        $this->assertTrue(ComingSoon::soon('leagues'));
    }

    public function test_a_feature_without_a_flag_follows_live(): void
    {
        $this->assertTrue(ComingSoon::open('studio'));
        $this->assertTrue(ComingSoon::soon('apps'));

        config(['site.features.apps.live' => true]);
        $this->assertTrue(ComingSoon::open('apps'));
    }

    public function test_an_unknown_feature_fails_loudly(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ComingSoon::open('teleport');
    }
}
