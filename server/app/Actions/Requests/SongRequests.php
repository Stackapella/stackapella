<?php

declare(strict_types=1);

namespace App\Actions\Requests;

use App\Exceptions\Refusal;
use App\Models\AuditLog;
use App\Models\Report;
use App\Models\SongRequest;
use App\Models\SongRequestVote;
use App\Models\Template;
use App\Models\User;
use App\Support\Flags;
use App\Support\Gallery\Bell;
use App\Support\Ids;
use App\Support\Moderation\TextFilter;
use App\Support\Trust\TrustLevel;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Song requests (N7; docs/more-features-plan.md, 8; contract/api.md, "Song requests (N7)"): what
 * people want to sing next, voted for, behind the `song_requests` flag. Asking is a vote; the same
 * title again is a vote for the one there is. When Hillel releases one, everyone who asked or
 * voted hears of it in the bell, once.
 */
final class SongRequests
{
    public function __construct(private readonly Flags $flags, private readonly TrustLevel $trust) {}

    public function isOpen(): bool
    {
        return $this->flags->on('song_requests');
    }

    /**
     * Requests anyone sees: not held, hidden or merged.
     *
     * @return Builder<SongRequest>
     */
    public function shown(): Builder
    {
        return SongRequest::query()->whereNull('held_at')->whereNull('hidden_at')->whereNull('merged_into_id');
    }

    /**
     * requests.list.
     *
     * @return CursorPaginator<int, SongRequest>
     */
    public function list(?string $q, ?string $status, string $sort): CursorPaginator
    {
        if (! $this->isOpen()) {
            throw new Refusal(404, 'not_found');
        }

        $key = $q !== null ? SongRequest::matchKey($q) : '';

        return $this->shown()->with('template')
            ->when($key !== '', fn ($query) => $query->where('match_key', 'like', '%'.addcslashes($key, '%_\\').'%'))
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->when($sort === 'newest', fn ($query) => $query->orderByDesc('id'), fn ($query) => $query->orderByDesc('votes_count')->orderByDesc('id'))
            ->cursorPaginate(50);
    }

    /**
     * requests.create: a new request (and its asker's vote), or a vote for the same title already
     * asked for.
     *
     * @param  array{title: string, writer?: string|null, why?: string|null}  $data
     * @return array{request: SongRequest, created: bool}
     */
    public function create(User $user, array $data, ?string $ip): array
    {
        $this->ensureMayAsk($user);
        $title = trim($data['title']);
        $writer = trim((string) ($data['writer'] ?? ''));
        $why = trim((string) ($data['why'] ?? ''));

        foreach (['title' => $title, 'writer' => $writer, 'why' => $why] as $field => $text) {
            if (TextFilter::hasLink($text)) {
                throw ValidationException::withMessages([$field => __('validation.no_links')]);
            }
        }

        $key = SongRequest::matchKey($title);

        if ($key === '') {
            throw ValidationException::withMessages(['title' => __('validation.request_title')]);
        }

        // Only one anyone can see: a held one's words aren't shown back to a new asker.
        $existing = $this->shown()->where('match_key', $key)->first();

        if ($existing !== null) {
            $this->addVote($existing, $user);

            return ['request' => $existing->refresh(), 'created' => false];
        }

        $request = new SongRequest;
        $request->forceFill([
            'user_id' => $user->id,
            'title' => mb_substr($title, 0, 120),
            'match_key' => $key,
            'writer' => $writer !== '' ? mb_substr($writer, 0, 120) : null,
            'why' => $why !== '' ? mb_substr($why, 0, 300) : null,
            'held_at' => TextFilter::holds($title.' '.$writer.' '.$why) ? now() : null,
        ])->save();
        $this->addVote($request, $user);
        AuditLog::record('requests.created', $request, $user, ip: $ip);

        return ['request' => $request->refresh(), 'created' => true];
    }

    public function vote(User $user, string $id, bool $on): SongRequest
    {
        $request = $this->find($id);

        if ($on) {
            $this->ensureMayAsk($user);
            $this->addVote($request, $user);
        } elseif (SongRequestVote::query()->where('song_request_id', $request->id)->where('user_id', $user->id)->delete() > 0) {
            self::recount($request);
        }

        return $request->refresh();
    }

    private function ensureMayAsk(User $user): void
    {
        if (! $this->isOpen()) {
            throw new Refusal(409, 'requests_closed');
        }

        if ($this->trust->of($user) < TrustLevel::VERIFIED || $user->isSuspended()) {
            throw new Refusal(403, 'email_unverified');
        }
    }

    private function addVote(SongRequest $request, User $user): void
    {
        try {
            (new SongRequestVote)->forceFill(['song_request_id' => $request->id, 'user_id' => $user->id])->save();
        } catch (UniqueConstraintViolationException) {
            // Already voted: the same answer.
            return;
        }

        self::recount($request);
    }

    public static function recount(SongRequest $request): void
    {
        // In one statement, so two votes at once both count.
        SongRequest::query()->whereKey($request->id)->update(['votes_count' => DB::raw('(select count(*) from song_request_votes where song_request_votes.song_request_id = song_requests.id)')]);
    }

    /**
     * One anyone can see, by its id.
     */
    public function find(string $id): SongRequest
    {
        if (! $this->isOpen()) {
            throw new Refusal(404, 'not_found');
        }

        return $this->shown()->with('template')->whereKey(Ids::decode($id) ?? 0)->first() ?? throw new Refusal(404, 'not_found');
    }

    /**
     * @return array<string, mixed>
     */
    public static function view(SongRequest $request, ?User $viewer, ?bool $voted = null): array
    {
        return [
            'id' => $request->sqid(),
            'title' => $request->title,
            'writer' => $request->writer,
            'why' => $request->why,
            'votes' => $request->votes_count,
            'voted' => $voted ?? ($viewer !== null && SongRequestVote::query()->where('song_request_id', $request->id)->where('user_id', $viewer->id)->exists()),
            // The caller asked for it ("You asked for this").
            'mine' => $viewer !== null && $request->user_id === $viewer->id,
            'status' => $request->status,
            'template' => $request->status === 'ready' && $request->template !== null ? ['id' => $request->template->slug, 'title' => $request->template->title] : null,
            'createdAt' => $request->created_at?->utc()->toIso8601ZuluString(),
        ];
    }

    /**
     * admin.requests.update: the status (ready with one of our songs tells everyone who asked or
     * voted, once), a merge, hidden, or a held one approved.
     *
     * @param  array{status?: string|null, templateId?: string|null, mergeInto?: string|null, hidden?: bool|null, approve?: bool|null}  $changes
     */
    public function update(User $admin, string $id, array $changes, ?string $ip): SongRequest
    {
        $request = SongRequest::query()->whereKey(Ids::decode($id) ?? 0)->first() ?? throw new Refusal(404, 'not_found');
        $released = false;

        DB::transaction(function () use ($request, $changes, &$released): void {
            $locked = SongRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (($changes['mergeInto'] ?? null) !== null) {
                $into = SongRequest::query()->whereKey(Ids::decode((string) $changes['mergeInto']) ?? 0)->whereKeyNot($locked->id)->whereNull('merged_into_id')->first()
                    ?? throw ValidationException::withMessages(['mergeInto' => __('validation.request_merge')]);
                // Each vote moves once (someone who voted for both keeps one).
                $voters = SongRequestVote::query()->where('song_request_id', $locked->id)->pluck('user_id');
                SongRequestVote::query()->insertOrIgnore($voters->map(fn (mixed $user): array => ['song_request_id' => $into->id, 'user_id' => $user, 'created_at' => now()])->all());
                $locked->forceFill(['merged_into_id' => $into->id, 'hidden_at' => $locked->hidden_at ?? now()]);
                self::recount($into);
            }

            if (($changes['status'] ?? null) !== null) {
                $template = null;

                if ($changes['status'] === 'ready') {
                    $template = Template::query()->where('slug', (string) ($changes['templateId'] ?? ''))->whereNull('owner_id')->whereNull('deleted_at')->first()
                        ?? throw ValidationException::withMessages(['templateId' => __('validation.request_template')]);
                }

                $released = $changes['status'] === 'ready' && $locked->released_at === null;
                $locked->forceFill(['status' => $changes['status'], 'template_id' => $template !== null ? $template->id : $locked->template_id, 'released_at' => $released ? now() : $locked->released_at]);
            }

            if (($changes['hidden'] ?? null) !== null) {
                $locked->forceFill(['hidden_at' => $changes['hidden'] ? ($locked->hidden_at ?? now()) : null]);
            }

            if (($changes['approve'] ?? false) === true) {
                $locked->forceFill(['held_at' => null]);
                Report::query()->whereMorphedTo('reportable', $locked)->where('status', Report::OPEN)->update(['status' => Report::DISMISSED, 'updated_at' => now()]);
            }

            $locked->save();
        });

        AuditLog::record('requests.updated', $request, $admin, after: $changes, ip: $ip);
        $request->refresh();

        if ($released) {
            foreach (SongRequestVote::query()->where('song_request_id', $request->id)->pluck('user_id') as $userId) {
                Bell::tell((int) $userId, 'request_ready', null, request: $request);
            }
        }

        return $request->load('template');
    }
}
