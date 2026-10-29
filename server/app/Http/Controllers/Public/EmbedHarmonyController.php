<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Harmony;
use App\Support\Sharing\HarmonyFacts;
use Illuminate\Http\Response;

/**
 * /embed/h/<id>: a shared harmony's player alone, for other sites to frame (Event Schedule's event
 * pages, by oEmbed; contract/api.md, "/embed/h/<id>"). The only page any https site may frame
 * (SecurityHeaders); no session, cached 5 minutes, never indexed.
 */
final class EmbedHarmonyController extends Controller
{
    private const HEADERS = ['Cache-Control' => 'public, max-age=300', 'X-Robots-Tag' => 'noindex'];

    public function __invoke(string $harmony, HarmonyFacts $facts): Response
    {
        $found = HarmonyFacts::live($harmony);

        if ($found === null) {
            $removed = Harmony::query()->where('public_id', $harmony)->whereNotNull('removed_at')->exists();

            return response()->view('public.embed-gone', ['reason' => $removed ? 'removed' : 'missing'], $removed ? 410 : 404, self::HEADERS);
        }

        return response()->view('public.embed-harmony', ['harmony' => $facts->page($found)], 200, self::HEADERS);
    }
}
