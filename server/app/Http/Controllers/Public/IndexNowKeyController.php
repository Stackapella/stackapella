<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Actions\Site\SubmitToIndexNow;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * /<key>.txt: IndexNow's proof that the pages we send are ours (the key, as plain text, at the root
 * of the host the addresses are on). Any other name, and on a self-hosted install, is a 404.
 */
final class IndexNowKeyController extends Controller
{
    public function __invoke(string $key): Response
    {
        abort_if(config('selfhost.enabled') === true || ! hash_equals(SubmitToIndexNow::key(), $key), 404);

        return response(SubmitToIndexNow::key(), 200, ['Content-Type' => 'text/plain; charset=utf-8', 'Cache-Control' => 'public, max-age=86400']);
    }
}
