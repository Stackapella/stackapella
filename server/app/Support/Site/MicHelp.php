<?php

declare(strict_types=1);

namespace App\Support\Site;

use JsonException;

/**
 * The mic help page's steps, read from the app's own mic dialog words (web/locales/en.json
 * `micHelp`, web/js/components/mic-help.js), so the page and the dialog never say different
 * things: why the mic won't start, then each browser's and device's steps to allow it.
 */
final class MicHelp
{
    /** The devices in the order the page lists them; the iPhone browsers share one set of steps. */
    private const DEVICES = [
        'iosSafari' => 'iosSafari', 'iosChrome' => 'iosBrowser', 'iosFirefox' => 'iosBrowser', 'iosEdge' => 'iosBrowser',
        'iosApp' => 'iosApp', 'iosOther' => 'iosOther', 'androidChrome' => 'androidChrome', 'samsung' => 'samsung',
        'androidFirefox' => 'androidFirefox', 'chrome' => 'chrome', 'edge' => 'edge', 'firefox' => 'firefox', 'safari' => 'safari',
    ];

    /** Why it won't start (an app's own browser has its own section below). */
    private const CAUSES = ['blocked', 'notFound', 'inUse', 'insecure', 'unsupported'];

    /**
     * @return list<array{0: string, 1: list<string>}>
     */
    public static function sections(string $whyHeading): array
    {
        $words = self::words();
        $fill = static fn (string $text, string $browser = ''): string => strtr($text, ['{browser}' => $browser !== '' ? $browser : (string) ($words['yourBrowser'] ?? '')]);

        $sections = [[$whyHeading, array_map(static fn (string $cause): string => $fill($words['cause'][$cause].'. '.$words['cause'][$cause.'Body']), self::CAUSES)]];

        foreach (self::DEVICES as $device => $steps) {
            $name = (string) $words['platform'][$device];
            $browser = strtok($name, ' ') ?: '';
            $sections[] = [$name, array_values(array_map(static fn (string $step): string => $fill($step, $browser), (array) $words['steps'][$steps]))];
        }

        foreach (['mac', 'windows'] as $system) {
            $sections[] = [(string) $words['system'][$system], array_values(array_map(static fn (string $step): string => $fill($step), (array) $words['steps'][$system]))];
        }

        return $sections;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    private static function words(): array
    {
        $all = json_decode((string) file_get_contents(dirname(base_path()).'/web/locales/en.json'), true, flags: JSON_THROW_ON_ERROR);

        return (array) ($all['micHelp'] ?? []);
    }
}
