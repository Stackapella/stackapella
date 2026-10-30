<?php

declare(strict_types=1);

namespace App\Actions\Boards;

use App\Enums\SecurityEventType;
use App\Exceptions\Refusal;
use App\Models\AuditLog;
use App\Models\BoardChallenge;
use App\Models\BoardConnection;
use App\Models\DailyScore;
use App\Models\Ghost;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\MonthlyScore;
use App\Models\PartScore;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Report;
use App\Models\Take;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Models\UserBlock;
use App\Support\Boards\Daily;
use App\Support\Gallery\Bell;
use App\Support\Gallery\Gallery;
use App\Support\Gallery\GalleryViews;
use App\Support\Ids;
use App\Support\Trust\IpHash;
use App\Support\Trust\SecurityLog;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Part leaderboards (docs/decisions.md, "part leaderboards"): each person's best score on a part
 * of a song's current version, under the current scoring; a global board of our songs for adults
 * who opt in, and a friends' board on any song. Free for everyone. A perfect or surprising score is
 * held off the global board for review (docs/leaderboards-plan.md); reporting names, bell notes and
 * earlier versions' boards come later, on these rows.
 */
final class Boards
{
    /** What a score needs to count: how it was sung, and that its timing can be trusted. */
    public const MODES = ['own', 'record'];

    /** How long a flagged score stays off the global board unless an admin clears it sooner. */
    public const HOLD_HOURS = 48;

    /** A jump this far over someone's own best on the part is checked. */
    public const JUMP = 25;

    /** As is a score this far above the global board's best, once it has this many entries. */
    public const ABOVE_BOARD = 15;

    public const ABOVE_BOARD_ENTRIES = 5;

    /** Reports from this many people hold an entry off the global board until an admin looks. */
    public const REPORTS_TO_HOLD = 2;

    /**
     * @param  array{templateId: string, templateVersion: int, partKey: string, scoringVersion: int, score: int, mode: string, keyOffset: int, speed: float, guideMuted: bool, timingChecked: bool, output?: string|null, takeId?: string|null, appVersion?: string|null, pitchDetectorVersion: int, alignmentMs?: int|null, notes?: list<array<string, mixed>>|null}  $s
     * @return array{counted: bool, why: string|null, best: int|null, improved: bool, before: int|null, daily: array{counted: bool, best: int|null, rank: int|null}|null}
     */
    public function submit(User $user, array $s): array
    {
        $template = $this->template($s['templateId']);
        $version = $template->currentVersion ?? throw new Refusal(404, 'not_found');

        if ($s['templateVersion'] !== $version->version) {
            throw new Refusal(409, 'old_version');
        }

        if ($s['scoringVersion'] !== (int) config('boards.scoring_version')) {
            throw new Refusal(409, 'scoring_outdated');
        }

        if (! collect((array) ($version->data['parts'] ?? []))->contains(fn ($p): bool => is_array($p) && ($p['key'] ?? null) === $s['partKey'])) {
            throw new Refusal(404, 'not_found');
        }

        // On your own or recording, in the song's own key, at full speed, the guide off, with the
        // device's timing checked and no Bluetooth delay: otherwise it's sung, but not ranked, and
        // the app says why.
        $why = match (true) {
            ! in_array($s['mode'], self::MODES, true) => 'mode',
            $s['keyOffset'] !== 0 => 'key',
            abs($s['speed'] - 1.0) > 0.001 => 'speed',
            ! $s['guideMuted'] => 'guide',
            ! $s['timingChecked'] => 'timing',
            ($s['output'] ?? null) === 'bluetooth' => 'bluetooth',
            default => null,
        };

        $take = null;

        if ($why === null && ($s['takeId'] ?? null) !== null) {
            // Only their own voice: a take they recorded, never a collaborator's on their project.
            $take = Take::query()->with(['part', 'project'])->where('uuid', $s['takeId'])
                ->where(fn ($query) => $query->where('recorded_by', $user->id)->orWhere(fn ($own) => $own->whereNull('recorded_by')->where('user_id', $user->id)))
                ->where('status', Take::READY)->first();

            if ($take === null || $take->part?->template_part_key !== $s['partKey'] || $take->project->template_slug !== $template->slug) {
                $why = 'take';
            }
        }

        if ($why !== null) {
            $had = PartScore::query()->where(['user_id' => $user->id, 'template_version_id' => $version->id, 'part_key' => $s['partKey'], 'scoring_version' => $s['scoringVersion']])->value('score');

            return ['counted' => false, 'why' => $why, 'best' => $had !== null ? (int) $had : null, 'improved' => false, 'before' => null, 'daily' => $this->toDaily($user, $s, $template, $version, null)];
        }

        $score = max(0, min(100, $s['score']));

        /** @var array{counted: bool, why: string|null, best: int|null, improved: bool, before?: int|null, row?: PartScore} $result */
        $result = DB::transaction(function () use ($user, $template, $version, $s, $score, $take): array {
            $best = PartScore::query()->where(['user_id' => $user->id, 'template_version_id' => $version->id, 'part_key' => $s['partKey'], 'scoring_version' => $s['scoringVersion']])->lockForUpdate()->first();

            if ($best !== null && $best->score >= $score) {
                return ['counted' => true, 'why' => null, 'best' => $best->score, 'improved' => false, 'before' => $best->score];
            }

            $before = $best?->score;
            $flagged = $this->surprising($user, $version->id, $s['partKey'], $s['scoringVersion'], $score, $before);
            $best ??= new PartScore;
            $best->forceFill([
                'user_id' => $user->id, 'template_id' => $template->id, 'template_version_id' => $version->id, 'part_key' => $s['partKey'], 'scoring_version' => $s['scoringVersion'],
                'score' => $score, 'mode' => $s['mode'], 'key_offset' => 0, 'speed' => '1.00', 'guide_muted' => true, 'timing_checked' => true, 'output' => $s['output'] ?? null,
                'take_id' => $take?->id, 'app_version' => $s['appVersion'] ?? null, 'recorded_at' => now(), 'hidden_at' => null, 'flagged_at' => $flagged ? now() : null, 'reviewed_at' => null,
                'pitch_detector_version' => $s['pitchDetectorVersion'], 'alignment_ms' => $s['alignmentMs'] ?? null, 'notes' => $s['notes'] ?? null,
            ])->save();

            return ['counted' => true, 'why' => null, 'best' => $score, 'improved' => true, 'before' => $before, 'row' => $best];
        });

        if (isset($result['row'])) {
            $this->tellBeatenFriends($user, $result['row'], $result['before'] ?? null);
        }

        // The month's board keeps its own best: a counted score that isn't an all-time best can be this month's.
        self::keepMonthly($user, $version, $s['partKey'], (int) $s['scoringVersion'], $score);

        // `before`: their best on this board before this take, for the game's "up from" (whatever
        // device set it); null on a first score or one that didn't count.
        return ['counted' => $result['counted'], 'why' => $result['why'], 'best' => $result['best'], 'improved' => $result['improved'], 'before' => $result['before'] ?? null, 'daily' => $this->toDaily($user, $s, $template, $version, $score)];
    }

    /**
     * This month's best on a part (UTC), raised by a better counted score.
     */
    private static function keepMonthly(User $user, TemplateVersion $version, string $part, int $scoring, int $score): void
    {
        DB::statement('insert into monthly_scores (month, user_id, template_version_id, part_key, scoring_version, score, recorded_at, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?) as new
            on duplicate key update recorded_at = if(new.score > monthly_scores.score, new.recorded_at, monthly_scores.recorded_at), score = greatest(monthly_scores.score, new.score), updated_at = new.updated_at',
            [MonthlyScore::thisMonth(), $user->id, $version->id, $part, $scoring, $score, now(), now(), now()]);
    }

    /**
     * A score sent with `daily`: on that day's board too, when the day is still open and this is
     * its pick, keeping their best of the day. Null when `daily` wasn't sent.
     *
     * @param  array<string, mixed>  $s
     * @return array{counted: bool, best: int|null, rank: int|null}|null
     */
    private function toDaily(User $user, array $s, Template $template, TemplateVersion $version, ?int $score): ?array
    {
        $date = $s['daily'] ?? null;

        if (! is_string($date)) {
            return null;
        }

        $pick = Daily::open($date) ? Daily::pick($date) : null;
        $counts = $score !== null && $pick !== null && $pick['template']->id === $template->id && $pick['version']->id === $version->id && $pick['part'] === $s['partKey'];

        if ($counts) {
            DB::transaction(function () use ($user, $date, $template, $version, $s, $score): void {
                $row = DailyScore::query()->where('date', $date)->where('user_id', $user->id)->lockForUpdate()->first() ?? new DailyScore;

                if (! $row->exists || $row->score < $score) {
                    $row->forceFill(['date' => $date, 'user_id' => $user->id, 'template_id' => $template->id, 'template_version_id' => $version->id, 'part_key' => $s['partKey'], 'scoring_version' => $s['scoringVersion'], 'score' => $score, 'recorded_at' => now()])->save();
                }
            });
        }

        $best = DailyScore::query()->where('date', $date)->where('user_id', $user->id)->value('score');
        $rank = $pick !== null && $best !== null ? self::rankOf($this->dailyRanked($user, $date, $pick, 'global'), $user) : null;

        return ['counted' => $counts, 'best' => $best !== null ? (int) $best : null, 'rank' => $rank];
    }

    /**
     * The daily challenge's board: the day's pick, its top 10 (none for someone signed out) and
     * their own best and place. Named as the part boards are, and held and hidden by them: a day's
     * score shows only while its singer's part score on that song is on the board.
     *
     * @return array<string, mixed>
     */
    public function daily(?User $viewer, string $date, string $scope): array
    {
        if (! Daily::viewable($date)) {
            throw ValidationException::withMessages(['date' => __('validation.daily_date')]);
        }

        $pick = Daily::pick($date) ?? throw new Refusal(404, 'not_found');
        $ranked = $viewer !== null ? $this->dailyRanked($viewer, $date, $pick, $scope) : collect();
        $ids = $ranked->isEmpty() ? collect() : PartScore::query()->where('template_version_id', $pick['version']->id)->where('part_key', $pick['part'])
            ->where('scoring_version', (int) config('boards.scoring_version'))->whereIn('user_id', $ranked->pluck('user_id'))->get()->keyBy('user_id');
        $own = $viewer !== null ? DailyScore::query()->where('date', $date)->where('user_id', $viewer->id)->where('template_version_id', $pick['version']->id)->value('score') : null;

        return [
            'date' => $date,
            'templateId' => $pick['template']->slug,
            'templateVersion' => $pick['version']->version,
            'partKey' => $pick['part'],
            'scope' => $scope,
            'endsAt' => Daily::endsAt($date),
            'days' => $viewer !== null ? $this->stampDays($viewer, $date) : [],
            'entries' => $ranked->take((int) config('boards.top'))->map(fn (DailyScore $row, int $i): array => [
                // The singer's part-board entry, so "Report this name" works the same here.
                'id' => $ids->get($row->user_id)?->sqid() ?? '',
                'rank' => $i + 1,
                ...self::shown($row->user, $scope),
                'score' => $row->score,
                'at' => $row->recorded_at->utc()->toIso8601ZuluString(),
                'you' => $row->user_id === $viewer?->id,
            ])->values()->all(),
            'you' => $viewer !== null && $own !== null ? ['score' => (int) $own, 'rank' => self::rankOf($ranked, $viewer), 'initials' => self::initials($viewer)] : null,
        ];
    }

    /**
     * The days of the week ending on $date on which they took the daily challenge, oldest first:
     * their own history, so a score held off the global board counts, but not one taken off the
     * board or whose take is binned.
     *
     * @return list<string>
     */
    private function stampDays(User $user, string $date): array
    {
        $from = CarbonImmutable::createFromFormat('!Y-m-d', $date, 'UTC')?->subDays(Daily::DAYS_BACK - 1)->toDateString() ?? $date;

        return array_values(DailyScore::query()->where('user_id', $user->id)->whereBetween('date', [$from, $date])
            ->whereExists(fn (QueryBuilder $q) => $q->from('part_scores')->selectRaw('1')
                ->whereColumn('part_scores.user_id', 'daily_scores.user_id')->whereColumn('part_scores.template_version_id', 'daily_scores.template_version_id')
                ->whereColumn('part_scores.part_key', 'daily_scores.part_key')->whereColumn('part_scores.scoring_version', 'daily_scores.scoring_version')
                ->whereNull('part_scores.hidden_at')
                ->whereNotExists(fn (QueryBuilder $t) => $t->from('takes')->selectRaw('1')->whereColumn('takes.id', 'part_scores.take_id')->whereNotNull('takes.binned_at')))
            ->orderBy('date')->get(['date'])->map(fn (DailyScore $row): string => $row->date->toDateString())->all());
    }

    /**
     * Their place on a day's board, or null when it doesn't show them.
     *
     * @param  Collection<int, DailyScore>  $ranked
     */
    private static function rankOf(Collection $ranked, User $user): ?int
    {
        $at = $ranked->search(fn (DailyScore $row): bool => $row->user_id === $user->id);

        return $at === false ? null : $at + 1;
    }

    /**
     * A day's scores in order, as the viewer may see them: only where the singer's part score on
     * that song is on the board (not hidden, not held, its take not binned), global or friends.
     *
     * @param  array{template: Template, version: TemplateVersion, part: string}  $pick
     * @return Collection<int, DailyScore>
     */
    private function dailyRanked(User $viewer, string $date, array $pick, string $scope): Collection
    {
        $visible = PartScore::query()->where('template_version_id', $pick['version']->id)->where('part_key', $pick['part'])
            ->where('scoring_version', (int) config('boards.scoring_version'))->whereNull('hidden_at')
            ->whereDoesntHave('take', fn ($q) => $q->whereNotNull('binned_at'))
            ->when($scope === 'global', fn ($q) => $q->where(fn ($q) => $q->where(self::notHeld(...))->orWhere('user_id', $viewer->id)))
            ->pluck('user_id');

        return DailyScore::query()->with('user')->where('date', $date)->where('template_version_id', $pick['version']->id)->where('part_key', $pick['part'])
            ->whereIn('user_id', $visible)->whereNotIn('user_id', self::blocked($viewer))
            ->when($scope === 'global',
                fn ($q) => $q->whereHas('user', fn ($u) => $u->whereNotNull('board_global_at')->whereNotNull('adult_confirmed_at')->whereNull('suspended_at')),
                fn ($q) => $q->whereIn('user_id', [$viewer->id, ...$this->friendIds($viewer)])->whereHas('user', fn ($u) => $u->whereNull('suspended_at')))
            ->orderByDesc('score')->orderBy('recorded_at')->orderBy('id')->get()->values();
    }

    /**
     * Their ghost on a part: the pitch line of their best take, while that best still stands on
     * the board (a score taken off, or its take binned, takes the ghost with it). Null when none is
     * kept; an unknown song or part is a 404.
     */
    public function ghost(User $user, string $templateId, string $part): ?Ghost
    {
        $template = $this->template($templateId);
        $version = $template->currentVersion ?? throw new Refusal(404, 'not_found');

        if (! self::hasPart($version, $part)) {
            throw new Refusal(404, 'not_found');
        }

        $scoring = (int) config('boards.scoring_version');
        $ghost = Ghost::query()->where(['user_id' => $user->id, 'template_version_id' => $version->id, 'part_key' => $part, 'scoring_version' => $scoring])->first();
        $best = PartScore::query()->where(['user_id' => $user->id, 'template_version_id' => $version->id, 'part_key' => $part, 'scoring_version' => $scoring])->whereNull('hidden_at')
            ->whereDoesntHave('take', fn ($q) => $q->whereNotNull('binned_at'))->value('score');

        if ($ghost === null || $best === null || (int) $best !== $ghost->score) {
            return null;
        }

        return $ghost->setRelation('templateVersion', $version);
    }

    private static function hasPart(TemplateVersion $version, string $part): bool
    {
        return collect((array) ($version->data['parts'] ?? []))->contains(fn ($p): bool => is_array($p) && ($p['key'] ?? null) === $part);
    }

    /**
     * Keeps the ghost of their best take, replacing the one before: only for the score that is
     * their best on that part's board now.
     *
     * @param  array{templateVersion: int, scoringVersion: int, score: int, rate: int, startSeconds: float, keyOffset: int, pitches: string}  $g
     */
    public function keepGhost(User $user, string $templateId, string $part, array $g): Ghost
    {
        $template = $this->template($templateId);
        $version = $template->currentVersion ?? throw new Refusal(404, 'not_found');

        if (! self::hasPart($version, $part)) {
            throw new Refusal(404, 'not_found');
        }

        $best = PartScore::query()->where(['user_id' => $user->id, 'template_version_id' => $version->id, 'part_key' => $part, 'scoring_version' => $g['scoringVersion']])->whereNull('hidden_at')
            ->whereDoesntHave('take', fn ($q) => $q->whereNotNull('binned_at'))->value('score');

        if ($g['templateVersion'] !== $version->version || $g['scoringVersion'] !== (int) config('boards.scoring_version') || $best === null || (int) $best !== $g['score']) {
            throw new Refusal(409, 'not_best');
        }

        $ghost = Ghost::query()->where(['user_id' => $user->id, 'template_version_id' => $version->id, 'part_key' => $part, 'scoring_version' => $g['scoringVersion']])->first() ?? new Ghost;
        $ghost->forceFill([
            'user_id' => $user->id, 'template_version_id' => $version->id, 'part_key' => $part, 'scoring_version' => $g['scoringVersion'], 'score' => $g['score'],
            'rate' => $g['rate'], 'start_seconds' => number_format($g['startSeconds'], 2, '.', ''), 'key_offset' => $g['keyOffset'], 'pitches' => $g['pitches'],
        ])->save();

        return $ghost->setRelation('templateVersion', $version);
    }

    /**
     * A friend's bell when your new best goes above theirs on the part, where they'd matched or
     * led you before: once a day per friend, part and person, and never by email.
     */
    private function tellBeatenFriends(User $user, PartScore $row, ?int $before): void
    {
        $friends = $this->friendIds($user);

        if ($friends === []) {
            return;
        }

        $beaten = PartScore::query()->whereIn('user_id', $friends)->where('template_version_id', $row->template_version_id)->where('part_key', $row->part_key)
            ->where('scoring_version', $row->scoring_version)->whereNull('hidden_at')->where('score', '<', $row->score)
            ->when($before !== null, fn ($q) => $q->where('score', '>=', $before))->pluck('user_id');

        foreach ($beaten as $friend) {
            if (Cache::add('bell:board_beaten:'.$friend.':'.$user->id.':'.$row->template_version_id.':'.$row->part_key, true, now()->addDay())) {
                Bell::tell((int) $friend, 'board_beaten', $user, score: $row);
            }
        }
    }

    /**
     * What a board_beaten bell item points at: the song, the part and the friend's score.
     *
     * @return array{templateId: string, title: string, partKey: string, partName: string, score: int}
     */
    public static function bellBoard(PartScore $row): array
    {
        $part = collect((array) ($row->templateVersion->data['parts'] ?? []))->firstWhere('key', $row->part_key);

        return [
            'templateId' => $row->template->slug,
            'title' => $row->template->title,
            'partKey' => $row->part_key,
            'partName' => is_array($part) && is_string($part['name'] ?? null) ? $part['name'] : ucfirst($row->part_key),
            'score' => $row->score,
        ];
    }

    /**
     * How a person shows to friends (a board, the bell): the public name, or under 18 their first
     * name only. Never an email.
     */
    public static function friendName(User $user): string
    {
        $name = GalleryViews::person($user)['name'];

        return Gallery::adult($user) ? $name : Str::before(trim($name), ' ');
    }

    /**
     * A part's board: `global` (our songs, adults who opted in) or `friends` (you and the people
     * you're connected to, on any song). The current arrangement's, or an earlier one's (read only:
     * new scores only go to the current version), while it has entries.
     *
     * @return array<string, mixed>
     */
    public function show(User $viewer, string $templateId, string $part, string $scope, ?int $versionNumber = null, string $period = 'all'): array
    {
        if ($period === 'month' && $versionNumber !== null) {
            throw ValidationException::withMessages(['period' => __('validation.board_month_version')]);
        }

        $template = $this->template($templateId);
        $current = $template->currentVersion ?? throw new Refusal(404, 'not_found');
        $scored = PartScore::query()->where('template_id', $template->id)->where('part_key', $part)->where('scoring_version', (int) config('boards.scoring_version'))->whereNull('hidden_at')
            ->where('template_version_id', '!=', $current->id)->distinct()->pluck('template_version_id')->all();
        $older = TemplateVersion::query()->whereIn('id', $scored)->where('version', '<', $current->version)->orderByDesc('version')->get(['id', 'version']);
        $version = $versionNumber === null || $versionNumber === $current->version ? $current : ($older->firstWhere('version', $versionNumber) ?? throw new Refusal(404, 'not_found'));
        $blocked = self::blocked($viewer);
        $rows = PartScore::query()->with('user')->where('template_version_id', $version->id)->where('part_key', $part)
            ->where('scoring_version', (int) config('boards.scoring_version'))->whereNull('hidden_at')->whereNotIn('user_id', $blocked)
            // A score whose take is in the bin is off the boards (deleting it for good deletes the score).
            ->whereDoesntHave('take', fn ($q) => $q->whereNotNull('binned_at'));

        if ($scope === 'global') {
            if (! $template->isOfficial()) {
                throw new Refusal(404, 'not_found');
            }

            $rows->whereHas('user', fn ($q) => $q->whereNotNull('board_global_at')->whereNotNull('adult_confirmed_at')->whereNull('suspended_at'))
                // A score held for review shows only to its singer until it's cleared or the hold ends.
                ->where(fn ($q) => $q->where(self::notHeld(...))->orWhere('user_id', $viewer->id));
        } else {
            $rows->whereIn('user_id', [$viewer->id, ...$this->friendIds($viewer)])->whereHas('user', fn ($q) => $q->whereNull('suspended_at'));
        }

        if ($period === 'month') {
            return [...$this->month($viewer, $template, $version, $part, $scope, $rows), 'olderVersions' => $older->pluck('version')->map(fn ($v): int => (int) $v)->values()->all()];
        }

        $ranked = $rows->orderByDesc('score')->orderBy('recorded_at')->orderBy('id')->get()->values();
        $mine = $ranked->search(fn (PartScore $row): bool => $row->user_id === $viewer->id);
        $own = PartScore::query()->where('user_id', $viewer->id)->where('template_version_id', $version->id)->where('part_key', $part)->where('scoring_version', (int) config('boards.scoring_version'))->value('score');

        return [
            'period' => 'all',
            'endsAt' => null,
            'templateId' => $template->slug,
            'templateVersion' => $version->version,
            'partKey' => $part,
            'scope' => $scope,
            'scoringVersion' => (int) config('boards.scoring_version'),
            'entries' => $ranked->take((int) config('boards.top'))->map(fn (PartScore $row, int $i): array => [
                'id' => $row->sqid(),
                'rank' => $i + 1,
                ...self::shown($row->user, $scope),
                'score' => $row->score,
                'at' => $row->recorded_at->utc()->toIso8601ZuluString(),
                'you' => $row->user_id === $viewer->id,
            ])->values()->all(),
            'you' => $own !== null ? ['score' => (int) $own, 'rank' => $mine !== false ? $mine + 1 : null, 'initials' => self::initials($viewer)] : null,
            'onGlobal' => ($viewer->getAttributes()['board_global_at'] ?? null) !== null,
            'current' => $version->id === $current->id,
            'olderVersions' => $older->pluck('version')->map(fn ($v): int => (int) $v)->values()->all(),
        ];
    }

    /**
     * On the global board, or off it: 18 and over only.
     */
    public function setGlobal(User $user, bool $on): void
    {
        if ($on && ! Gallery::adult($user)) {
            throw new Refusal(403, 'adult_required');
        }

        $user->forceFill(['board_global_at' => $on ? ($user->getAttributes()['board_global_at'] ?? now()) : null])->save();
    }

    /**
     * A challenge link for a part of a song: the token is shown once.
     *
     * @return array{token: string}
     */
    public function challenge(User $user, string $templateId, string $part): array
    {
        $template = $this->template($templateId);
        $token = Str::random(40);
        $challenge = new BoardChallenge;
        $challenge->forceFill(['user_id' => $user->id, 'template_id' => $template->id, 'part_key' => $part, 'token_hash' => hash('sha256', $token)])->save();

        return ['token' => $token];
    }

    /**
     * What a challenge link shows before it's accepted: the song and part, the challenger's best on
     * it, and their name, which isn't shown to someone under 18 or signed out.
     *
     * @return array{templateId: string, partKey: string, score: int|null, by: string|null}
     */
    public function open(?User $viewer, string $token): array
    {
        $challenge = BoardChallenge::query()->with(['user', 'template.currentVersion'])->where('token_hash', hash('sha256', $token))->first() ?? throw new Refusal(404, 'not_found');
        $score = PartScore::query()->where('user_id', $challenge->user_id)->where('template_version_id', (int) $challenge->template->current_version_id)->where('part_key', $challenge->part_key)
            ->where('scoring_version', (int) config('boards.scoring_version'))->whereNull('hidden_at')->value('score');

        return [
            'templateId' => $challenge->template->slug,
            'partKey' => $challenge->part_key,
            'score' => $score !== null ? (int) $score : null,
            // Their initials when they show initials; their name only to an adult.
            'by' => $viewer === null ? null : (self::showsInitials($challenge->user) ? self::initials($challenge->user) : (Gallery::adult($viewer) ? GalleryViews::person($challenge->user)['name'] : null)),
        ];
    }

    /**
     * Accepting a challenge: the song and part to sing, and, between two adults, a connection both
     * ways. Never with someone under 18 (they connect only through a group or a song they were
     * invited to), and never with yourself.
     *
     * @return array{templateId: string, partKey: string, connected: bool}
     */
    public function accept(User $user, string $token): array
    {
        $challenge = BoardChallenge::query()->with(['user', 'template'])->where('token_hash', hash('sha256', $token))->first() ?? throw new Refusal(404, 'not_found');
        $connect = $challenge->user_id !== $user->id && Gallery::adult($user) && Gallery::adult($challenge->user);

        if ($connect) {
            foreach ([[$user->id, $challenge->user_id], [$challenge->user_id, $user->id]] as [$from, $to]) {
                if (! BoardConnection::query()->where(['user_id' => $from, 'friend_id' => $to, 'source' => BoardConnection::CHALLENGE])->exists()) {
                    BoardConnection::query()->forceCreate(['user_id' => $from, 'friend_id' => $to, 'source' => BoardConnection::CHALLENGE, 'created_at' => now()]);
                }
            }
        }

        return ['templateId' => $challenge->template->slug, 'partKey' => $challenge->part_key, 'connected' => $connect];
    }

    /**
     * Admin: the scores waiting for a look, newest first, each with a short-lived link to the take
     * behind it (terms 4c: "we may listen to the take behind your score").
     *
     * @return list<array<string, mixed>>
     */
    public function flags(): array
    {
        return array_values(self::waiting()->with(['user', 'template', 'take'])->select('part_scores.*')->selectSub(self::openReports(), 'reports_count')->latest('flagged_at')->latest('id')->limit(100)->get()
            ->map(fn (PartScore $row): array => [
                'id' => $row->sqid(),
                'person' => ['id' => $row->user->sqid(), 'name' => $row->user->name],
                'templateId' => $row->template->slug,
                'title' => $row->template->title,
                'partKey' => $row->part_key,
                'score' => $row->score,
                'reason' => match (true) {
                    $row->getAttribute('reports_count') > 0 => 'reported',
                    $row->score === 100 => 'perfect',
                    default => 'outlier',
                },
                'reports' => (int) $row->getAttribute('reports_count'),
                'recordedAt' => $row->recorded_at->utc()->toIso8601ZuluString(),
                'holdEndsAt' => $row->flagged_at?->copy()->addHours(self::HOLD_HOURS)->utc()->toIso8601ZuluString(),
                'takeUrl' => $row->take?->file_key !== null && $row->take->binned_at === null ? Storage::disk('private')->temporaryUrl($row->take->file_key, now()->addMinutes(10)) : null,
            ])->all());
    }

    /**
     * Flagged, not yet looked at, and still on the boards: the admin's list and the digest's count.
     *
     * @return Builder<PartScore>
     */
    public static function waiting(): Builder
    {
        return PartScore::query()->whereNotNull('flagged_at')->whereNull('reviewed_at')->whereNull('hidden_at');
    }

    /**
     * Admin: looked at and fine, so it shows at once (audited).
     */
    public function review(User $admin, PartScore $score, ?string $ip): void
    {
        $score->forceFill(['reviewed_at' => now()])->save();
        self::settleReports($score, Report::DISMISSED);
        AuditLog::record('boards.score_reviewed', $score->user, $admin, after: ['score' => $score->id, 'part' => $score->part_key], ip: $ip);
    }

    /**
     * Admin: one score off the boards, or all of a person's (audited; in sudo).
     */
    public function hide(User $admin, PartScore $score, string $note, ?string $ip): void
    {
        $score->forceFill(['hidden_at' => now()])->save();
        self::settleReports($score, Report::UPHELD);
        AuditLog::record('boards.score_hidden', $score->user, $admin, after: ['score' => $score->id, 'part' => $score->part_key], reason: $note, ip: $ip);
    }

    public function reset(User $admin, User $person, string $note, ?string $ip): int
    {
        $count = PartScore::query()->where('user_id', $person->id)->delete();
        // Their day's and month's boards and ghosts trace those scores, so they go too.
        DailyScore::query()->where('user_id', $person->id)->delete();
        MonthlyScore::query()->where('user_id', $person->id)->delete();
        Ghost::query()->where('user_id', $person->id)->delete();
        AuditLog::record('boards.scores_reset', $person, $admin, after: ['scores' => $count], reason: $note, ip: $ip);

        return (int) $count;
    }

    /**
     * Who someone's friends' board shows: people connected by a challenge, who sing on a song with
     * them (owner and invited singers), or share a group with them.
     *
     * @return list<int>
     */
    public function friendIds(User $user): array
    {
        $projects = Project::query()->where('user_id', $user->id)->pluck('id')->merge(ProjectMember::query()->where('user_id', $user->id)->pluck('project_id'))->unique()->all();
        $groups = Group::query()->where('director_id', $user->id)->pluck('id')->merge(GroupMember::query()->where('user_id', $user->id)->pluck('group_id'))->unique()->all();

        $ids = collect()
            ->merge(BoardConnection::query()->where('user_id', $user->id)->pluck('friend_id'))
            ->merge(Project::query()->whereIn('id', $projects)->pluck('user_id'))
            ->merge(ProjectMember::query()->whereIn('project_id', $projects)->pluck('user_id'))
            ->merge(Group::query()->whereIn('id', $groups)->pluck('director_id'))
            ->merge(GroupMember::query()->whereIn('group_id', $groups)->pluck('user_id'));

        return array_values(array_diff($ids->map(fn ($id): int => (int) $id)->unique()->all(), [$user->id], self::blocked($user)));
    }

    /**
     * This month's board: each person's best this month among those the all-time board would show
     * (the same scope, blocks, holds and bins), ranked; an entry's id is their part-board entry's,
     * so "Report this name" works the same.
     *
     * @param  Builder<PartScore>  $visible
     * @return array<string, mixed>
     */
    private function month(User $viewer, Template $template, TemplateVersion $version, string $part, string $scope, Builder $visible): array
    {
        $scoring = (int) config('boards.scoring_version');
        $parts = (clone $visible)->get(['id', 'user_id'])->keyBy('user_id');
        $ranked = MonthlyScore::query()->with('user')->where('month', MonthlyScore::thisMonth())->where('template_version_id', $version->id)->where('part_key', $part)
            ->where('scoring_version', $scoring)->whereIn('user_id', $parts->keys())->orderByDesc('score')->orderBy('recorded_at')->orderBy('id')->get()->values();
        $own = MonthlyScore::query()->where('month', MonthlyScore::thisMonth())->where('user_id', $viewer->id)->where('template_version_id', $version->id)->where('part_key', $part)->where('scoring_version', $scoring)->value('score');
        $at = $ranked->search(fn (MonthlyScore $row): bool => $row->user_id === $viewer->id);

        return [
            'period' => 'month',
            'endsAt' => now()->utc()->startOfMonth()->addMonth()->toIso8601ZuluString(),
            'templateId' => $template->slug,
            'templateVersion' => $version->version,
            'partKey' => $part,
            'scope' => $scope,
            'scoringVersion' => $scoring,
            'entries' => $ranked->take((int) config('boards.top'))->map(fn (MonthlyScore $row, int $i): array => [
                'id' => $parts->get($row->user_id)?->sqid() ?? '',
                'rank' => $i + 1,
                ...self::shown($row->user, $scope),
                'score' => $row->score,
                'at' => $row->recorded_at->utc()->toIso8601ZuluString(),
                'you' => $row->user_id === $viewer->id,
            ])->values()->all(),
            'you' => $own !== null ? ['score' => (int) $own, 'rank' => $at !== false ? $at + 1 : null, 'initials' => self::initials($viewer)] : null,
            'onGlobal' => ($viewer->getAttributes()['board_global_at'] ?? null) !== null,
            'current' => true,
        ];
    }

    /**
     * How a board shows someone: their initials when they show initials (game mode's arcade
     * letters, and always someone under 18), else the name rules of public pages (and on a friends'
     * board, someone under 18 by their first name only). Never an email.
     *
     * @return array{name: string|null, initials: string|null}
     */
    public static function shown(User $user, string $scope): array
    {
        if (self::showsInitials($user)) {
            return ['name' => null, 'initials' => self::initials($user)];
        }

        return ['name' => $scope === 'friends' ? self::friendName($user) : GalleryViews::person($user)['name'], 'initials' => null];
    }

    /**
     * Their initials as a board shows them: null when they show their name.
     */
    public static function initials(User $user): ?string
    {
        return self::showsInitials($user) ? ($user->getAttributes()['initials'] ?? null) : null;
    }

    public static function showsInitials(User $user): bool
    {
        return ! Gallery::adult($user) || ($user->getAttributes()['boards_name'] ?? 'name') === 'initials';
    }

    /**
     * Everyone they blocked or who blocked them, read fresh (Gallery's list is kept for a request).
     *
     * @return list<int>
     */
    private static function blocked(User $user): array
    {
        return array_values(array_map('intval', [
            ...UserBlock::query()->where('blocker_id', $user->id)->pluck('blocked_id')->all(),
            ...UserBlock::query()->where('blocked_id', $user->id)->pluck('blocker_id')->all(),
        ]));
    }

    /**
     * "Report this name": a signed-in viewer reports someone else's entry. Always quiet about the
     * outcome (your own entry and repeats are ignored); enough people's reports hold it off the
     * global board and put it in front of an admin.
     *
     * @param  array{reason: string, note?: string|null, website?: mixed}  $data
     */
    public function report(?User $user, string $id, array $data, string $ip): void
    {
        $score = PartScore::query()->whereKey(Ids::decode($id) ?? 0)->whereNull('hidden_at')->first();

        if ($user === null || $score === null) {
            throw new Refusal(404, 'not_found');
        }

        if ((($data['website'] ?? null) !== null && $data['website'] !== '') || $user->id === $score->user_id) {
            return;
        }

        $report = new Report(['reason' => $data['reason'], 'note' => trim((string) ($data['note'] ?? ''))]);
        $report->forceFill([
            'reportable_type' => $score->getMorphClass(),
            'reportable_id' => $score->id,
            'reporter_id' => $user->id,
            'reporter_key' => hash_hmac('sha256', 'user|'.$user->id, (string) config('app.key')),
            'ip_hash' => IpHash::of($ip),
            'status' => Report::OPEN,
        ]);

        DB::transaction(function () use ($report, $score): void {
            $added = Report::query()->insertOrIgnore([array_merge($report->getAttributes(), ['created_at' => now(), 'updated_at' => now()])]) > 0;

            if ($added && PartScore::query()->whereKey($score->id)->where(self::openReports(), '>=', self::REPORTS_TO_HOLD)->exists()) {
                PartScore::query()->whereKey($score->id)->update(['flagged_at' => now(), 'reviewed_at' => null]);
            }
        });

        app(SecurityLog::class)->record(SecurityEventType::Report, $user, $ip, ['board_entry' => $score->sqid(), 'reason' => $data['reason']]);
    }

    /**
     * Shown on the global board: never flagged, cleared, or past its hold, and not held by
     * people's reports (which wait for an admin, however long).
     *
     * @param  Builder<PartScore>  $query
     */
    private static function notHeld(Builder $query): void
    {
        $query->where(fn ($q) => $q->whereNull('flagged_at')->orWhereNotNull('reviewed_at')->orWhere('flagged_at', '<=', now()->subHours(self::HOLD_HOURS)))
            ->where(self::openReports(), '<', self::REPORTS_TO_HOLD);
    }

    /**
     * How many people's reports on an entry are still open, as a subquery.
     *
     * @return Closure(QueryBuilder): QueryBuilder
     */
    private static function openReports(): Closure
    {
        return static fn (QueryBuilder $query): QueryBuilder => $query->from('reports')->selectRaw('count(*)')->where('reportable_type', 'part_score')->whereColumn('reportable_id', 'part_scores.id')->where('status', Report::OPEN);
    }

    private static function settleReports(PartScore $score, string $status): void
    {
        Report::query()->where('reportable_type', $score->getMorphClass())->where('reportable_id', $score->id)->where('status', Report::OPEN)->update(['status' => $status, 'updated_at' => now()]);
    }

    /**
     * A new best worth a look before it tops the global board: perfect, a big jump over their own
     * best, or well above everyone else's.
     */
    private function surprising(User $user, int $versionId, string $part, int $scoring, int $score, ?int $before): bool
    {
        if ($score === 100 || ($before !== null && $score - $before >= self::JUMP)) {
            return true;
        }

        $others = PartScore::query()->where('template_version_id', $versionId)->where('part_key', $part)->where('scoring_version', $scoring)
            ->whereNull('hidden_at')->where('user_id', '!=', $user->id)->where(self::notHeld(...))
            ->selectRaw('count(*) as entries, max(score) as best')->first();
        $entries = (int) ($others?->getAttribute('entries') ?? 0);

        return $entries >= self::ABOVE_BOARD_ENTRIES && $score - (int) $others?->getAttribute('best') >= self::ABOVE_BOARD;
    }

    /**
     * A song with boards: an official one, or one published to the library; never someone's private
     * template (404, as if it weren't there).
     */
    private function template(string $id): Template
    {
        return Template::query()->with('currentVersion')->where('slug', $id)->whereNull('deleted_at')
            ->where(fn ($q) => $q->whereNull('owner_id')->orWhere('visibility', 'public'))->first() ?? throw new Refusal(404, 'not_found');
    }
}
