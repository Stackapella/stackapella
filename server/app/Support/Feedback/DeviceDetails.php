<?php

declare(strict_types=1);

namespace App\Support\Feedback;

/**
 * What a support message keeps of the device (docs/feedback-plan.md): a kind and a browser family, never the user
 * agent. The app sends both already; an older app's user agent is reduced to them here, before anything is kept.
 */
final class DeviceDetails
{
    public const KINDS = ['phone', 'tablet', 'computer'];

    public const FAMILIES = ['chrome', 'safari', 'firefox', 'edge', 'samsung', 'opera', 'other'];

    public static function kind(mixed $sent): ?string
    {
        if (! is_string($sent) || trim($sent) === '') {
            return null;
        }

        $value = strtolower(trim($sent));

        if (in_array($value, self::KINDS, true)) {
            return $value;
        }

        return match (true) {
            preg_match('/ipad|tablet|kindle|silk|playbook|(android(?!.*mobile))/', $value) === 1 => 'tablet',
            preg_match('/iphone|ipod|mobile|phone|android/', $value) === 1 => 'phone',
            default => 'computer',
        };
    }

    public static function family(mixed $sent): ?string
    {
        if (! is_string($sent) || trim($sent) === '') {
            return null;
        }

        $value = strtolower(trim($sent));

        if (in_array($value, self::FAMILIES, true)) {
            return $value;
        }

        // Order matters: Edge, Samsung and Opera say Chrome too, and Chrome says Safari.
        return match (true) {
            str_contains($value, 'edg') => 'edge',
            str_contains($value, 'samsungbrowser') || str_contains($value, 'samsung') => 'samsung',
            str_contains($value, 'opr/') || str_contains($value, 'opera') => 'opera',
            str_contains($value, 'firefox') || str_contains($value, 'fxios') => 'firefox',
            str_contains($value, 'chrome') || str_contains($value, 'crios') || str_contains($value, 'chromium') => 'chrome',
            str_contains($value, 'safari') => 'safari',
            default => 'other',
        };
    }

    /**
     * The context as kept: the device and browser reduced, nothing else changed.
     *
     * @param  array<string, mixed>|null  $context
     * @return array<string, mixed>|null
     */
    public static function reduce(?array $context): ?array
    {
        if ($context === null) {
            return null;
        }

        foreach (['device' => self::kind(...), 'browser' => self::family(...)] as $key => $reduce) {
            if (array_key_exists($key, $context)) {
                $context[$key] = $reduce($context[$key]);
            }
        }

        return $context;
    }
}
