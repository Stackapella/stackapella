<?php

declare(strict_types=1);

namespace App\Support\Accounts;

use App\Actions\Counts\RecordCounts;
use App\Models\User;
use App\Support\Flags;

/**
 * The AI features setting (docs/ai-optional-plan.md v2, Hillel 2026-11-23: "some people are really turned off by AI
 * voices"): each account's choice (null until chosen, off for an account made from now on; on for every account made
 * before), where it came from, and the anonymous daily counts by state and the age band kept at sign-up (`guest` before
 * an account, `unknown` without a birth date). Nothing in a count says who.
 */
final class AiChoice
{
    public const NO_BAND = 'unknown';

    public const GUEST = 'guest';

    /** Where a choice came from (users.ai_features_source); `existing` only from the migration. */
    public const SOURCES = ['card', 'settings', 'ai-singing'];

    /** What the release switch is called. */
    public const SWITCH = 'ai_optional';

    public static function on(): bool
    {
        return app(Flags::class)->on(self::SWITCH);
    }

    public static function band(User $user): string
    {
        return in_array($user->age_band, Age::BANDS, true) ? (string) $user->age_band : self::NO_BAND;
    }

    /** A change in Settings (the server's only count): off to on or on to off, a choice never made counting as off. */
    public static function switched(User $user, ?bool $before, bool $now): void
    {
        app(RecordCounts::class)->serverAmount('ai_switch:'.(($before ?? false) && ! $now ? 'on_off' : 'off_on').':'.self::band($user), 1);
    }

    /**
     * Every server kind (config counts.server_kinds).
     *
     * @return list<string>
     */
    public static function serverKinds(): array
    {
        $kinds = [];

        foreach ([...Age::BANDS, self::NO_BAND] as $band) {
            $kinds[] = "ai_switch:on_off:{$band}";
            $kinds[] = "ai_switch:off_on:{$band}";
        }

        return $kinds;
    }

    /**
     * Every kind a device sends (config counts.kinds), a guest's too: the card's events, AI used, and engagement by
     * state (on, off, unset), each by band.
     *
     * @return list<string>
     */
    public static function deviceKinds(): array
    {
        $kinds = [];

        foreach ([...Age::BANDS, self::GUEST, self::NO_BAND] as $band) {
            foreach (['shown', 'try', 'not', 'closed'] as $event) {
                $kinds[] = "ai_card:{$event}:{$band}";
            }

            foreach (['choir_start', 'choir_heard', 'guide_heard'] as $use) {
                $kinds[] = "ai_use:{$use}:{$band}";
            }

            foreach (['on', 'off', 'unset'] as $state) {
                foreach (['back_d1', 'back_w1', 'back_d7', 'back_w4'] as $back) {
                    $kinds[] = "aic:{$back}:{$state}:{$band}";
                }

                foreach (['0', '1-2', '3-5', '6+'] as $lessons) {
                    $kinds[] = "aic:wk1_lessons:{$state}:{$band}:{$lessons}";
                }
            }
        }

        return $kinds;
    }
}
