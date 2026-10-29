<?php

declare(strict_types=1);

namespace App\Support\Legal;

use App\Exceptions\Refusal;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * The terms of use someone agreed to (B): every account agrees to the current version when it's made
 * (its sign-up says so), and accepts a new one when it comes (account.terms.accept).
 */
final class Terms
{
    private static ?int $current = null;

    /** The current version: the terms' own front matter (an install's own terms when self-hosted). */
    public static function current(): int
    {
        return self::$current ??= (int) app(LegalText::class)->read('terms')['version'];
    }

    /**
     * @return array{accepted: int|null, current: int, acceptedAt: string|null}
     */
    public static function of(User $user): array
    {
        $attributes = $user->getAttributes();
        $at = $attributes['terms_accepted_at'] ?? null;

        return [
            'accepted' => isset($attributes['terms_version']) ? (int) $attributes['terms_version'] : null,
            'current' => self::current(),
            'acceptedAt' => $at !== null ? Carbon::parse($at)->utc()->toIso8601ZuluString() : null,
        ];
    }

    /**
     * Accepting the version shown: only the current one (an older one is told the current).
     */
    public static function accept(User $user, int $version): User
    {
        if ($version !== self::current()) {
            throw new Refusal(409, 'terms_changed', ['current' => ['version' => self::current()]]);
        }

        $user->forceFill(['terms_version' => $version, 'terms_accepted_at' => now()])->save();

        return $user;
    }

    /** For tests: the version is read again. */
    public static function forget(): void
    {
        self::$current = null;
    }
}
