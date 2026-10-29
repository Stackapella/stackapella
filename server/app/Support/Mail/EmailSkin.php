<?php

declare(strict_types=1);

namespace App\Support\Mail;

use App\Models\User;

/**
 * Which skin an email wears (Hillel: "style user emails depending on the game mode settings";
 * docs/skins-plan.md): the recipient's own (appearance.skin, game until chosen, as in the app), and
 * serious for anyone without an account. Each skin is a palette the email views read, all inline
 * styles: the game one is the arcade cabinet's (always dark, every text colour at AA on its
 * panel), the serious one today's Warm. No web font in either: a font fetched when the email opens
 * is a request on open, which we don't make, so the game heading falls back to the boldest system
 * faces. The button and links take the recipient's accent (appearance.accent; Hillel: "make sure
 * emails use the user's accent color"): the app's light tuning on the serious card, its dark one
 * on the game card, a custom colour moved until it reads at AA there, and the button's words dark
 * or light, whichever reads better.
 */
final class EmailSkin
{
    public const GAME = 'game';

    public const SERIOUS = 'serious';

    /**
     * The app's accents (web/js/accent-colours.js, written by web/tool/accents.py): the light
     * theme's tuning for the serious card, the dark one for the game card.
     *
     * @var array<string, array{light: string, dark: string}>
     */
    private const ACCENTS = [
        'coral' => ['light' => '#bd3b26', 'dark' => '#fd826a'],
        'amber' => ['light' => '#925d0b', 'dark' => '#e69825'],
        'green' => ['light' => '#037a2f', 'dark' => '#58c66e'],
        'teal' => ['light' => '#0e7571', 'dark' => '#2ac3bb'],
        'sky' => ['light' => '#0d70a4', 'dark' => '#47b5fa'],
        'indigo' => ['light' => '#4463c7', 'dark' => '#88a7fd'],
        'violet' => ['light' => '#7853c5', 'dark' => '#b296ff'],
        'rose' => ['light' => '#b73975', 'dark' => '#fb78b0'],
    ];

    /**
     * The palette an email to this address wears: its account's skin (or `$skin` when given, for
     * the renderer) and accent (or `$accent`); serious and the default accent for anyone else.
     *
     * @param  array{accent?: string|null, customAccent?: string|null}|null  $accent  an appearance to use instead of the account's
     * @return array<string, string>
     */
    public static function paletteFor(?string $email, ?string $skin = null, ?array $accent = null): array
    {
        $user = $email !== null && $email !== '' ? User::query()->where('email', mb_strtolower(trim($email)))->first(['id', 'appearance']) : null;
        $skin ??= $user !== null ? self::of($user) : self::SERIOUS;

        return self::palette($skin, $accent ?? $user?->appearance);
    }

    /**
     * The skin of whoever has this address: their choice, game until they make one; serious when
     * no account has it.
     */
    public static function forAddress(?string $email): string
    {
        $user = $email !== null && $email !== '' ? User::query()->where('email', mb_strtolower(trim($email)))->first(['id', 'appearance']) : null;

        return $user !== null ? self::of($user) : self::SERIOUS;
    }

    public static function of(User $user): string
    {
        return ($user->appearance['skin'] ?? User::APPEARANCE_DEFAULT['skin']) === self::SERIOUS ? self::SERIOUS : self::GAME;
    }

    /**
     * The colours and faces an email's views use, with the accent of `$appearance` when it has one.
     *
     * @param  array{accent?: string|null, customAccent?: string|null}|null  $appearance
     * @return array<string, string>
     */
    public static function palette(string $skin, ?array $appearance = null): array
    {
        $base = self::base($skin);
        $accent = self::accent($skin, $appearance, $base['card']);

        if ($accent === null) {
            return $base;
        }

        return [...$base, 'button' => $accent, 'link' => $accent, 'buttonText' => self::readableOn($accent, $base['buttonInk'])];
    }

    /**
     * The recipient's accent tuned for this skin's card, or null for the skin's own.
     *
     * @param  array{accent?: string|null, customAccent?: string|null}|null  $appearance
     */
    private static function accent(string $skin, ?array $appearance, string $card): ?string
    {
        $name = $appearance['accent'] ?? null;
        $tuning = $skin === self::GAME ? 'dark' : 'light';

        if (is_string($name) && isset(self::ACCENTS[$name])) {
            return self::ACCENTS[$name][$tuning];
        }

        $custom = $appearance['customAccent'] ?? null;

        if ($name === 'custom' && is_string($custom) && preg_match('/^#[0-9a-fA-F]{6}$/', $custom) === 1) {
            return self::fit(strtolower($custom), $card);
        }

        return null;
    }

    /**
     * A custom colour moved toward white (on a dark card) or black (on a light one) until it reads
     * at AA (4.5:1) on the card, as the app fits it to each theme.
     */
    private static function fit(string $hex, string $card): string
    {
        $toward = self::luminance($card) < 0.5 ? 255 : 0;
        [$r, $g, $b] = self::rgb($hex);

        for ($step = 0; $step <= 20 && self::contrast(sprintf('#%02x%02x%02x', $r, $g, $b), $card) < 4.5; $step++) {
            [$r, $g, $b] = array_map(static fn (int $c): int => (int) round($c + ($toward - $c) * 0.1), [$r, $g, $b]);
        }

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    /**
     * White or the skin's dark ink on this colour, whichever reads better.
     */
    private static function readableOn(string $fill, string $dark): string
    {
        return self::contrast('#ffffff', $fill) >= self::contrast($dark, $fill) ? '#ffffff' : $dark;
    }

    public static function contrast(string $a, string $b): float
    {
        [$hi, $lo] = [max(self::luminance($a), self::luminance($b)), min(self::luminance($a), self::luminance($b))];

        return ($hi + 0.05) / ($lo + 0.05);
    }

    private static function luminance(string $hex): float
    {
        $linear = static fn (int $c): float => ($v = $c / 255) <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        [$r, $g, $b] = self::rgb($hex);

        return 0.2126 * $linear($r) + 0.7152 * $linear($g) + 0.0722 * $linear($b);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private static function rgb(string $hex): array
    {
        return [(int) hexdec(substr($hex, 1, 2)), (int) hexdec(substr($hex, 3, 2)), (int) hexdec(substr($hex, 5, 2))];
    }

    /**
     * Each skin's own colours, before any accent.
     *
     * @return array<string, string>
     */
    private static function base(string $skin): array
    {
        $body = "-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif";

        return $skin === self::GAME ? [
            'skin' => self::GAME,
            'page' => '#0c0a17',
            'card' => '#12101e',
            'line' => '#2a2640',
            'inset' => '#1a1729',
            'text' => '#f7f4ff',
            'muted' => '#b1abc9',
            'heading' => '#ffd84a',
            // Bungee where a mail app has it installed; else the heaviest system faces, in capitals.
            'headingFont' => "'Bungee','Arial Black','Arial Bold',Gadget,Impact,sans-serif",
            'headingCase' => 'uppercase',
            'button' => '#ff4fa8',
            'buttonText' => '#1d0712',
            'buttonInk' => '#07060e',
            'link' => '#3be7ff',
            'hud' => '#3be7ff',
            'body' => $body,
            'mark' => '#000000',
        ] : [
            'skin' => self::SERIOUS,
            'page' => '#faf5ee',
            'card' => '#ffffff',
            'line' => '#e0d5c6',
            'inset' => '#faf5ee',
            'text' => '#211a15',
            'muted' => '#685d53',
            'heading' => '#211a15',
            'headingFont' => $body,
            'headingCase' => 'none',
            'button' => '#0e7571',
            'buttonText' => '#ffffff',
            'buttonInk' => '#211a15',
            'link' => '#0e7571',
            'hud' => '#685d53',
            'body' => $body,
            'mark' => '#000000',
        ];
    }
}
