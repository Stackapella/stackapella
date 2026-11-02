<?php

declare(strict_types=1);

namespace App\Support\Legal;

/**
 * The DMCA designated agent (config site.dmca_agent): named on the copyright page once registered,
 * and needed before sheet music import is switched on.
 */
final class DmcaAgent
{
    /**
     * Registered: a name, an address and an email, at least.
     */
    public static function registered(): bool
    {
        $agent = (array) config('site.dmca_agent');

        return trim((string) ($agent['name'] ?? '')) !== '' && trim((string) ($agent['address'] ?? '')) !== '' && trim((string) ($agent['email'] ?? '')) !== '';
    }

    /**
     * The copyright page's line about the agent: who they are, or that they're being registered.
     */
    public static function line(): string
    {
        if (! self::registered()) {
            return 'Our designated agent is being registered with the US Copyright Office. Until then, send notices to '.config('site.legal_email').'.';
        }

        $agent = (array) config('site.dmca_agent');
        $phone = trim((string) ($agent['phone'] ?? ''));

        return 'Our designated agent: '.trim((string) $agent['name']).', '.trim((string) $agent['address']).($phone !== '' ? ', telephone '.$phone : '').', '.trim((string) $agent['email']).'.';
    }
}
