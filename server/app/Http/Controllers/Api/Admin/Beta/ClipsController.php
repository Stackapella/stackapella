<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Beta;

use App\Actions\Courses\CourseClips;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * admin.beta.clips.list: every course clip from clips.json, with who's recording it and what's
 * published, for Admin, Singers.
 */
final class ClipsController extends Controller
{
    public function __invoke(CourseClips $clips): JsonResponse
    {
        return response()->json(['data' => $clips->forAdmin()]);
    }
}
