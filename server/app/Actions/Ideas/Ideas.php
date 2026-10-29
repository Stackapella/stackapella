<?php

declare(strict_types=1);

namespace App\Actions\Ideas;

use App\Actions\Templates\OwnTemplates;
use App\Exceptions\Refusal;
use App\Models\AuditLog;
use App\Models\Idea;
use App\Models\IdeaVoice;
use App\Models\Template;
use App\Models\User;
use App\Support\Flags;
use App\Support\Gallery\Bell;
use App\Support\Gallery\Gallery;
use App\Support\Gallery\GalleryViews;
use App\Support\Moderation\TextFilter;
use App\Support\Templates\PersonsTemplate;
use App\Support\Trust\TrustLevel;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Ideas (docs/your-tune-plan.md, step 3; contract/api.md, "Ideas"): a writer's tune shared with
 * every part open, on the Ideas shelf behind the `ideas` flag; anyone 18 or over may add their
 * voice to any part. The writer is 18 or over with a verified email, and their first two wait for
 * review unless they're level 3. Free.
 */
final class Ideas
{
    /** A writer's first this many ideas are reviewed first. */
    public const HELD_FIRST = 2;

    public const PER_PAGE = 24;

    public function __construct(private readonly Flags $flags, private readonly TrustLevel $trust, private readonly Gallery $gallery) {}

    public function isOpen(): bool
    {
        return $this->flags->on('ideas');
    }

    public function ensureOpen(): void
    {
        if (! $this->isOpen()) {
            throw new Refusal(409, 'ideas_closed');
        }
    }

    /**
     * Who may write or sing: 18 or over (their name goes in public), a verified email, not
     * suspended.
     */
    public function ensureMayTakePart(User $user): void
    {
        Gallery::ensureAdult($user);

        if ($this->trust->of($user) < TrustLevel::VERIFIED || $user->isSuspended()) {
            throw new Refusal(403, 'email_unverified');
        }
    }

    /**
     * ideas.create: the one there is for that template, or a new one.
     *
     * @param  array{templateId: string, note?: string|null, voicing?: int|null}  $data
     * @return array{idea: Idea, created: bool}
     */
    public function create(User $user, array $data, ?string $ip): array
    {
        $this->ensureOpen();
        $this->ensureMayTakePart($user);

        return DB::transaction(function () use ($user, $data, $ip): array {
            $template = app(OwnTemplates::class)->mine($user, $data['templateId'], lock: true);

            // Hidden by Hillel in the library: not one to share.
            if ($template->hidden_at !== null) {
                throw new Refusal(404, 'not_found');
            }

            $existing = Idea::query()->where('template_id', $template->id)->first();

            if ($existing !== null) {
                return ['idea' => $existing, 'created' => false];
            }

            $note = trim((string) ($data['note'] ?? ''));

            if (TextFilter::hasLink($note)) {
                throw ValidationException::withMessages(['note' => __('validation.no_links')]);
            }

            $voicings = self::voicings($template);
            $voicing = (int) ($data['voicing'] ?? ($voicings !== [] ? max(array_keys($voicings)) : 0));

            if (! isset($voicings[$voicing]) || $voicings[$voicing] === []) {
                throw ValidationException::withMessages(['voicing' => __('validation.idea_voicing')]);
            }

            $idea = new Idea;
            $idea->forceFill([
                'template_id' => $template->id,
                'user_id' => $user->id,
                'note' => $note !== '' ? $note : null,
                'voicing' => $voicing,
                'part_keys' => $voicings[$voicing],
                'rights_confirmed_at' => now(),
                // A tune whose last idea was hidden or held by reports waits for review again.
                'held_at' => $this->heldFirst($user) || $template->idea_review_at !== null ? now() : null,
                'voices_count' => 0,
            ])->save();
            AuditLog::record('ideas.shared', $idea, $user, after: ['template' => $template->slug, 'held' => $idea->held_at !== null], ip: $ip);

            return ['idea' => $idea, 'created' => true];
        });
    }

    public function heldFirst(User $user): bool
    {
        return $this->trust->of($user) < TrustLevel::TRUSTED
            && Idea::query()->where('user_id', $user->id)->whereNull('held_at')->whereNull('hidden_at')->count() < self::HELD_FIRST;
    }

    /**
     * Ideas anyone can find: not waiting for review or hidden, by a writer still 18 or over and
     * not suspended, whose template is still there and not hidden in the library.
     *
     * @return Builder<Idea>
     */
    public function listed(?User $viewer = null): Builder
    {
        $blocked = $viewer !== null ? $this->gallery->blockedIds($viewer) : [];

        return Idea::query()->whereNull('held_at')->whereNull('hidden_at')
            ->whereHas('user', fn ($writer) => $writer->whereNotNull('adult_confirmed_at')->whereNull('suspended_at'))
            ->whereHas('template', fn ($template) => $template->whereNull('deleted_at')->whereNull('hidden_at'))
            ->when($blocked !== [], fn ($query) => $query->whereNotIn('user_id', $blocked));
    }

    /**
     * Whether the template is a listed idea's tune (templates.show's `idea` grant).
     */
    public function isListedFor(Template $template): bool
    {
        return $this->isOpen() && $this->listed()->where('template_id', $template->id)->exists();
    }

    /**
     * ideas.list: the shelf, newest first, open (waiting for voices) or complete, or those still
     * missing a voice for one part.
     *
     * @return CursorPaginator<int, Idea>
     */
    public function shelf(?User $viewer, string $tab, ?string $needs): CursorPaginator
    {
        if (! $this->isOpen()) {
            throw new Refusal(404, 'not_found');
        }

        return $this->listed($viewer)->with(self::WITH)
            ->when($tab === 'complete', fn ($query) => $query->whereNotNull('completed_at'), fn ($query) => $query->whereNull('completed_at'))
            ->when($needs !== null, fn ($query) => $query->whereJsonContains('part_keys', $needs)->whereDoesntHave('voices', fn ($voices) => $voices->where('part_key', $needs)->where('status', IdeaVoice::READY)->whereNull('hidden_at')))
            ->orderByDesc('id')->cursorPaginate(self::PER_PAGE);
    }

    /**
     * @return CursorPaginator<int, Idea>
     */
    public function mine(User $user): CursorPaginator
    {
        return Idea::query()->with(self::WITH)->where('user_id', $user->id)->orderByDesc('id')->cursorPaginate(self::PER_PAGE);
    }

    /** What a card needs loaded. */
    public const WITH = ['template.currentVersion', 'user'];

    /**
     * One idea for whoever is looking: listed, or held (anyone with the link), or its writer's
     * own whatever it is. Anything else is as if it weren't there.
     */
    public function find(string $publicId, ?User $viewer): Idea
    {
        $idea = Idea::query()->with(self::WITH)->where('public_id', $publicId)->first() ?? throw new Refusal(404, 'not_found');
        $writer = $viewer !== null && $idea->user_id === $viewer->id;
        // Closed to all but its writer: the shelf shut, hidden (it or its tune), its tune deleted,
        // its writer suspended, or blocked with them.
        $closed = ! $this->isOpen() || $idea->hidden_at !== null || $idea->template->deleted_at !== null || $idea->template->hidden_at !== null
            || $idea->user->isSuspended() || ($viewer !== null && $this->gallery->blockedBetween($viewer, $idea->user_id));

        if (! $writer && $closed) {
            throw new Refusal(404, 'not_found');
        }

        return $idea;
    }

    public function delete(User $user, string $publicId, ?string $ip): void
    {
        $idea = Idea::query()->where('public_id', $publicId)->where('user_id', $user->id)->first();

        if ($idea !== null) {
            AuditLog::record('ideas.deleted', $idea, $user, ip: $ip);
            $this->remove($idea);
        }
    }

    /**
     * The idea and its voices go, their files too (the keys alone would leave them).
     */
    public function remove(Idea $idea): void
    {
        // Read under the lock processing takes, so a voice finishing meanwhile leaves no file.
        $keys = DB::transaction(function () use ($idea): array {
            Idea::query()->whereKey($idea->id)->lockForUpdate()->first(['id']);
            $keys = IdeaVoice::query()->where('idea_id', $idea->id)->lockForUpdate()->get(['file_key', 'upload_key'])
                ->flatMap(fn (IdeaVoice $v): array => array_values(array_filter([$v->file_key, $v->upload_key])))->all();
            Idea::query()->whereKey($idea->id)->delete();

            return $keys;
        });

        Storage::disk('private')->delete($keys);
    }

    /**
     * Every idea on these templates goes (a template deleted, or its writer's account).
     *
     * @param  list<int>  $templateIds
     */
    public function removeForTemplates(array $templateIds): void
    {
        foreach (Idea::query()->whereIn('template_id', $templateIds)->get() as $idea) {
            $this->remove($idea);
        }
    }

    /**
     * Its count of voices, and whether every part has one, read afresh under the idea's lock. The
     * writer is told the first time it's complete, never again (a part losing its voice and
     * getting another isn't news).
     */
    public function recount(Idea $idea): void
    {
        $tell = DB::transaction(function () use ($idea): ?Idea {
            $locked = Idea::query()->whereKey($idea->id)->lockForUpdate()->first();

            if ($locked === null) {
                return null;
            }

            $parts = IdeaVoice::query()->where('idea_id', $locked->id)->where('status', IdeaVoice::READY)->whereNull('hidden_at')->pluck('part_key');
            $complete = array_diff($locked->part_keys, $parts->unique()->all()) === [];
            $first = $complete && $locked->complete_told_at === null;

            $locked->forceFill([
                'voices_count' => $parts->count(),
                'completed_at' => $complete ? ($locked->completed_at ?? now()) : null,
                'complete_told_at' => $first ? now() : $locked->complete_told_at,
            ])->save();

            return $first ? $locked : null;
        });

        $idea->refresh();

        if ($tell !== null) {
            Bell::tell($tell->user_id, 'idea_complete', null, idea: $tell);
        }
    }

    /**
     * Each idea's ready voices counted by part, for a page of them at once.
     *
     * @param  list<int>  $ideaIds
     * @return array<int, Collection<string, int>>
     */
    public static function readyByPart(array $ideaIds): array
    {
        $counts = [];

        foreach (IdeaVoice::query()->whereIn('idea_id', $ideaIds)->where('status', IdeaVoice::READY)->whereNull('hidden_at')->selectRaw('idea_id, part_key, count(*) as n')->groupBy('idea_id', 'part_key')->get() as $row) {
            $counts[(int) $row->getAttribute('idea_id')][(string) $row->getAttribute('part_key')] = (int) $row->getAttribute('n');
        }

        return array_map(static fn (array $byPart): Collection => collect($byPart), $counts);
    }

    /**
     * An idea as the contract shows it ($readyByPart: its ready voices counted by part, when a list
     * has counted them all at once).
     *
     * @param  Collection<string, int>|null  $readyByPart
     * @return array<string, mixed>
     */
    public function view(Idea $idea, ?User $viewer, ?Collection $readyByPart = null): array
    {
        $data = $idea->template->currentVersion->data ?? [];
        $counts = $readyByPart ?? IdeaVoice::query()->where('idea_id', $idea->id)->where('status', IdeaVoice::READY)->whereNull('hidden_at')->selectRaw('part_key, count(*) as n')->groupBy('part_key')->pluck('n', 'part_key');
        $byKey = collect((array) ($data['parts'] ?? []))->filter(fn (mixed $part): bool => is_array($part) && is_string($part['key'] ?? null))->keyBy('key');

        return [
            'id' => $idea->public_id,
            'title' => $idea->template->title,
            'note' => $idea->note,
            'writer' => GalleryViews::person($idea->user),
            'template' => ['id' => $idea->template->slug, 'version' => (int) $idea->template->currentVersion?->version],
            'voicing' => $idea->voicing,
            'parts' => array_values(array_map(static fn (string $key): array => [
                'key' => $key,
                'name' => (string) ($byKey->get($key)['name'] ?? $key),
                'range' => ['low' => (int) ($byKey->get($key)['range']['low'] ?? 0), 'high' => (int) ($byKey->get($key)['range']['high'] ?? 0)],
                'voices' => (int) ($counts[$key] ?? 0),
            ], $idea->part_keys)),
            'voices' => $idea->voices_count,
            'complete' => $idea->completed_at !== null,
            'status' => $idea->status(),
            'mine' => $viewer !== null && $viewer->id === $idea->user_id,
            'createdAt' => $idea->created_at?->utc()->toIso8601ZuluString(),
        ];
    }

    /**
     * ideas.show: the idea, its voices (ready ones, and the caller's own on the way), and the
     * tune itself, as templates.show answers it.
     *
     * @return array<string, mixed>
     */
    public function show(Idea $idea, ?User $viewer): array
    {
        $blocked = $viewer !== null ? $this->gallery->blockedIds($viewer) : [];
        $voices = IdeaVoice::query()->with('user')->where('idea_id', $idea->id)->whereNull('hidden_at')
            ->when($blocked !== [], fn ($query) => $query->whereNotIn('user_id', $blocked))
            ->where(fn ($query) => $query->where('status', IdeaVoice::READY)->when($viewer !== null, fn ($mine) => $mine->orWhere('user_id', $viewer?->id)))
            ->orderByDesc('id')->get();
        $version = $idea->template->currentVersion;

        return [
            ...$this->view($idea, $viewer),
            'voiceList' => $voices->map(fn (IdeaVoice $voice): array => self::voice($voice, $viewer))->values()->all(),
            'song' => $version !== null ? PersonsTemplate::payload($version, PersonsTemplate::recordings($idea->template), $viewer?->id === $idea->user_id ? 'own' : 'idea') : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function voice(IdeaVoice $voice, ?User $viewer): array
    {
        return [
            'id' => $voice->uuid,
            'partKey' => $voice->part_key,
            'singer' => GalleryViews::person($voice->user),
            'status' => $voice->status,
            'url' => $voice->status === IdeaVoice::READY && $voice->file_key !== null ? Storage::disk('private')->temporaryUrl($voice->file_key, now()->addHours(2)) : null,
            'sampleRate' => $voice->sample_rate,
            'frames' => $voice->frames,
            'offsetFrames' => $voice->offset_frames,
            'framesPerPeak' => $voice->frames_per_peak,
            'peaks' => $voice->peaks,
            'mine' => $viewer !== null && $viewer->id === $voice->user_id,
            'createdAt' => $voice->created_at?->utc()->toIso8601ZuluString(),
        ];
    }

    /**
     * The template's voicings: how many singers, and which parts, melody first.
     *
     * @return array<int, list<string>>
     */
    public static function voicings(Template $template): array
    {
        $data = $template->currentVersion->data ?? [];
        $parts = array_values(array_filter((array) ($data['parts'] ?? []), static fn (mixed $part): bool => is_array($part) && is_string($part['key'] ?? null)));
        $melody = array_values(array_map(static fn (array $part): string => $part['key'], array_filter($parts, static fn (array $part): bool => ($part['melody'] ?? false) === true)));
        $first = static function (array $keys) use ($melody): array {
            usort($keys, static fn (string $a, string $b): int => (int) in_array($b, $melody, true) <=> (int) in_array($a, $melody, true));

            return $keys;
        };
        $voicings = [];

        foreach ((array) ($data['voicings'] ?? []) as $count => $keys) {
            if (is_numeric($count) && is_array($keys)) {
                $voicings[(int) $count] = $first(array_map('strval', array_values($keys)));
            }
        }

        ksort($voicings);

        return $voicings !== [] ? $voicings : [count($parts) => $first(array_map(static fn (array $part): string => $part['key'], $parts))];
    }
}
