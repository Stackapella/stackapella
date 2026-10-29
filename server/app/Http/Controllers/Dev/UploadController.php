<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Local only: stands in for a presigned PUT to Spaces (App\Support\Storage\Uploads), so the app's
 * upload flow is the same on a laptop. The signed URL fixes the key, the content type and the exact
 * size, and like Spaces this refuses anything else with a 403.
 */
final class UploadController extends Controller
{
    public function __construct()
    {
        abort_unless(app()->environment('local', 'testing'), 404);
    }

    public function __invoke(Request $request, string $key): Response
    {
        $body = $request->getContent();

        if ($request->header('Content-Type') !== $request->query('type') || strlen($body) !== (int) $request->query('bytes')) {
            return response('', 403);
        }

        Storage::disk('private')->put($key, $body);

        return response('', 200);
    }
}
