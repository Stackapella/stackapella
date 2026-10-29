<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Support\Http\Hosts;
use Illuminate\Http\Response;

/**
 * /.well-known/security.txt (RFC 9116; docs/operations-plan.md, "Security housekeeping"): where to
 * report a security problem, until when this is current, and the short policy page.
 */
final class SecurityTxtController extends Controller
{
    public function __invoke(): Response
    {
        $base = Hosts::publicUrl();
        // The same file on both hosts, so each names both as canonical.
        $canonical = array_map(static fn (string $host): string => 'Canonical: '.$host.'/.well-known/security.txt', array_values(array_unique([$base, Hosts::appUrl()])));
        $body = implode("\n", [
            'Contact: mailto:'.config('ops.security_contact'),
            'Expires: '.now()->addYear()->startOfMonth()->utc()->format('Y-m-d\TH:i:s\Z'),
            'Preferred-Languages: en',
            ...$canonical,
            'Policy: '.$base.'/legal/security',
            '',
        ]);

        return response($body, 200, ['Content-Type' => 'text/plain; charset=utf-8', 'Cache-Control' => 'public, max-age=86400']);
    }
}
