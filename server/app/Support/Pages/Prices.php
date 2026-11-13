<?php

declare(strict_types=1);

namespace App\Support\Pages;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use NumberFormatter;

/**
 * Plus's prices as the public pages show them (docs/pricing-plan.md, "Prices"), in the visitor's
 * currency: pounds in the UK, euros in the euro area, dollars elsewhere (Cloudflare's country, or
 * the browser's language), or the one they picked (?currency=). From config/billing.php, so the
 * pages never say what Checkout won't charge.
 */
final class Prices
{
    public const CURRENCIES = ['usd', 'eur', 'gbp'];

    private const EURO_AREA = ['AT', 'BE', 'HR', 'CY', 'EE', 'FI', 'FR', 'DE', 'GR', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PT', 'SK', 'SI', 'ES'];

    private const SYMBOLS = ['usd' => '$', 'eur' => '€', 'gbp' => '£'];

    public static function currency(Request $request): string
    {
        $chosen = $request->query('currency');

        if (is_string($chosen) && in_array($chosen, self::CURRENCIES, true)) {
            return $chosen;
        }

        $country = strtoupper((string) $request->header('CF-IPCountry'));

        if ($country === '' || $country === 'XX') {
            $language = (string) $request->getPreferredLanguage();
            $country = strtoupper((string) (explode('_', str_replace('-', '_', $language))[1] ?? ''));
        }

        return match (true) {
            $country === 'GB' => 'gbp',
            in_array($country, self::EURO_AREA, true) => 'eur',
            default => 'usd',
        };
    }

    /**
     * @return array{currency: string, month: string, year: string, yearAsMonth: string, taxIncluded: bool}
     */
    public static function of(string $currency): array
    {
        $amounts = (array) config('billing.amounts.'.$currency);

        return [
            'currency' => $currency,
            'month' => self::format((int) $amounts['month'], $currency),
            'year' => self::format((int) $amounts['year'], $currency),
            'yearAsMonth' => self::format(intdiv((int) $amounts['year'], 12), $currency),
            'taxIncluded' => (bool) $amounts['tax_inclusive'],
            // How much less a year costs than twelve months, in whole percent (the landing's switch).
            'saving' => (int) round(100 - (int) $amounts['year'] * 100 / max(1, 12 * (int) $amounts['month'])),
        ];
    }

    /** A currency's sign alone ("$", "€", "£"), for /pricing's currency switch. */
    public static function symbol(string $currency): string
    {
        return self::SYMBOLS[$currency] ?? strtoupper($currency);
    }

    /** A price as the page's language writes it: "$3.33" in English, "3,33 US$" and "3,33 €" in Spanish (ICU). */
    public static function format(int $cents, string $currency): string
    {
        return self::local($cents, $currency) ?? self::SYMBOLS[$currency].number_format($cents / 100, 2);
    }

    /** Any currency in the page's language, or null in English (whose own forms the site keeps exactly). */
    public static function local(int $cents, string $currency): ?string
    {
        $locale = App::getLocale();
        if ($locale === 'en' || ! class_exists(NumberFormatter::class)) {
            return null;
        }
        $words = (new NumberFormatter($locale, NumberFormatter::CURRENCY))->formatCurrency($cents / 100, strtoupper($currency));

        return $words === false ? null : $words;
    }
}
