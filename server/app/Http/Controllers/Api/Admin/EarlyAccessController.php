<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Mudeo\UpdateEarlyAccessRequest;
use App\Models\AuditLog;
use App\Models\EarlyAccessLink;
use App\Models\User;
use App\Support\Accounts\EarlyAccess;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * admin.earlyAccess.*: the mudeo email's secret link (docs/mudeo-transition-plan.md), and
 * admin.preview.*: Hillel's preview link, the same way (the route says which).
 */
final class EarlyAccessController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return $this->view(EarlyAccess::current(self::label($request)));
    }

    public function update(UpdateEarlyAccessRequest $request, #[CurrentUser] User $admin): JsonResponse
    {
        $link = EarlyAccess::current(self::label($request));
        $before = ['cap' => $link->cap, 'paused' => $link->paused_at !== null];

        if ($request->has('cap')) {
            $link->cap = (int) $request->validated('cap');
        }

        if ($request->has('paused')) {
            $link->paused_at = $request->boolean('paused') ? ($link->paused_at ?? now()) : null;
        }

        $link->save();
        AuditLog::record('early_access.updated', $link, $admin, before: $before, after: ['cap' => $link->cap, 'paused' => $link->paused_at !== null], ip: $request->ip());

        return $this->view($link);
    }

    public function replace(Request $request, #[CurrentUser] User $admin): JsonResponse
    {
        return $this->view(EarlyAccess::replace($admin, $request->ip(), self::label($request)));
    }

    private static function label(Request $request): string
    {
        return in_array($request->route('label'), [EarlyAccessLink::PREVIEW, EarlyAccessLink::INVITE], true) ? (string) $request->route('label') : EarlyAccessLink::MUDEO;
    }

    private function view(EarlyAccessLink $link): JsonResponse
    {
        $earlier = EarlyAccessLink::query()->where('label', $link->label)->whereNotNull('closed_at')->latest('id')->limit(20)->get();

        return response()->json(['data' => [
            'url' => EarlyAccess::url($link),
            'status' => $link->status(),
            'cap' => $link->cap,
            'joined' => $link->joined,
            'paused' => $link->paused_at !== null,
            'createdAt' => $link->created_at?->utc()->toIso8601ZuluString(),
            'earlier' => array_values(array_map(static fn (EarlyAccessLink $old): array => ['joined' => $old->joined, 'createdAt' => $old->created_at?->utc()->toIso8601ZuluString(), 'closedAt' => $old->closed_at?->utc()->toIso8601ZuluString()], $earlier->all())),
        ]]);
    }
}
