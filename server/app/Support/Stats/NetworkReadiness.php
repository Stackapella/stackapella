<?php

declare(strict_types=1);

namespace App\Support\Stats;

use App\Actions\Choirs\Choirs;
use App\Models\ChoirVoice;
use App\Models\CoachProfile;
use App\Models\Harmony;
use App\Models\Score;
use App\Models\Setting;
use App\Models\Template;
use App\Models\User;
use App\Support\Flags;
use Illuminate\Database\Eloquent\Builder;

/**
 * The network features panel (docs/roadmap.md, "After launch"): for each of N1 to N7, its numbers
 * against its thresholds, the opt-ins so far, and its status: collecting (under half), start
 * building (half or more), ready to open (at the threshold), or open (its feature flag is on).
 * Counted from our own records.
 */
final class NetworkReadiness
{
    public const THRESHOLDS = 'network.thresholds';

    public const STATUSES = 'network.statuses';

    public const CHANGES = 'network.changes';

    public function __construct(private readonly Flags $flags) {}

    /**
     * @return list<array{key: string, title: string, status: string, numbers: list<array{key: string, value: int, threshold: int}>}>
     */
    public function rows(): array
    {
        $galleryOpen = $this->flags->value('gallery') === 'on' ? 1 : 0;
        $active = Counts::activeSingers();
        $opted = self::galleryHopefuls();
        $values = [
            'harmonies' => (clone $opted)->count(),
            'singers' => (int) (clone $opted)->distinct()->count('user_id'),
            'active' => $active,
            'galleryOpen' => $galleryOpen,
            // Opted in, or already published (N3).
            'templates' => self::libraryTemplates()->count(),
            'creators' => (int) self::libraryTemplates()->distinct()->count('owner_id'),
            'coaches' => CoachProfile::query()->where('status', CoachProfile::APPROVED)->count(),
            'rated' => CoachProfile::query()->where('status', CoachProfile::APPROVED)->where('rating_count', '>=', 3)->count(),
            // Harmonies offered with a part left open (N5), live. Nobody can open one before the
            // feature does, so until then: the gallery's hopefuls that could.
            'openParts' => $this->flags->on('open_parts')
                ? Harmony::query()->whereNotNull('open_parts')->whereJsonLength('open_parts', '>', 0)->whereNull('deleted_at')->whereNull('removed_at')->whereNull('hidden_at')->count()
                : self::couldOpenParts()->count(),
            // The fullest choir (N6 opens song by song): its voices, and across how many parts.
            // Before it opens: the song with the most singers who could join.
            ...($this->flags->on('virtual_choir') ? self::fullestChoir() : self::fullestChoirToBe()),
        ];

        $rows = [];

        foreach ($this->features() as $key => $feature) {
            $numbers = [];
            $least = 1.0;

            foreach ($feature['thresholds'] as $name => $threshold) {
                $value = $values[$name] ?? 0;
                $numbers[] = ['key' => $name, 'value' => $value, 'threshold' => (int) $threshold];
                $least = min($least, $threshold > 0 ? $value / $threshold : 1.0);
            }

            $open = $feature['flag'] !== null && $this->flags->value($feature['flag']) === 'on';
            $rows[] = [
                'key' => $key,
                'title' => $feature['title'],
                'status' => match (true) {
                    $open => 'open',
                    $least >= 1.0 => 'ready_to_open',
                    $least >= 0.5 => 'start_building',
                    default => 'collecting',
                },
                'numbers' => $numbers,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, int>  $thresholds
     */
    public static function setThresholds(string $feature, array $thresholds): void
    {
        $saved = (array) (Setting::query()->where('key', self::THRESHOLDS)->first()->value ?? []);
        $saved[$feature] = [...(array) ($saved[$feature] ?? []), ...$thresholds];
        Setting::query()->updateOrCreate(['key' => self::THRESHOLDS], ['value' => $saved]);
    }

    /**
     * The nightly recount: each status that changed since last night becomes a line for the digest.
     */
    public function recount(): int
    {
        $before = (array) (Setting::query()->where('key', self::STATUSES)->first()->value ?? []);
        $changes = (array) (Setting::query()->where('key', self::CHANGES)->first()->value ?? []);
        $now = [];

        foreach ($this->rows() as $row) {
            $now[$row['key']] = $row['status'];

            if (isset($before[$row['key']]) && $before[$row['key']] !== $row['status']) {
                $numbers = implode(', ', array_map(fn (array $number): string => __('ops.network.number', ['name' => $number['key'], 'value' => $number['value'], 'threshold' => $number['threshold']]), $row['numbers']));
                $changes[] = __('ops.network.changed', ['title' => $row['title'], 'status' => __('ops.network.status.'.$row['status']), 'numbers' => $numbers]);
            }
        }

        Setting::query()->updateOrCreate(['key' => self::STATUSES], ['value' => $now]);
        Setting::query()->updateOrCreate(['key' => self::CHANGES], ['value' => $changes]);

        return count($changes);
    }

    /**
     * The changes waiting for the digest.
     *
     * @return list<string>
     */
    public static function changes(): array
    {
        return array_values(array_filter((array) (Setting::query()->where('key', self::CHANGES)->first()->value ?? []), 'is_string'));
    }

    /**
     * The first $count changes said (once the digest has gone), so each is said once; any counted
     * since stay for the next.
     */
    public static function forgetChanges(int $count): void
    {
        $row = Setting::query()->where('key', self::CHANGES)->first();
        $row?->forceFill(['value' => array_slice(self::changes(), $count)])->save();
    }

    /**
     * @return array<string, array{title: string, flag: string|null, thresholds: array<string, int>}>
     */
    private function features(): array
    {
        $saved = (array) (Setting::query()->where('key', self::THRESHOLDS)->first()->value ?? []);
        $features = [];

        foreach ((array) config('network.features') as $key => $feature) {
            $thresholds = [];

            foreach ((array) $feature['thresholds'] as $name => $proposed) {
                // Hillel's line where he set one, else the proposed one.
                $thresholds[(string) $name] = (int) ($saved[$key][$name] ?? $proposed);
            }

            $features[(string) $key] = ['title' => (string) $feature['title'], 'flag' => is_string($feature['flag']) ? $feature['flag'] : null, 'thresholds' => $thresholds];
        }

        return $features;
    }

    /**
     * The choir with the most voices in (ready, not taken out or held), and its parts.
     *
     * @return array{voices: int, parts: int}
     */
    private static function fullestChoir(): array
    {
        $row = ChoirVoice::query()->where('status', ChoirVoice::READY)->whereNull('hidden_at')->whereNull('held_at')
            ->selectRaw('choir_id, count(*) as voices, count(distinct part_key) as parts')->groupBy('choir_id')->orderByDesc('voices')->first();

        return ['voices' => (int) ($row?->getAttribute('voices') ?? 0), 'parts' => (int) ($row?->getAttribute('parts') ?? 0)];
    }

    /**
     * Harmonies opted in to the gallery that could list once it opens: live, shared by link, not
     * hidden, by an adult who isn't suspended. A missing public profile or rights tick is the
     * owner's to fix, so it still counts; a private or minor's harmony never could list.
     *
     * @return Builder<Harmony>
     */
    private static function galleryHopefuls(): Builder
    {
        return Harmony::query()->whereNotNull('gallery_opt_in_at')->whereNull('deleted_at')->whereNull('removed_at')->whereNull('hidden_at')
            ->where('status', Harmony::READY)->where('visibility', Harmony::UNLISTED)
            ->whereHas('user', fn ($owner) => $owner->whereNotNull('adult_confirmed_at')->whereNull('suspended_at'));
    }

    /**
     * Hopefuls that harmonies.open would take: one of our songs or a live public template, with its
     * mix's takes kept.
     *
     * @return Builder<Harmony>
     */
    private static function couldOpenParts(): Builder
    {
        return self::galleryHopefuls()->whereNotNull('take_ids')->whereNull('part_id')
            ->whereHas('templateVersion.template', fn ($template) => $template->whereNull('deleted_at')->where(fn ($q) => $q->whereNull('owner_id')->orWhere(fn ($public) => $public->where('visibility', 'public')->whereNull('hidden_at'))));
    }

    /**
     * The song of ours with the most adults, verified and not suspended, holding a scored take of
     * it good enough to join its choir, and across how many parts.
     *
     * @return array{voices: int, parts: int}
     */
    private static function fullestChoirToBe(): array
    {
        $row = Score::query()->whereNotNull('take_uuid')->whereNotNull('part_key')
            ->whereIn('template_slug', Template::query()->whereNull('owner_id')->whereNull('deleted_at')->select('slug'))
            ->whereRaw("cast(json_unquote(json_extract(summary, '$.pitchPercent')) as decimal(6,2)) >= ?", [Choirs::MIN_SCORE])
            ->whereIn('user_id', User::query()->whereNotNull('adult_confirmed_at')->whereNotNull('email_verified_at')->whereNull('suspended_at')->select('id'))
            ->selectRaw('template_slug, count(distinct user_id) as voices, count(distinct part_key) as parts')->groupBy('template_slug')->orderByDesc('voices')->first();

        return ['voices' => (int) ($row?->getAttribute('voices') ?? 0), 'parts' => (int) ($row?->getAttribute('parts') ?? 0)];
    }

    /**
     * People's templates wanted in the library: opted in, or public already.
     *
     * @return Builder<Template>
     */
    private static function libraryTemplates(): Builder
    {
        return Template::query()->whereNotNull('owner_id')->whereNull('deleted_at')->where(fn ($q) => $q->where('library_opt_in', true)->orWhere('visibility', 'public'));
    }
}
