<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Leagues;

use App\Actions\Leagues\Leagues;
use App\Exceptions\Refusal;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * leagues.* and friends.leaderboard (N2; contract/api.md, "Leagues (N2)").
 */
final class LeaguesController extends Controller
{
    public function __construct(private readonly Leagues $leagues) {}

    public function current(#[CurrentUser] User $user): JsonResponse
    {
        $this->ensureOpen();

        return response()->json(['data' => $this->leagues->current($user)]);
    }

    public function join(#[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => $this->leagues->join($user)]);
    }

    public function leave(#[CurrentUser] User $user): Response
    {
        $this->leagues->leave($user);

        return response()->noContent();
    }

    public function friends(#[CurrentUser] User $user): JsonResponse
    {
        $this->ensureOpen();

        return response()->json(['data' => $this->leagues->friends($user)]);
    }

    private function ensureOpen(): void
    {
        if (! $this->leagues->isOpen()) {
            throw new Refusal(404, 'not_found');
        }
    }
}
