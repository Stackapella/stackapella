<?php

declare(strict_types=1);

namespace App\Support\Billing;

use Illuminate\Http\Request;

/**
 * The EU, EEA and UK right of withdrawal (docs/pricing-plan.md, "At checkout"): 14 days to change
 * one's mind about a digital service, unless the person asked for it to start at once and
 * acknowledged losing that right for the time used. So there, before Checkout, the app asks for
 * that consent, and the server records it with the checkout (when, which words, which country).
 */
final class Withdrawal
{
    /** The EU's 27, Iceland, Liechtenstein, Norway, and the UK. */
    private const COUNTRIES = [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
        'IS', 'LI', 'NO',
        'GB',
    ];

    /**
     * The visitor's country, from Cloudflare at the edge; null when it doesn't say.
     */
    public static function country(Request $request): ?string
    {
        $country = strtoupper(trim((string) $request->header('CF-IPCountry')));

        return preg_match('/^[A-Z]{2}$/', $country) === 1 && $country !== 'XX' ? $country : null;
    }

    public static function needsConsent(?string $country): bool
    {
        return $country !== null && in_array($country, self::COUNTRIES, true);
    }

    /**
     * The version of the consent's words the app shows (contract/api.md, billing.checkout): when the
     * lawyer changes them, both sides move to the next.
     */
    public static function version(): int
    {
        return (int) config('billing.start_now_consent_version');
    }
}
