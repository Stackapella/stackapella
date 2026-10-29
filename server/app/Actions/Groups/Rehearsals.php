<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Exceptions\Refusal;
use App\Models\EsConnection;
use App\Models\Group;
use App\Models\GroupAssignment;
use App\Models\GroupEventSong;
use App\Models\GroupSong;
use App\Models\User;
use App\Services\EventSchedule\EventSchedule;
use App\Services\EventSchedule\EventScheduleError;
use App\Support\Groups\GroupView;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A choir across both apps (docs/event-schedule-integration-plan.md G3): the director links the
 * group to one of their Event Schedule schedules and picks which of the group's songs each
 * rehearsal or concert is for; everyone sees the next ones with their own part and progress. The
 * events come through the director's connection, cached 5 minutes. The schedule's members are
 * shown to the director only, and never emailed or matched to accounts.
 */
final class Rehearsals
{
    private const DAYS = 60;

    private const SHOWN = 10;

    public function __construct(private readonly ManageGroups $groups, private readonly GroupView $view, private readonly EventSchedule $es) {}

    /**
     * @return array<string, mixed>
     */
    public function link(User $director, string $sqid, string $schedule): array
    {
        $group = $this->groups->directed($director, $sqid, needsPlus: true);
        $connection = EsConnection::query()->where('user_id', $director->id)->where('status', '!=', EsConnection::DISCONNECTED)->first() ?? throw new Refusal(409, 'event_schedule_required');

        if (! $connection->usable()) {
            throw new Refusal(409, 'reconnect_required');
        }

        $found = collect($this->es->schedules($connection))->firstWhere('subdomain', $schedule) ?? throw ValidationException::withMessages(['schedule' => __('validation.in', ['attribute' => 'schedule'])]);

        DB::transaction(function () use ($group, $connection, $found): void {
            if ($group->es_schedule !== $found['subdomain']) {
                GroupEventSong::query()->where('group_id', $group->id)->delete();
            }

            $group->forceFill(['es_connection_id' => $connection->id, 'es_schedule' => $found['subdomain'], 'es_schedule_name' => $found['name']])->save();
        });

        return $this->list($director, $group->sqid());
    }

    public function unlink(User $director, string $sqid): void
    {
        $group = $this->groups->directed($director, $sqid);

        DB::transaction(function () use ($group): void {
            GroupEventSong::query()->where('group_id', $group->id)->delete();
            $group->forceFill(['es_connection_id' => null, 'es_schedule' => null, 'es_schedule_name' => null])->save();
        });
    }

    /**
     * The next rehearsals and concerts, each with its songs and the viewer's part and progress.
     *
     * @return array<string, mixed>
     */
    public function list(User $viewer, string $sqid): array
    {
        $group = $this->groups->find($viewer, $sqid);

        if ($group->es_schedule === null) {
            return ['schedule' => null, 'available' => true, 'events' => []];
        }

        $connection = $group->es_connection_id !== null ? EsConnection::query()->find($group->es_connection_id) : null;
        $schedule = ['subdomain' => $group->es_schedule, 'name' => (string) $group->es_schedule_name, 'url' => $connection !== null ? self::scheduleUrl($connection, $group->es_schedule) : null];
        $events = $connection !== null && $connection->usable() ? $this->events($connection, $group->es_schedule) : null;

        if ($events === null) {
            return ['schedule' => $schedule, 'available' => false, 'events' => []];
        }

        $events = array_slice(array_values(array_filter($events, static fn (array $e): bool => ($e['endsAt'] ?? $e['startsAt']) >= now()->utc()->toIso8601ZuluString())), 0, self::SHOWN);
        $picked = GroupEventSong::query()->with('song.template')->where('group_id', $group->id)->whereIn('es_event_id', array_column($events, 'id'))->orderBy('id')->get();
        $songs = $picked->pluck('song')->unique('id')->values();
        $assignments = GroupAssignment::query()->whereIn('group_song_id', $songs->pluck('id'))->where('user_id', $viewer->id)->get();
        $progress = $this->view->progress($assignments, $songs);

        return [
            'schedule' => $schedule,
            'available' => true,
            'events' => array_map(static fn (array $event): array => [
                ...$event,
                'songs' => $picked->where('es_event_id', $event['id'])->map(function (GroupEventSong $p) use ($assignments, $progress): array {
                    $mine = $assignments->firstWhere('group_song_id', $p->group_song_id);

                    return [
                        'templateId' => $p->song->template->slug,
                        'title' => $p->song->template->title,
                        'partKey' => $mine?->part_key,
                        'status' => $mine !== null ? ($progress[$mine->id]['status'] ?? 'none') : 'none',
                    ];
                })->values()->all(),
            ], $events),
        ];
    }

    /**
     * @param  list<string>  $templateIds
     * @return array<string, mixed>
     */
    public function pickSongs(User $director, string $sqid, string $eventId, array $templateIds): array
    {
        $group = $this->groups->directed($director, $sqid, needsPlus: true);
        $connection = $group->es_connection_id !== null ? EsConnection::query()->find($group->es_connection_id) : null;

        if ($group->es_schedule === null || $connection === null || ! $connection->usable()) {
            throw new Refusal(404, 'not_found');
        }

        if (! in_array($eventId, array_column($this->events($connection, $group->es_schedule) ?? [], 'id'), true)) {
            throw new Refusal(404, 'not_found');
        }

        $songs = GroupSong::query()->with('template')->where('group_id', $group->id)->get()->keyBy(fn (GroupSong $song): string => $song->template->slug);

        foreach (array_values($templateIds) as $i => $templateId) {
            if (! $songs->has($templateId)) {
                throw ValidationException::withMessages(["templateIds.{$i}" => __('validation.group_song')]);
            }
        }

        DB::transaction(function () use ($group, $eventId, $templateIds, $songs): void {
            GroupEventSong::query()->where('group_id', $group->id)->where('es_event_id', $eventId)->delete();

            foreach ($templateIds as $templateId) {
                $song = $songs->get($templateId);

                if ($song !== null) {
                    $link = new GroupEventSong;
                    $link->forceFill(['group_id' => $group->id, 'es_event_id' => $eventId, 'group_song_id' => $song->id])->save();
                }
            }
        });

        return $this->list($director, $sqid);
    }

    /**
     * @return array{members: list<array{id: string, name: string, email: string|null, role: string|null}>, next: string|null}
     */
    public function members(User $director, string $sqid, ?string $cursor): array
    {
        $group = $this->groups->directed($director, $sqid);
        $connection = $group->es_connection_id !== null ? EsConnection::query()->find($group->es_connection_id) : null;

        if ($group->es_schedule === null || $connection === null) {
            throw new Refusal(404, 'not_found');
        }

        if (! $connection->usable()) {
            throw new Refusal(409, 'reconnect_required');
        }

        return $this->es->members($connection, $group->es_schedule, $cursor);
    }

    /**
     * The schedule's events for the next 60 days, or null when ES can't give them now.
     *
     * @return list<array{id: string, name: string, startsAt: string, endsAt: string|null, timezone: string, url: string|null}>|null
     */
    private function events(EsConnection $connection, string $schedule): ?array
    {
        $key = "es-events:{$connection->id}:{$schedule}:".now()->toDateString();

        try {
            return Cache::remember($key, 300, fn (): array => $this->es->events($connection, $schedule, now()->toDateString(), now()->addDays(self::DAYS)->toDateString()));
        } catch (EventScheduleError $e) {
            // The group page still works, saying the rehearsals can't be shown right now.
            report($e);

            return null;
        }
    }

    private static function scheduleUrl(EsConnection $connection, string $schedule): ?string
    {
        $host = parse_url($connection->base_url, PHP_URL_HOST);

        return $connection->mode === EsConnection::OAUTH && is_string($host) ? 'https://'.$schedule.'.'.$host : null;
    }
}
