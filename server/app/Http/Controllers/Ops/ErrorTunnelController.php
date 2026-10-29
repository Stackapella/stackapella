<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * POST /log/errors: the Sentry browser SDK's tunnel (after iaw's), so the app's connect-src stays
 * 'self'. Only envelopes for our own browser DSN are forwarded, never an open relay; the app
 * scrubs its events before sending (no recordings, no account details).
 */
final class ErrorTunnelController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $ours = (string) config('sentry.browser_dsn');
        $envelope = $request->getContent();

        if ($ours === '' || $envelope === '' || strlen($envelope) > 200_000) {
            return new Response('', 204);
        }

        $header = json_decode((string) strtok($envelope, "\n"), true);

        if (! is_array($header) || ($header['dsn'] ?? null) !== $ours) {
            return new Response('', 400);
        }

        $parts = parse_url($ours);

        try {
            Http::withBody($envelope, 'application/x-sentry-envelope')->timeout(5)
                ->post(sprintf('%s://%s/api/%s/envelope/', $parts['scheme'] ?? 'https', $parts['host'] ?? '', trim((string) ($parts['path'] ?? ''), '/')));
        } catch (Throwable $e) {
            // Reporting must never fail a page (and report() would only loop back to Sentry).
            Log::warning('The Sentry tunnel could not forward an envelope: '.$e->getMessage());
        }

        return new Response('', 200);
    }
}
