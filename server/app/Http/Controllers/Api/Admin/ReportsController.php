<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Sharing\ModerateHarmony;
use App\Exceptions\Refusal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListReportsRequest;
use App\Http\Requests\Admin\ModerateHarmonyRequest;
use App\Http\Requests\Admin\RestoreHarmonyRequest;
use App\Http\Resources\Admin\HarmonyQueueResource;
use App\Models\Harmony;
use App\Models\Report;
use App\Models\User;
use App\Support\Http\CursorList;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * admin.reports.list and admin.harmonies.hide, .restore and .remove: the trust and safety queue's
 * shared harmonies (docs/trust-and-safety-plan.md, "Admin controls").
 */
final class ReportsController extends Controller
{
    public function index(ListReportsRequest $request): JsonResponse
    {
        $open = $request->validated('status', 'open') === 'open';

        $page = $this->query()
            ->when($open, fn (Builder $query) => $query
                ->whereNull('deleted_at')
                ->where(fn (Builder $waiting) => $waiting
                    ->whereHas('reports', fn (Builder $reports) => $reports->where('status', Report::OPEN))
                    ->orWhere(fn (Builder $held) => $held->whereNotNull('title_held_at')->whereNull('hidden_at'))
                    ->orWhere('hidden_reason', Harmony::HIDDEN_REPORTS))
                ->orderBy('id'))
            ->when(! $open, fn (Builder $query) => $query
                ->whereDoesntHave('reports', fn (Builder $reports) => $reports->where('status', Report::OPEN))
                ->where(fn (Builder $released) => $released->whereNull('title_held_at')->orWhereNotNull('hidden_at'))
                ->where(fn (Builder $decided) => $decided->whereNull('hidden_reason')->orWhere('hidden_reason', '!=', Harmony::HIDDEN_REPORTS))
                ->where(fn (Builder $dealt) => $dealt->whereHas('reports')->orWhereNotNull('hidden_reason'))
                ->orderByDesc('id'))
            ->cursorPaginate(25);

        return CursorList::response($page, HarmonyQueueResource::class, $request);
    }

    public function hide(ModerateHarmonyRequest $request, string $harmony, ModerateHarmony $moderate, #[CurrentUser] User $admin): JsonResponse
    {
        return $this->item($moderate->hide($this->find($harmony), $admin, (string) $request->validated('reason'), (string) $request->validated('note'), $request->ip()));
    }

    public function restore(RestoreHarmonyRequest $request, string $harmony, ModerateHarmony $moderate, #[CurrentUser] User $admin): JsonResponse
    {
        $note = $request->validated('note');

        return $this->item($moderate->restore($this->find($harmony), $admin, is_string($note) ? $note : null, $request->ip()));
    }

    public function remove(ModerateHarmonyRequest $request, string $harmony, ModerateHarmony $moderate, #[CurrentUser] User $admin): JsonResponse
    {
        return $this->item($moderate->remove($this->find($harmony), $admin, (string) $request->validated('reason'), (string) $request->validated('note'), $request->ip()));
    }

    /**
     * @return Builder<Harmony>
     */
    private function query(): Builder
    {
        return Harmony::query()
            ->with(['user', 'reports.reporter'])
            ->select('harmonies.*')
            // The owner's harmonies Hillel took down, ever (repeat infringers).
            ->selectSub(fn ($query) => $query->from('harmonies as taken')->selectRaw('count(*)')->whereColumn('taken.user_id', 'harmonies.user_id')->whereIn('taken.hidden_reason', [Harmony::HIDDEN_DMCA, Harmony::HIDDEN_ADMIN]), 'takedowns_count');
    }

    private function find(string $sqid): Harmony
    {
        return Harmony::query()->where('public_id', $sqid)->first() ?? throw new Refusal(404, 'not_found');
    }

    private function item(Harmony $harmony): JsonResponse
    {
        $loaded = $this->query()->whereKey($harmony->id)->firstOrFail();

        return (new HarmonyQueueResource($loaded))->response();
    }
}
