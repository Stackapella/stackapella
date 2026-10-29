<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Beta;

use App\Actions\Beta\ManageGuides;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\BetaGuideResource;
use App\Models\TemplateGuide;
use App\Models\User;
use App\Support\Beta\GuideOverview;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * admin.beta.guides.*: every template's real voices, per part.
 */
final class GuidesController extends Controller
{
    public function index(Request $request, GuideOverview $overview): JsonResponse
    {
        $slug = $request->query('template');

        return response()->json(['data' => $overview->templates(is_string($slug) ? $slug : null)]);
    }

    public function default(Request $request, TemplateGuide $guide, ManageGuides $manage, #[CurrentUser] User $admin): BetaGuideResource
    {
        $manage->makeDefault($guide, $admin, $request->ip());

        return new BetaGuideResource($guide->refresh()->load('submission.assignment.singer.user'));
    }

    public function destroy(Request $request, TemplateGuide $guide, ManageGuides $manage, #[CurrentUser] User $admin): Response
    {
        $manage->remove($guide, $admin, $request->ip());

        return response()->noContent();
    }
}
