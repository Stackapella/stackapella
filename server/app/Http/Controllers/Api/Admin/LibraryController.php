<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Templates\Library;
use App\Actions\Templates\ModerateLibrary;
use App\Exceptions\Refusal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Library\DecideTemplateRequest;
use App\Http\Requests\Library\FeatureTemplateRequest;
use App\Http\Resources\Templates\LibraryItemResource;
use App\Models\Report;
use App\Models\Template;
use App\Models\User;
use App\Support\Gallery\GalleryViews;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * admin.library.* (N3): the templates waiting for review, Hillel's decisions, and the Featured
 * shelf.
 */
final class LibraryController extends Controller
{
    public function held(): JsonResponse
    {
        // The library's held templates, and any song made from imported sheet music with an open
        // report (private or in a group, it's never held in the library, but a notice must reach here).
        $openReport = static fn ($q) => $q->selectRaw('1')->from('reports')->where('reportable_type', (new Template)->getMorphClass())->whereColumn('reportable_id', 'templates.id')->where('status', Report::OPEN);
        $templates = Template::query()->with(['currentVersion', 'owner', 'parent.owner'])->whereNull('hidden_at')->whereNull('deleted_at')
            ->where(fn ($q) => $q->where(fn ($q) => $q->where('visibility', 'public')->whereNotNull('held_at'))->orWhere(fn ($q) => $q->whereNotNull('imported_at')->whereExists($openReport)))
            ->orderByRaw('coalesce(held_at, updated_at)')->limit(100)->get();
        $reports = Report::query()->where('reportable_type', (new Template)->getMorphClass())->whereIn('reportable_id', $templates->pluck('id'))->where('status', Report::OPEN)->get()->groupBy('reportable_id');

        return response()->json(['data' => array_values($templates->map(static fn (Template $template): array => [
            ...LibraryItemResource::of($template, null),
            // The library's view of its creator, or (a private imported song has none) the person as public pages show them.
            'creator' => [...(app(Library::class)->view($template, null)['creator'] ?? GalleryViews::person($template->owner)), 'id' => $template->owner?->sqid()],
            'declaration' => $template->declaration,
            'heldAt' => $template->held_at?->utc()->toIso8601ZuluString(),
            // Made from imported sheet music: private to its owner and their choir groups.
            'imported' => $template->isImported(),
            'reports' => [
                'count' => ($reports->get($template->id) ?? collect())->count(),
                'reasons' => array_values(($reports->get($template->id) ?? collect())->pluck('reason')->unique()->sort()->values()->all()),
            ],
        ])->all())]);
    }

    public function decide(DecideTemplateRequest $request, string $template, ModerateLibrary $moderate, #[CurrentUser] User $admin): JsonResponse
    {
        $moderate->decide(self::find($template), $admin, (string) $request->validated('decision'), (string) ($request->validated('note') ?? ''), $request->ip());

        return $this->held();
    }

    public function feature(FeatureTemplateRequest $request, string $template, ModerateLibrary $moderate, #[CurrentUser] User $admin): JsonResponse
    {
        $found = self::find($template);
        $moderate->feature($found, $admin, $request->boolean('featured'), $request->ip());

        return response()->json(['data' => LibraryItemResource::of($found->refresh()->load(['currentVersion', 'owner', 'parent.owner']), null)]);
    }

    private static function find(string $uuid): Template
    {
        // Public, or hidden whatever its owner set it to since (so it can still be shown again), or
        // made from imported sheet music, private or in a group (a copyright takedown reaches it).
        return Template::query()->where('slug', $uuid)->whereNotNull('owner_id')->where(fn ($q) => $q->where('visibility', 'public')->orWhereNotNull('hidden_at')->orWhereNotNull('imported_at'))->whereNull('deleted_at')->first() ?? throw new Refusal(404, 'not_found');
    }
}
