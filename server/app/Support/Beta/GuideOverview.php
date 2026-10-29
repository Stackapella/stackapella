<?php

declare(strict_types=1);

namespace App\Support\Beta;

use App\Http\Resources\Admin\BetaGuideResource;
use App\Models\Template;
use App\Models\TemplateGuide;

/**
 * Every template's real voices, per part (default first, then alternates), and how many are left
 * from older versions: "3 of 4 parts are real voices" (admin.beta.guides.list).
 */
final class GuideOverview
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function templates(?string $slug): array
    {
        $templates = Template::query()->official()
            ->with('currentVersion')
            ->when($slug !== null && $slug !== '', fn ($query) => $query->where('slug', $slug))
            ->whereNotNull('current_version_id')
            ->orderBy('title')
            ->get();

        $guides = TemplateGuide::query()
            ->with(['submission.assignment.singer.user', 'creditUser', 'templateVersion'])
            ->whereHas('templateVersion', fn ($query) => $query->whereIn('template_id', $templates->pluck('id')))
            ->orderByDesc('is_default')
            ->orderByDesc('published_at')
            ->get();

        return $templates->map(function (Template $template) use ($guides): array {
            $version = $template->currentVersion;
            $current = $guides->where('template_version_id', $version?->id);
            /** @var list<array{key: string, name: string}> $parts */
            $parts = (array) ($version->data['parts'] ?? []);

            return [
                'id' => $template->slug,
                'version' => (int) $version?->version,
                'title' => $template->title,
                'reviewed' => (bool) $version?->reviewed,
                'parts' => array_map(static fn (array $part): array => [
                    'key' => $part['key'],
                    'name' => $part['name'],
                    'guides' => $current->where('part_key', $part['key'])->map(static fn (TemplateGuide $g): array => BetaGuideResource::fields($g))->values()->all(),
                ], $parts),
                'olderGuides' => $guides->filter(static fn (TemplateGuide $g): bool => $g->templateVersion->template_id === $template->id && $g->template_version_id !== $version?->id)->count(),
            ];
        })->values()->all();
    }
}
