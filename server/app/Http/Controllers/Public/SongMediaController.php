<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\Pages\SongMedia;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * A song story's picture, one of its WebP sizes (SongMedia): from the content's own files, never another site's, kept a
 * year (its address carries its bytes' hash).
 */
final class SongMediaController extends Controller
{
    public function __invoke(string $slug, string $file): BinaryFileResponse
    {
        $path = SongMedia::served($slug, $file);
        abort_if($path === null, 404);

        return response()->file($path, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
