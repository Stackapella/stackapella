<?php

declare(strict_types=1);

namespace App\Http\Resources\Templates;

use App\Actions\Templates\Library;
use App\Models\Template;
use App\Models\TemplateRecording;
use App\Support\Usage\Plans;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * contract/schemas/templates.mine.template.json. Needs versions, recordings and owner loaded.
 *
 * @property Template $resource
 */
final class OwnTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $template = $this->resource;
        $versions = $template->versions->sortBy('version')->pluck('version')->map(static fn (mixed $v): int => (int) $v)->values()->all();

        return [
            'id' => $template->slug,
            'title' => $template->title,
            'visibility' => $template->visibility,
            'version' => $versions === [] ? 0 : max($versions),
            'versions' => $versions,
            'libraryOptIn' => $template->library_opt_in,
            // Made from imported sheet music: private to its owner and their choir groups, and when
            // the singer confirmed the rights.
            'imported' => $template->isImported(),
            'rightsConfirmedAt' => $template->rights_confirmed_at?->utc()->toIso8601ZuluString(),
            // In the library (N3): its status, creator, uses and rating; null while not public.
            'library' => app(Library::class)->view($template, $template->owner),
            'parent' => Library::parent($template),
            // Lists work it out once for all (read_only); one template on its own asks.
            'readOnly' => (bool) ($template->hasAttribute('read_only') ? $template->getAttribute('read_only') : ($template->owner !== null && app(Plans::class)->templateReadOnly($template->owner, $template))),
            'recordings' => $template->recordings->sortBy('id')->map(static fn (TemplateRecording $recording): array => self::recording($recording))->values()->all(),
            'createdAt' => $template->created_at?->utc()->toIso8601ZuluString(),
            'updatedAt' => $template->updated_at?->utc()->toIso8601ZuluString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function recording(TemplateRecording $recording): array
    {
        return [
            'id' => $recording->uuid,
            'partKey' => $recording->part_key,
            'status' => $recording->status,
            'failure' => $recording->failure,
            'sampleRate' => $recording->sample_rate,
            'frames' => $recording->frames,
            'offsetFrames' => $recording->offset_frames,
            'createdAt' => $recording->created_at?->utc()->toIso8601ZuluString(),
        ];
    }
}
