<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Courses;

use App\Actions\Courses\CourseClips;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * courses.clips.list: a course's real-voice clips, for its steps (CourseClips). Anyone may read it.
 */
final class ClipsController extends Controller
{
    public function __invoke(string $course, CourseClips $clips): JsonResponse
    {
        return response()->json(['data' => $clips->published($course)]);
    }
}
