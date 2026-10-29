<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\BetaSinger;
use App\Models\User;
use App\Support\Http\ApiError;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The beta singer's routes (after `auth`): the signed-in account must have a singer record
 * (403 not_beta_singer), still live (403 singer_revoked once Hillel revoked or forgot it).
 */
final class EnsureBetaSinger
{
    public const ATTRIBUTE = 'beta_singer';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $singer = $user instanceof User ? BetaSinger::query()->with('user')->where('user_id', $user->id)->first() : null;

        if ($singer === null) {
            return ApiError::response(403, 'not_beta_singer');
        }

        if (! $singer->isLive()) {
            return ApiError::response(403, 'singer_revoked');
        }

        $singer->forceFill(['last_seen_at' => now()])->saveQuietly();
        $request->attributes->set(self::ATTRIBUTE, $singer);

        return $next($request);
    }

    public static function singer(Request $request): BetaSinger
    {
        $singer = $request->attributes->get(self::ATTRIBUTE);

        return $singer instanceof BetaSinger ? $singer : abort(401);
    }
}
