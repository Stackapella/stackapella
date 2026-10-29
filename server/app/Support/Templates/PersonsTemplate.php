<?php

declare(strict_types=1);

namespace App\Support\Templates;

use App\Models\Template;
use App\Models\TemplateRecording;
use App\Models\TemplateVersion;
use App\Support\Gallery\GalleryViews;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * What templates.show answers for a person's own template (and ideas.show inlines): the template
 * JSON, its creator's recorded guides by temporary URL (2 hours), and why the caller may have it.
 */
final class PersonsTemplate
{
    /**
     * @return Collection<int, TemplateRecording>
     */
    public static function recordings(Template $template): Collection
    {
        return TemplateRecording::query()->with('user')->where('template_id', $template->id)->where('status', TemplateRecording::READY)->orderBy('part_key')->get();
    }

    /**
     * @param  Collection<int, TemplateRecording>  $recordings
     * @param  array<string, mixed>|null  $library
     * @return array<string, mixed>
     */
    public static function payload(TemplateVersion $version, Collection $recordings, string $grant, ?array $library = null): array
    {
        return [
            'template' => $version->data,
            'voices' => $recordings->map(fn (TemplateRecording $r): array => [
                'id' => $r->uuid,
                'partKey' => $r->part_key,
                'url' => Storage::disk('private')->temporaryUrl((string) $r->file_key, now()->addHours(2)),
                'sampleRate' => $r->sample_rate,
                'frames' => $r->frames,
                'offsetFrames' => $r->offset_frames,
                'credit' => GalleryViews::person($r->user)['name'],
                'creditHandle' => GalleryViews::person($r->user)['handle'],
                'default' => true,
            ])->values()->all(),
            'grant' => $grant,
            'freeUntil' => null,
            // In the library: its creator (for credits in exports), uses and rating.
            ...($library !== null ? ['library' => $library] : []),
        ];
    }
}
