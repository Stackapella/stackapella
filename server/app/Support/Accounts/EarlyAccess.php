<?php

declare(strict_types=1);

namespace App\Support\Accounts;

use App\Exceptions\Refusal;
use App\Models\AuditLog;
use App\Models\EarlyAccessLink;
use App\Models\User;
use App\Support\Trust\IpHash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * The secret early-access link (docs/mudeo-transition-plan.md): its code lets someone make an
 * account while sign-ups are paused, as a beta invite does, without making them a singer. Every
 * account made with an open code is marked with its link (that's "from mudeo"). A forwarded link
 * is held by its cap and a few sign-ups an IP address a day, and it can be paused or replaced.
 */
final class EarlyAccess
{
    /** Sign-ups through the link from one IP address a day. */
    public const PER_IP = 5;

    /**
     * A code's link of that kind, or with no kind, either link that lets a sign-up through (the
     * mudeo email's, or Hillel's shared invite; never the preview link).
     */
    public static function find(?string $code, ?string $label = null): ?EarlyAccessLink
    {
        return $code === null || $code === '' || strlen($code) > 100 ? null : EarlyAccessLink::query()
            ->when($label !== null, fn ($query) => $query->where('label', $label), fn ($query) => $query->whereIn('label', EarlyAccessLink::SIGN_UP))
            ->where('code_hash', hash('sha256', $code))->first();
    }

    /**
     * What the page says: open, paused, full or closed (an unknown code is closed too, so codes
     * can't be told apart by probing). The mudeo email's unless asked for another kind.
     */
    public static function status(string $code, string $label = EarlyAccessLink::MUDEO): string
    {
        return self::find($code, $label)?->status() ?? 'closed';
    }

    /**
     * Whether a sign-up with this code may go through paused sign-ups (either kind), or, with a
     * kind, whether that kind's code is open.
     */
    public static function opens(?string $code, ?string $label = null): bool
    {
        return self::find($code, $label)?->status() === 'open';
    }

    /**
     * A place on the link for a new account, under the link's lock (in the account's own
     * transaction), or null when the code isn't open now. The IP's daily count is checked first.
     */
    public static function claim(?string $code, ?string $ip): ?EarlyAccessLink
    {
        $found = self::find($code);

        if ($found === null) {
            return null;
        }

        // The network's HMAC, never its address (the cache keeps no raw IP: docs/privacy-audit.md).
        $key = 'early-access:'.IpHash::of($ip ?? 'unknown');

        if (RateLimiter::tooManyAttempts($key, self::PER_IP)) {
            return null;
        }

        $link = EarlyAccessLink::query()->whereKey($found->id)->lockForUpdate()->first();

        if ($link === null || $link->status() !== 'open') {
            return null;
        }

        $link->forceFill(['joined' => $link->joined + 1])->save();
        RateLimiter::hit($key, 86400);

        return $link;
    }

    /**
     * The open (or paused) link for a label, made the first time it's asked for.
     */
    public static function current(string $label = EarlyAccessLink::MUDEO): EarlyAccessLink
    {
        return DB::transaction(function () use ($label): EarlyAccessLink {
            $link = EarlyAccessLink::query()->where('label', $label)->whereNull('closed_at')->lockForUpdate()->latest('id')->first();

            return $link ?? self::make($label, $label === EarlyAccessLink::MUDEO ? EarlyAccessLink::DEFAULT_CAP : EarlyAccessLink::PREVIEW_CAP);
        });
    }

    /**
     * A new code: the old one closes at once (its page says "this link has closed"). The cap and a
     * pause carry over.
     */
    public static function replace(User $admin, ?string $ip, string $label = EarlyAccessLink::MUDEO): EarlyAccessLink
    {
        return DB::transaction(function () use ($admin, $ip, $label): EarlyAccessLink {
            $old = EarlyAccessLink::query()->where('label', $label)->whereNull('closed_at')->lockForUpdate()->latest('id')->first();
            $old?->forceFill(['closed_at' => now()])->save();
            $link = self::make($label, $old->cap ?? ($label === EarlyAccessLink::MUDEO ? EarlyAccessLink::DEFAULT_CAP : EarlyAccessLink::PREVIEW_CAP), $old?->paused_at);
            AuditLog::record('early_access.replaced', $link, $admin, before: $old !== null ? ['link' => $old->id, 'joined' => $old->joined] : null, ip: $ip);

            return $link;
        });
    }

    public static function url(EarlyAccessLink $link): string
    {
        return url(match ($link->label) {
            EarlyAccessLink::PREVIEW => '/preview/',
            EarlyAccessLink::INVITE => '/invite/',
            default => '/early/',
        }.$link->code);
    }

    private static function make(string $label, int $cap, mixed $pausedAt = null): EarlyAccessLink
    {
        $code = Str::random(40);
        $link = new EarlyAccessLink;
        $link->forceFill(['label' => $label, 'code_hash' => hash('sha256', $code), 'code' => $code, 'cap' => $cap, 'joined' => 0, 'paused_at' => $pausedAt])->save();

        return $link;
    }

    /**
     * For a sign-up while paused: refused unless the code is open.
     */
    public static function ensureOpen(?string $code): void
    {
        if (! self::opens($code)) {
            throw new Refusal(423, 'paused');
        }
    }
}
