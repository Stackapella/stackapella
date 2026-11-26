<?php

declare(strict_types=1);

namespace App\Support\Billing;

use App\Actions\Counts\RecordCounts;
use App\Exceptions\Refusal;
use Closure;
use Illuminate\Validation\ValidationException;

/**
 * The Plus and seats checkouts' own anonymous daily counts (the coordinator, 2026-11-26: the funnel in Admin from now):
 * a Checkout started, and one refused, by why (the refusal's code, or `consent` for a missing EU start-now tick).
 */
final class CheckoutCounts
{
    public const KIND = ['plus', 'seats'];

    public const REFUSED = ['paused', 'email_unverified', 'already_plus', 'consent', 'trial_used', 'checkout_pending', 'seats_active', 'invalid'];

    /**
     * Run a checkout, counting how it went.
     *
     * @template T
     *
     * @param  Closure(): T  $checkout
     * @return T
     */
    public static function counted(string $kind, Closure $checkout): mixed
    {
        try {
            $url = $checkout();
        } catch (Refusal $e) {
            self::count('checkout_refused:'.(in_array($e->refusalCode, self::REFUSED, true) ? $e->refusalCode : 'invalid'));

            throw $e;
        } catch (ValidationException $e) {
            self::count('checkout_refused:'.(array_key_exists('startNowConsent', $e->errors()) ? 'consent' : 'invalid'));

            throw $e;
        }

        self::count('checkout_started:'.$kind);

        return $url;
    }

    /**
     * @return list<string>
     */
    public static function serverKinds(): array
    {
        return [...array_map(static fn (string $k): string => 'checkout_started:'.$k, self::KIND), ...array_map(static fn (string $r): string => 'checkout_refused:'.$r, self::REFUSED)];
    }

    private static function count(string $kind): void
    {
        app(RecordCounts::class)->serverAmount($kind, 1);
    }
}
