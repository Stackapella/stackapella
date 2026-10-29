<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Audio\Ffmpeg;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * The health check for App Platform and uptime monitors (iaw's): the database answers and the
 * cache can write and read. Works on any host, including the ondigitalocean.app one.
 */
final class HealthController extends Controller
{
    public function __invoke(Ffmpeg $ffmpeg): JsonResponse
    {
        try {
            DB::select('select 1');

            $key = 'health:'.Str::random(8);
            Cache::put($key, 'ok', 10);
            $cacheOk = Cache::pull($key) === 'ok';
        } catch (Throwable $e) {
            report($e);

            return response()->json(['status' => 'error'], 503)->header('Cache-Control', 'no-store');
        }

        // A framework default where our name belongs (or a missing piece the work needs) (an app made without APP_NAME): degraded,
        // and reported, so a fresh deploy can't send "Laravel" email unnoticed.
        $defaults = array_keys(array_filter([
            'app_name' => config('app.name') === 'Laravel',
            'mail_from_name' => config('mail.from.name') === 'Laravel',
            'mail_from_address' => str_ends_with((string) config('mail.from.address'), '@example.com'),
            // Stripe set up without the webhook's secret: webhooks are refused in production.
            'stripe_webhook_secret' => (string) config('cashier.secret') !== '' && (string) config('cashier.webhook.secret') === '',
            // ffmpeg without H.264 or AAC: share videos (or every mix) would fail. Asked once an hour,
            // as the health check runs every few seconds.
            'ffmpeg_encoders' => Cache::remember('health:ffmpeg-encoders', 3600, fn (): array => $ffmpeg->missingEncoders()) !== [],
        ]));

        if ($defaults !== [] && app()->isProduction()) {
            report(new RuntimeException('Framework defaults in production: '.implode(', ', $defaults)));
        }

        return response()->json(['status' => $cacheOk && ($defaults === [] || ! app()->isProduction()) ? 'ok' : 'degraded', 'build' => config('app.build'), 'builtAt' => config('app.built_at')])
            ->header('Cache-Control', 'no-store');
    }
}
