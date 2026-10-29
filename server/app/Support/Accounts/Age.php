<?php

declare(strict_types=1);

namespace App\Support\Accounts;

use App\Exceptions\Refusal;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Age from a birth month and year (never a day): a birthday counts as the last day of its month,
 * the cautious reading, so someone is 13 or 18 only once their whole birth month has passed. The
 * answers land in age_confirmed_at (13 and over) and adult_confirmed_at (18 and over), which every
 * check reads, and a 17-year-old becomes an adult by `app:accounts:ages` with no asking again.
 */
final class Age
{
    public const ACCOUNT = 13;

    public const ADULT = 18;

    public const EARLIEST_YEAR = 1900;

    /**
     * The day someone born in that month and year turns `$age`.
     */
    public static function turns(int $month, int $year, int $age): Carbon
    {
        return Carbon::parse(sprintf('%04d-%02d-01', $year + $age, $month))->endOfMonth()->startOfDay();
    }

    public static function isAtLeast(int $month, int $year, int $age, ?Carbon $on = null): bool
    {
        return ($on ?? now())->copy()->startOfDay()->gte(self::turns($month, $year, $age));
    }

    public static function given(User $user): bool
    {
        return $user->birth_month !== null && $user->birth_year !== null;
    }

    /**
     * Their birth month and year say they're younger than `$age`.
     */
    public static function knownUnder(User $user, int $age): bool
    {
        return self::given($user) && ! self::isAtLeast((int) $user->birth_month, (int) $user->birth_year, $age);
    }

    /**
     * Under 13 is refused (`422 too_young`, the code alone, never the age back).
     */
    public static function ensureOldEnough(int $month, int $year): void
    {
        if (! self::isAtLeast($month, $year, self::ACCOUNT)) {
            throw new Refusal(422, 'too_young');
        }
    }

    /**
     * What a birth month and year set on the account; under 13 is refused (`422 too_young`, the
     * code alone, never the age back).
     *
     * @return array<string, mixed>
     */
    public static function fields(User $user, int $month, int $year): array
    {
        self::ensureOldEnough($month, $year);

        return [
            'birth_month' => $month,
            'birth_year' => $year,
            'age_confirmed_at' => $user->age_confirmed_at ?? now(),
            'adult_confirmed_at' => self::isAtLeast($month, $year, self::ADULT) ? ($user->adult_confirmed_at ?? now()) : null,
        ];
    }

    /**
     * "I'm 18 or over" (`adult: true`) counts only from an account without a birth date: one that
     * says younger is refused, whatever the request says.
     */
    public static function ensureMayClaimAdult(User $user): void
    {
        if (self::knownUnder($user, self::ADULT)) {
            throw new Refusal(403, 'adult_required');
        }
    }
}
