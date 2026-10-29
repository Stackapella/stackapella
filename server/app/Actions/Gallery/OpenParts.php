<?php

declare(strict_types=1);

namespace App\Actions\Gallery;

use App\Exceptions\Refusal;
use App\Jobs\CopyHarmonyStems;
use App\Models\AuditLog;
use App\Models\Harmony;
use App\Models\HarmonyStem;
use App\Models\Part;
use App\Models\Project;
use App\Models\Take;
use App\Models\User;
use App\Support\Flags;
use App\Support\Gallery\Gallery;
use App\Support\Gallery\GalleryViews;
use App\Support\Trust\TrustLevel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Open parts (N5; docs/more-features-plan.md, 5; contract/api.md, "Open parts (N5)"): the owner of
 * a public harmony leaves parts open, its other parts' takes are copied as stems, and anyone may
 * sing an open part in their own project and share it as a fill, which links back.
 */
final class OpenParts
{
    public function __construct(private readonly Flags $flags, private readonly Gallery $gallery, private readonly TrustLevel $trust) {}

    public function isOpen(): bool
    {
        return $this->flags->on('open_parts');
    }

    /**
     * harmonies.open: these parts left open (none closes them), the rest copied as stems.
     *
     * @param  list<string>  $parts
     */
    public function open(User $owner, string $publicId, array $parts, bool $guide, ?string $ip): Harmony
    {
        if (! $this->isOpen()) {
            throw new Refusal(409, 'open_parts_closed');
        }

        $harmony = Harmony::query()->with(['user', 'templateVersion.template'])->where('public_id', $publicId)->where('user_id', $owner->id)->whereNull('deleted_at')->first()
            ?? throw new Refusal(404, 'not_found');

        if ($parts === []) {
            $this->close($harmony);
            AuditLog::record('harmonies.parts_closed', $harmony, $owner, ip: $ip);

            return $harmony->refresh();
        }

        if (! $this->gallery->isPublic($harmony)) {
            throw new Refusal(409, 'not_public');
        }

        $template = $harmony->templateVersion?->template;

        if ($template === null || $template->deleted_at !== null || ! ($template->isOfficial() || ($template->isPublic() && $template->hidden_at === null))) {
            throw new Refusal(409, 'not_official');
        }

        if ($harmony->take_ids === null || $harmony->part_id !== null) {
            throw new Refusal(409, 'stems_missing');
        }

        $sung = self::sungParts($harmony);
        $parts = array_values(array_unique($parts));

        if (array_diff($parts, array_keys($sung)) !== [] || count($parts) >= count($sung)) {
            throw ValidationException::withMessages(['parts' => __('validation.open_parts')]);
        }

        DB::transaction(function () use ($harmony, $parts, $guide): void {
            Harmony::query()->whereKey($harmony->id)->lockForUpdate()->first(['id']);
            $harmony->forceFill(['open_parts' => $parts, 'open_parts_guide' => $guide, 'stems_ready_at' => null])->save();
        });

        $this->dropStems($harmony);
        AuditLog::record('harmonies.parts_opened', $harmony, $owner, after: ['parts' => $parts], ip: $ip);
        CopyHarmonyStems::dispatch($harmony->id);

        return $harmony->refresh();
    }

    /**
     * No parts open, and its stems gone.
     */
    public function close(Harmony $harmony): void
    {
        $harmony->forceFill(['open_parts' => null, 'stems_ready_at' => null])->save();
        $this->dropStems($harmony);
    }

    public function dropStems(Harmony $harmony): void
    {
        $keys = DB::transaction(function () use ($harmony): array {
            $stems = HarmonyStem::query()->where('harmony_id', $harmony->id)->lockForUpdate()->get(['id', 'file_key']);
            HarmonyStem::query()->whereKey($stems->pluck('id'))->delete();

            return $stems->pluck('file_key')->all();
        });

        Storage::disk('private')->delete($keys);
    }

    /**
     * The song's parts sung in its shared mix, by template part key (the take each was sung in).
     *
     * @return array<string, Take>
     */
    public static function sungParts(Harmony $harmony): array
    {
        $sung = [];

        foreach (Take::query()->with('part')->whereKey($harmony->take_ids ?? [])->get() as $take) {
            $key = $take->part?->template_part_key;

            if ($key !== null) {
                $sung[$key] = $take;
            }
        }

        return $sung;
    }

    /**
     * Whether this harmony has parts open to anyone now.
     */
    public function hasOpenParts(Harmony $harmony): bool
    {
        return $this->isOpen() && ($harmony->open_parts ?? []) !== [] && $this->gallery->isPublic($harmony);
    }

    /**
     * harmonies.stems: what a filler sings with.
     *
     * @return array<string, mixed>
     */
    public function stems(User $user, string $publicId): array
    {
        $harmony = Harmony::query()->with(['user', 'templateVersion.template'])->where('public_id', $publicId)->whereNull('deleted_at')->first();

        if ($harmony === null || ! $this->hasOpenParts($harmony) || $this->gallery->blockedBetween($user, $harmony->user_id)) {
            throw new Refusal(404, 'not_found');
        }

        Gallery::ensureAdult($user);

        if ($this->trust->of($user) < TrustLevel::VERIFIED) {
            throw new Refusal(403, 'email_unverified');
        }

        if ($harmony->stems_ready_at === null) {
            throw new Refusal(409, 'stems_not_ready');
        }

        return [
            'template' => ['id' => (string) $harmony->templateVersion?->template?->slug, 'version' => (int) $harmony->templateVersion?->version],
            'bpm' => (float) $harmony->bpm,
            'openParts' => $this->partNames($harmony),
            'stems' => HarmonyStem::query()->with('user')->where('harmony_id', $harmony->id)->orderBy('part_key')->get()->map(fn (HarmonyStem $stem): array => [
                'partKey' => $stem->part_key,
                'singer' => GalleryViews::person($stem->user),
                'url' => Storage::disk('private')->temporaryUrl($stem->file_key, now()->addHours(2)),
                'sampleRate' => $stem->sample_rate,
                'frames' => $stem->frames,
                'offsetFrames' => $stem->offset_frames,
                'trimStartFrames' => $stem->trim_start_frames,
                'trimEndFrames' => $stem->trim_end_frames,
                'volume' => (float) $stem->volume,
                'pan' => (float) $stem->pan,
                'framesPerPeak' => $stem->frames_per_peak,
                'peaks' => $stem->peaks ?? [],
            ])->values()->all(),
        ];
    }

    /**
     * Its open parts with their names, for its page and cards.
     *
     * @return list<array{key: string, name: string}>
     */
    public function partNames(Harmony $harmony): array
    {
        if (! $this->isOpen() || ($harmony->open_parts ?? []) === []) {
            return [];
        }

        $names = [];
        $harmony->loadMissing('templateVersion');

        foreach ((array) ($harmony->templateVersion->data['parts'] ?? []) as $part) {
            if (is_array($part) && is_string($part['key'] ?? null)) {
                $names[$part['key']] = (string) ($part['name'] ?? $part['key']);
            }
        }

        return array_values(array_map(static fn (string $key): array => ['key' => $key, 'name' => $names[$key] ?? $key], $harmony->open_parts ?? []));
    }

    /**
     * Sharing a fill (harmonies.create with parentId): the part is open on a public original, the
     * project is from the same song at its tempo, its takes are on that part, and nobody is
     * blocked. Answers the original and the stems' longest end, for the mix length check.
     *
     * @return array{parent: Harmony, seconds: float}
     */
    public function checkFill(User $user, Project $project, string $parentId, string $part): array
    {
        $parent = Harmony::query()->with(['user', 'templateVersion.template'])->where('public_id', $parentId)->whereNull('deleted_at')->first();

        if ($parent === null || ! $this->hasOpenParts($parent) || ! in_array($part, $parent->open_parts ?? [], true) || $this->gallery->blockedBetween($user, $parent->user_id)) {
            throw new Refusal(409, 'fill_mismatch');
        }

        $version = $parent->templateVersion;
        $sameSong = $version !== null && $project->template_slug !== null && $project->template_slug === $version->template->slug
            && (int) $project->template_version === $version->version;
        $sameTempo = abs((float) $project->bpm - (float) $parent->bpm) < 0.01;
        // Its original key: the original project's, or the song's own when that's gone.
        $originalKey = $parent->project_id !== null ? Project::query()->whereKey($parent->project_id)->value('music_key') : null;
        $originalKey ??= $version->data['key'] ?? null;
        $sameTempo = $sameTempo && $project->music_key === $originalKey;
        $onlyThatPart = Part::query()->where('project_id', $project->id)->whereNotNull('current_take_id')->where(fn ($q) => $q->whereNull('template_part_key')->orWhere('template_part_key', '!=', $part))->doesntExist();

        if (! $sameSong || ! $sameTempo || ! $onlyThatPart) {
            throw new Refusal(409, 'fill_mismatch');
        }

        $seconds = 0.0;

        foreach (HarmonyStem::query()->where('harmony_id', $parent->id)->get() as $stem) {
            $seconds = max($seconds, ($stem->frames + max(0, $stem->offset_frames)) / max(1, $stem->sample_rate));
        }

        return ['parent' => $parent, 'seconds' => $seconds];
    }

    /**
     * A fill as its original's page and the bell show it.
     *
     * @return array{id: string, title: string, owner: array<string, mixed>, part: string|null}|null
     */
    public static function parentOf(Harmony $fill): ?array
    {
        $parent = $fill->parent_id !== null ? Harmony::query()->whereKey($fill->parent_id)->whereNull('deleted_at')->first() : null;
        $parent?->setRelation('user', User::query()->find($parent->user_id));

        // Only while it's public: a hidden, private or withdrawn original isn't named.
        return $parent === null || ! app(Gallery::class)->isPublic($parent) ? null : ['id' => $parent->public_id, 'title' => $parent->title, 'owner' => GalleryViews::person($parent->user), 'part' => $fill->fill_part_key];
    }

    /**
     * @return array{id: string, title: string, owner: array<string, mixed>}|null
     */
    public function featuredFillOf(Harmony $harmony): ?array
    {
        $fill = $harmony->featured_fill_id !== null ? Harmony::query()->with('user')->whereKey($harmony->featured_fill_id)->first() : null;

        return $fill === null || ! $this->gallery->isPublic($fill) ? null : ['id' => $fill->public_id, 'title' => $fill->title, 'owner' => GalleryViews::person($fill->user)];
    }
}
