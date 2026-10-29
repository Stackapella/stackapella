<?php

declare(strict_types=1);

namespace App\Support\Site;

use App\Support\Http\Hosts;

/**
 * What search engines read about the public site (schema.org JSON-LD): only what the page itself
 * says, prices only from config/billing.php, and nothing about any person.
 */
final class StructuredData
{
    /**
     * The landing: who runs it, the site, the app with its free and Plus prices, and its questions.
     *
     * @param  array{currency: string, month: string, year: string}  $price
     * @param  list<array{0: string, 1: string}>  $faq
     * @return list<array<string, mixed>>
     */
    public static function landing(array $price, array $faq): array
    {
        $site = Hosts::publicUrl().'/';
        $amounts = (array) config('billing.amounts.'.$price['currency']);
        $currency = strtoupper($price['currency']);

        return [
            [
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => __('meta.brand'),
                'url' => $site,
                'logo' => Hosts::public('/icons/icon-512.png'),
                'email' => (string) config('site.contact_email'),
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => __('meta.brand'),
                'url' => $site,
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'SoftwareApplication',
                'name' => __('meta.brand'),
                'description' => __('pages.landing.description'),
                'url' => $site,
                'applicationCategory' => 'EducationalApplication',
                'operatingSystem' => 'Web browser',
                'offers' => [
                    ['@type' => 'Offer', 'name' => __('pages.pricing.free'), 'price' => '0', 'priceCurrency' => $currency],
                    ['@type' => 'Offer', 'name' => __('pages.pricing.plus'), 'price' => number_format((int) ($amounts['month'] ?? 0) / 100, 2, '.', ''), 'priceCurrency' => $currency],
                ],
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => array_map(static fn (array $qa): array => [
                    '@type' => 'Question',
                    'name' => $qa[0],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $qa[1]],
                ], $faq),
            ],
        ];
    }

    /**
     * A song's page: the composition, its parts' arrangement and whether it's free to learn.
     *
     * @param  array{title: string, description: string, pageUrl: string, plus: bool, genre: list<string>, credits: list<string>}  $song
     * @return list<array<string, mixed>>
     */
    public static function song(array $song): array
    {
        return [[
            '@context' => 'https://schema.org',
            '@type' => 'MusicComposition',
            'name' => $song['title'],
            'description' => $song['description'],
            'url' => $song['pageUrl'],
            'genre' => $song['genre'],
            'isAccessibleForFree' => ! $song['plus'],
            'inLanguage' => app()->getLocale(),
        ]];
    }
}
