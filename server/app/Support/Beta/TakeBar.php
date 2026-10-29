<?php

declare(strict_types=1);

namespace App\Support\Beta;

use App\Exceptions\Refusal;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;

/**
 * The bar a beta singer's take must clear before it can be sent (docs/decisions.md, "beta
 * singers' scores"): a pitch percent and a timing percent, set in admin. The app shows it and
 * offers Send only when a take clears it; the server checks it again, since the app can't be the
 * only gate.
 */
final class TakeBar
{
    public const KEY = 'beta.bar';

    /** @var array{pitchPercent: int, timingPercent: int} */
    public const DEFAULT = ['pitchPercent' => 90, 'timingPercent' => 80];

    /**
     * @return array{pitchPercent: int, timingPercent: int}
     */
    public static function current(): array
    {
        $saved = (array) (Setting::query()->where('key', self::KEY)->value('value') ?? []);

        return [
            'pitchPercent' => is_numeric($saved['pitchPercent'] ?? null) ? (int) $saved['pitchPercent'] : self::DEFAULT['pitchPercent'],
            'timingPercent' => is_numeric($saved['timingPercent'] ?? null) ? (int) $saved['timingPercent'] : self::DEFAULT['timingPercent'],
        ];
    }

    /**
     * @param  array{pitchPercent?: int, timingPercent?: int}  $changes
     * @return array{pitchPercent: int, timingPercent: int}
     */
    public static function set(User $admin, array $changes, ?string $ip): array
    {
        $before = self::current();
        $after = [...$before, ...array_map('intval', $changes)];
        $setting = Setting::query()->firstOrNew(['key' => self::KEY]);
        $setting->forceFill(['value' => $after, 'updated_by' => $admin->id])->save();
        AuditLog::record('beta.bar', null, $admin, before: $before, after: $after, ip: $ip);

        return $after;
    }

    /**
     * A take's own scores against the bar: no pitch, or under it, or timing under it (when there
     * was enough to time), is `422 below_bar` with the bar, for the app to show again.
     *
     * @param  array<string, mixed>|null  $scores
     */
    public static function ensureCleared(?array $scores): void
    {
        $bar = self::current();
        $pitch = $scores['pitchPercent'] ?? null;
        $timing = $scores['timingPercent'] ?? null;

        if (! is_numeric($pitch) || (float) $pitch < $bar['pitchPercent'] || (is_numeric($timing) && (float) $timing < $bar['timingPercent'])) {
            throw new Refusal(422, 'below_bar', ['bar' => $bar]);
        }
    }
}
