<?php

declare(strict_types=1);

namespace App\Support\Accounts;

/**
 * Which outside sign-ins the web app may offer: only a provider whose settings are all present
 * (2026-11-10: Sign in with Apple was offered on production while its settings weren't complete,
 * and every tap failed). Event Schedule has its own check (EventSchedule::configured).
 */
final class SignInProviders
{
    /** The settings each provider can't work without (config/services.php). */
    private const REQUIRED = [
        'apple' => ['client_id', 'team_id', 'key_id', 'private_key'],
        'google' => ['client_id', 'client_secret'],
    ];

    public static function configured(string $provider): bool
    {
        foreach (self::REQUIRED[$provider] ?? [] as $key) {
            $value = config("services.{$provider}.{$key}");

            if (! is_string($value) || trim($value) === '') {
                return false;
            }
        }

        return isset(self::REQUIRED[$provider]);
    }

    /**
     * Apple first, then Google, as the app lists them.
     *
     * @return list<'apple'|'google'>
     */
    public static function offered(): array
    {
        return array_values(array_filter(['apple', 'google'], self::configured(...)));
    }
}
