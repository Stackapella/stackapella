<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Beta;

use App\Actions\Courses\CourseClips;
use App\Exceptions\Refusal;
use App\Http\Controllers\Controller;
use App\Models\CourseClip;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * admin.beta.clips.delete (Lend your voice, slice 3): a course clip's published voice comes down,
 * the synth plays again; for abuse, the volunteer who sang it can't claim again.
 */
final class ClipRemovalController extends Controller
{
    public function __invoke(Request $request, string $clip, CourseClips $clips, #[CurrentUser] User $admin): Response
    {
        $request->validate(['abuse' => ['sometimes', 'boolean']]);
        $found = CourseClip::query()->where('slot', $clip)->whereNotNull('media_key')->first() ?? throw new Refusal(404, 'not_found');
        $clips->remove($found, $admin, $request->boolean('abuse'), $request->ip());

        return response()->noContent();
    }
}
