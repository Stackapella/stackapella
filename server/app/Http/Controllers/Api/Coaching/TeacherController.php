<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coaching;

use App\Actions\Coaching\Conversations;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Coaching\CoachingView;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * teacher.links.*: Send to my teacher (Plus).
 */
final class TeacherController extends Controller
{
    public function __construct(private readonly Conversations $conversations) {}

    public function store(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $request->validate(['name' => ['required', 'string', 'min:1', 'max:80'], 'adult' => ['nullable', 'boolean']]);
        $made = $this->conversations->inviteTeacher($user, (string) $request->input('name'), $request->boolean('adult'));
        $link = $made['link'];

        return response()->json(['data' => ['id' => $link->sqid(), 'name' => $link->name, 'status' => $link->status, 'createdAt' => $link->created_at?->utc()->toIso8601ZuluString(), 'url' => $made['url']]], 201);
    }

    public function show(string $token): JsonResponse
    {
        return response()->json(['data' => ['singer' => ['name' => $this->conversations->teacherLink($token)->singer->name]]]);
    }

    public function accept(Request $request, string $token, #[CurrentUser] User $user): JsonResponse
    {
        $request->validate(['adult' => ['nullable', 'boolean']]);
        $engagement = $this->conversations->acceptTeacher($user, $token, $request->boolean('adult'));

        return response()->json(['data' => ['engagement' => CoachingView::engagement($engagement->load('rating'), $user), 'reviews' => []]]);
    }
}
