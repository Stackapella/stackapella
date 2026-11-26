<?php

declare(strict_types=1);

namespace App\Actions\OwnChoir;

use App\Exceptions\Refusal;
use App\Models\AuditLog;
use App\Models\ChoirGalleryItem;
use App\Models\Harmony;
use App\Models\OwnChoirRequest;
use App\Models\Report;
use App\Models\User;
use App\Support\OwnChoir\ChoirGallery;
use App\Support\Rights\RightsHere;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * The choir gallery, version 1 (docs/choir-gallery-plan.md): a singer adds their choir song (its own share, the exact
 * mix staff hear) or takes it out; anyone lists and plays the approved ones, never by name; staff approve, decline,
 * take down or release after reports (audited).
 */
final class ChoirGalleryItems
{
    private const EAGER = ['user', 'harmony.user', 'request.parts', 'request.templateVersion.template'];

    /**
     * ownChoir.gallery.add.
     *
     * @param  array{harmonyId: string, consentVersion: int, wordsHash: string, locale: string}  $data
     */
    public function add(User $user, string $song, array $data): ChoirGalleryItem
    {
        $request = $this->song($user, $song);

        if ((int) $data['consentVersion'] !== ChoirGallery::consentVersion()) {
            throw new Refusal(409, 'agreement_changed');
        }

        $cannot = ChoirGallery::cannot($request, $user);

        if ($cannot === 'limit_day') {
            throw new Refusal(429, 'rate_limited');
        }

        if ($cannot === 'limit_live') {
            throw new Refusal(409, 'limit_reached');
        }

        if ($cannot !== null) {
            throw new Refusal(409, 'not_eligible', ['reason' => $cannot]);
        }

        // The owner's own live share of this song, with only their own voice in it.
        $harmony = Harmony::query()->where('public_id', $data['harmonyId'])->where('user_id', $user->id)->first();
        $template = $request->templateVersion?->template_id;

        if ($harmony === null || ! $harmony->isLive() || (array) $harmony->own_voice_parts === [] || (array) $harmony->own_voice_friends !== []
            || $harmony->templateVersion?->template_id !== $template) {
            throw new Refusal(409, 'not_eligible', ['reason' => 'share']);
        }

        $item = $request->galleryItem ?? new ChoirGalleryItem;
        $item->forceFill([
            'user_id' => $user->id,
            'own_choir_request_id' => $request->id,
            'harmony_id' => $harmony->id,
            'parts_sha' => ChoirGallery::partsSha($request),
            'status' => ChoirGalleryItem::PENDING,
            'reason' => null,
            'reason_text' => null,
            'consent_version' => (int) $data['consentVersion'],
            'words_hash' => $data['wordsHash'],
            'locale' => $data['locale'],
            'added_at' => now(),
            'decided_at' => null,
            'decided_by' => null,
            'released_at' => null,
        ])->save();

        return $item->load(self::EAGER);
    }

    /** ownChoir.gallery.remove: out at once (its share stays until they stop sharing). */
    public function remove(User $user, string $song): void
    {
        $item = $this->song($user, $song)->galleryItem;

        if ($item === null || $item->status === ChoirGalleryItem::REMOVED) {
            throw new Refusal(404, 'not_found');
        }

        $item->forceFill(['status' => ChoirGalleryItem::REMOVED, 'reason' => null, 'reason_text' => null])->save();
    }

    /**
     * ownChoir.gallery.list: the approved ones anyone may hear here, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function list(?string $country): array
    {
        return ChoirGallery::on() ? $this->shownHere($this->visibleItems(), $country) : [];
    }

    /**
     * The gallery's page: its choirs for this visitor, and whether it's indexed, from one read.
     *
     * @return array{items: list<array<string, mixed>>, indexed: bool}
     */
    public function forPage(?string $country): array
    {
        $visible = ChoirGallery::on() ? $this->visibleItems() : new Collection;

        return ['items' => $this->shownHere($visible, $country), 'indexed' => $this->anyPlayable($visible)];
    }

    /**
     * Whether the gallery has a choir anyone can play somewhere (approved, shown, its mix on the media disk), whatever the
     * visitor's country: while it has none its page is noindex and out of the sitemap, both by this one answer, so the
     * sitemap never lists a page that says noindex (decisions 2026-11-26).
     *
     * @param  Collection<int, ChoirGalleryItem>|null  $visible
     */
    public function anyPlayable(?Collection $visible = null): bool
    {
        $visible ??= ChoirGallery::on() ? $this->visibleItems() : new Collection;
        $ids = $visible->pluck('harmony_id')->all();

        return $ids !== [] && Harmony::query()->whereKey($ids)->whereNotNull('media_audio_key')->where('media_audio_key', '!=', '')->exists();
    }

    /**
     * ownChoir.gallery.show.
     *
     * @return array<string, mixed>
     */
    public function show(string $id, ?string $country): array
    {
        $item = ChoirGallery::on() ? ChoirGalleryItem::query()->with(self::EAGER)->whereSqid($id)->first() : null;

        if ($item === null || $this->withheld($item, $country)) {
            throw new Refusal(404, 'not_found');
        }

        return match (true) {
            ChoirGallery::status($item) === ChoirGalleryItem::REMOVED => throw new Refusal(410, 'removed'),
            ChoirGallery::status($item) === ChoirGalleryItem::HELD => throw new Refusal(404, 'held'),
            ChoirGallery::visible($item) => ChoirGallery::shown($item),
            default => throw new Refusal(404, 'not_found'),
        };
    }

    /**
     * admin.choirGallery.list: the queue by status, oldest first.
     *
     * @return list<array<string, mixed>>
     */
    public function queue(string $status): array
    {
        $items = ChoirGalleryItem::query()->with(self::EAGER)->where('status', $status)->oldest('added_at')->oldest('id')->limit(100)->get();
        $reports = Report::query()->where('reportable_type', (new ChoirGalleryItem)->getMorphClass())->whereIn('reportable_id', $items->modelKeys())
            ->where('status', Report::OPEN)->orderBy('id')->get(['reportable_id', 'reason', 'created_at'])->groupBy('reportable_id');

        return array_values($items->map(fn (ChoirGalleryItem $item): array => $this->forStaff($item, array_values($reports->get($item->id)?->all() ?? [])))->all());
    }

    /**
     * admin.choirGallery.decide (audited).
     *
     * @param  array{decision: string, reason?: string|null, reasonText?: string|null}  $data
     * @return array<string, mixed>
     */
    public function decide(User $admin, string $id, array $data, ?string $ip): array
    {
        $item = ChoirGalleryItem::query()->with(self::EAGER)->whereSqid($id)->first() ?? throw new Refusal(404, 'not_found');
        $reason = in_array($data['decision'], ['decline', 'takedown'], true) ? ($data['reason'] ?? null) : null;

        $item->forceFill([
            'status' => match ($data['decision']) {
                'approve', 'release' => ChoirGalleryItem::APPROVED,
                'decline' => ChoirGalleryItem::DECLINED,
                default => ChoirGalleryItem::REMOVED,
            },
            'reason' => $reason,
            'reason_text' => $reason === 'other' ? ($data['reasonText'] ?? null) : null,
            'decided_at' => now(),
            'decided_by' => $admin->id,
            'released_at' => $data['decision'] === 'release' ? now() : $item->released_at,
        ])->save();

        if (in_array($data['decision'], ['release', 'takedown'], true)) {
            Report::query()->whereMorphedTo('reportable', $item)->where('status', Report::OPEN)
                ->update(['status' => $data['decision'] === 'release' ? Report::DISMISSED : Report::UPHELD, 'updated_at' => now()]);
        }

        AuditLog::record('choir_gallery.'.$data['decision'], $item->user, $admin, after: ['item' => $item->id, 'reason' => $reason], ip: $ip);

        return $this->forStaff($item, []);
    }

    /** Their saved voice deleted: every item of theirs goes (docs/choir-gallery-plan.md, "Removing and hiding"). */
    public static function voiceGone(int $userId): void
    {
        ChoirGalleryItem::query()->where('user_id', $userId)->where('status', '!=', ChoirGalleryItem::REMOVED)->update(['status' => ChoirGalleryItem::REMOVED, 'updated_at' => now()]);
    }

    /**
     * The approved items anyone may see, newest first, whatever the country.
     *
     * @return Collection<int, ChoirGalleryItem>
     */
    private function visibleItems(): Collection
    {
        return ChoirGalleryItem::query()->with(self::EAGER)->where('status', ChoirGalleryItem::APPROVED)->latest('decided_at')->latest('id')->limit(200)->get()
            ->filter(static fn (ChoirGalleryItem $item): bool => ChoirGallery::visible($item))->values();
    }

    /**
     * Those not withheld in this country, as anyone sees them.
     *
     * @param  Collection<int, ChoirGalleryItem>  $visible
     * @return list<array<string, mixed>>
     */
    private function shownHere(Collection $visible, ?string $country): array
    {
        return array_values($visible->reject(fn (ChoirGalleryItem $item): bool => $this->withheld($item, $country))->take(100)
            ->map(static fn (ChoirGalleryItem $item): array => ChoirGallery::shown($item))->all());
    }

    private function song(User $user, string $song): OwnChoirRequest
    {
        if (! ChoirGallery::on($user)) {
            throw new Refusal(404, 'not_found');
        }

        return OwnChoirRequest::query()->with(['parts', 'members', 'templateVersion', 'galleryItem.harmony'])->whereSqid($song)->where('user_id', $user->id)
            ->whereNull('parent_request_id')->first() ?? throw new Refusal(404, 'not_found');
    }

    private function withheld(ChoirGalleryItem $item, ?string $country): bool
    {
        $slug = $item->request->templateVersion?->template?->slug;

        // The catalogue's withheld songs, kept an hour by country (RightsHere).
        return $slug !== null && $country !== null && in_array($slug, app(RightsHere::class)->withheldSlugs($country), true);
    }

    /**
     * @param  list<Report>  $reports
     * @return array<string, mixed>
     */
    private function forStaff(ChoirGalleryItem $item, array $reports): array
    {
        $key = $item->harmony?->audio_key;

        return [
            ...collect(ChoirGallery::shown($item))->only(['id', 'title', 'templateId', 'parts'])->all(),
            'mixUrl' => $key !== null ? Storage::disk('private')->temporaryUrl($key, now()->addMinutes(10)) : null,
            'addedAt' => $item->added_at->utc()->toIso8601ZuluString(),
            'status' => $item->status,
            'reports' => array_map(static fn (Report $r): array => ['reason' => (string) $r->reason, 'at' => $r->created_at?->utc()->toIso8601ZuluString() ?? ''], $reports),
            'changed' => ChoirGallery::partsSha($item->request) !== $item->parts_sha,
            'decision' => $item->decided_at === null ? null : ['status' => $item->status, 'reason' => $item->reason, 'reasonText' => $item->reason_text, 'at' => $item->decided_at->utc()->toIso8601ZuluString()],
        ];
    }
}
