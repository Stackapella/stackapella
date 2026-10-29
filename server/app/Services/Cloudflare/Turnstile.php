<?php

declare(strict_types=1);

namespace App\Services\Cloudflare;

use App\Support\Flags;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Cloudflare Turnstile, only in shield mode (an attack switch): the forms send its token, and this
 * checks it with Cloudflare (docs/trust-and-safety-plan.md, "Switches for an attack").
 */
final class Turnstile
{
    public function __construct(private readonly Flags $flags) {}

    /**
     * The site key the app shows the widget with, while shield mode is on.
     */
    public function siteKey(): ?string
    {
        $key = (string) config('services.turnstile.site_key');

        return $this->flags->on('shield_mode') && $key !== '' ? $key : null;
    }

    public function required(): bool
    {
        return $this->siteKey() !== null;
    }

    public function passes(?string $token, ?string $ip): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(10)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', array_filter([
                'secret' => (string) config('services.turnstile.secret'),
                'response' => $token,
                'remoteip' => $ip,
            ]));
        } catch (Throwable $e) {
            // Cloudflare unreachable during an attack: refuse, and say why in the logs.
            report($e);

            return false;
        }

        return $response->json('success') === true;
    }
}
