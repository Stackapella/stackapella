<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Beta;

use App\Actions\Beta\ForgetSinger;
use App\Actions\Beta\InviteSinger;
use App\Actions\Beta\MarkSingerPaid;
use App\Actions\Beta\RenewInviteLink;
use App\Actions\Beta\RevokeInvite;
use App\Actions\Beta\UpdateSinger;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Beta\RenewLinkRequest;
use App\Http\Requests\Admin\Beta\StoreSingerRequest;
use App\Http\Requests\Admin\Beta\UpdateSingerRequest;
use App\Http\Resources\Admin\BetaSingerResource;
use App\Models\BetaSinger;
use App\Models\Template;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * admin.beta.singers.*: inviting singers, their links, and what they're owed.
 */
final class SingersController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $singers = BetaSinger::query()->with(BetaSingerResource::relationsForList())->orderByDesc('id')->get();

        return response()->json(['data' => $singers->map(static fn (BetaSinger $singer): array => BetaSingerResource::fields($singer))->values()]);
    }

    public function store(StoreSingerRequest $request, InviteSinger $invite, #[CurrentUser] User $admin): JsonResponse
    {
        /** @var list<array{templateId: string, partKey: string, minutes?: int|null}> $parts */
        $parts = (array) $request->validated('parts');
        $templates = Template::query()->official()->whereIn('slug', array_column($parts, 'templateId'))->pluck('id', 'slug');
        $expiresAt = $request->validated('expiresAt');

        $result = $invite->handle(
            (string) $request->validated('name'),
            $request->validated('email'),
            $request->validated('note'),
            is_string($expiresAt) ? Carbon::parse($expiresAt)->utc() : null,
            $request->validated('hourlyRateCents'),
            $request->validated('currency'),
            array_map(static fn (array $part): array => ['template_id' => (int) $templates[$part['templateId']], 'part_key' => $part['partKey'], 'minutes' => $part['minutes'] ?? null], $parts),
            $admin,
            $request->ip(),
        );

        return $this->withLink($result['singer'], $result['token'], 201);
    }

    public function update(UpdateSingerRequest $request, BetaSinger $singer, UpdateSinger $update, #[CurrentUser] User $admin): JsonResponse
    {
        return $this->singer($update->handle($singer, $request->validated(), $admin, $request->ip()));
    }

    public function link(RenewLinkRequest $request, BetaSinger $singer, RenewInviteLink $renew, #[CurrentUser] User $admin): JsonResponse
    {
        $expiresAt = $request->validated('expiresAt');
        $token = $renew->handle($singer, is_string($expiresAt) ? Carbon::parse($expiresAt)->utc() : null, $admin, $request->ip());

        return $this->withLink($singer, $token, 200);
    }

    public function revoke(Request $request, BetaSinger $singer, RevokeInvite $revoke, #[CurrentUser] User $admin): JsonResponse
    {
        $revoke->handle($singer, $admin, $request->ip());

        return $this->singer($singer);
    }

    public function paid(Request $request, BetaSinger $singer, MarkSingerPaid $markPaid, #[CurrentUser] User $admin): JsonResponse
    {
        $markPaid->handle($singer, $admin, $request->ip());

        return $this->singer($singer);
    }

    public function forget(Request $request, BetaSinger $singer, ForgetSinger $forget, #[CurrentUser] User $admin): JsonResponse
    {
        $forget->handle($singer, $admin, $request->ip());

        return $this->singer($singer);
    }

    private function singer(BetaSinger $singer): JsonResponse
    {
        return response()->json(['data' => BetaSingerResource::fields($singer->refresh()->load(BetaSingerResource::RELATIONS))]);
    }

    private function withLink(BetaSinger $singer, string $token, int $status): JsonResponse
    {
        return response()->json([
            'data' => BetaSingerResource::fields($singer->refresh()->load(BetaSingerResource::RELATIONS)),
            'link' => url('/beta/'.$token),
        ], $status);
    }
}
