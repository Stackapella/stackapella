<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Exceptions\Refusal;
use App\Models\AuditLog;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupSeat;
use App\Models\User;
use App\Support\Groups\ChoirSeats;
use App\Support\Ids;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Admin's look at a choir's seats (admin.groups.seats.*; docs/choir-seats-plan.md): where they stand, who holds one,
 * who's seated by their own Plus and who has none, the director's moves, and Stripe's page for the pack. Staff can move
 * a seat without the director's monthly limit, or fill the seats again from what Stripe says: in sudo, with a reason,
 * audited.
 */
final class AdminSeats
{
    public function __construct(private readonly ChoirSeats $seats) {}

    /**
     * @return array<string, mixed>
     */
    public function show(string $sqid): array
    {
        $group = $this->group($sqid);

        return $this->shown($group, $this->members($group));
    }

    /**
     * @param  Collection<int, GroupMember>  $members
     * @return array<string, mixed>
     */
    private function shown(Group $group, Collection $members): array
    {
        $panel = $this->seats->panel($group);
        $subscription = $this->seats->subscription($group);
        $states = $this->seats->states($group, $members);
        $since = GroupSeat::query()->where('group_id', $group->id)->pluck('assigned_at', 'user_id');
        $person = static fn (User $user): array => ['id' => $user->sqid(), 'name' => (string) $user->name];

        $byState = static fn (string $state): array => array_values($members->filter(static fn (GroupMember $m): bool => ($states[$m->user_id] ?? null) === $state)
            ->map(static fn (GroupMember $m): array => $person($m->user))->all());
        $holders = array_values($members->filter(static fn (GroupMember $m): bool => $since->has($m->user_id) && ($states[$m->user_id] ?? null) !== 'own')
            ->map(static fn (GroupMember $m): array => [...$person($m->user), 'since' => Carbon::parse((string) $since->get($m->user_id))->utc()->toIso8601ZuluString()])->all());

        // The director's moves in the last 30 days (staff's aren't counted), and when they may move again.
        $moves = AuditLog::query()->where('action', 'groups.seat_moved')->where('after->group', $group->id)->where('created_at', '>', now()->subDays(30));
        $count = $moves->count();
        $limit = (int) config('billing.choir.moves_a_month');
        $oldest = $count >= $limit ? (clone $moves)->oldest('created_at')->value('created_at') : null;
        $test = str_starts_with((string) config('cashier.secret'), 'sk_test');

        return [
            'group' => ['id' => $group->sqid(), 'name' => $group->name, 'director' => $person($group->director)],
            ...$panel,
            'startedAt' => $subscription?->created_at?->utc()->toIso8601ZuluString(),
            'movesThisMonth' => $count,
            'movesLeft' => max(0, $limit - $count),
            'nextMoveAt' => $oldest !== null ? Carbon::parse((string) $oldest)->addDays(30)->utc()->toIso8601ZuluString() : null,
            'stripeUrl' => $subscription !== null ? 'https://dashboard.stripe.com/'.($test ? 'test/' : '').'subscriptions/'.$subscription->stripe_id : null,
            'holders' => $holders,
            'own' => $byState('own'),
            'unseated' => $byState('needed'),
        ];
    }

    /**
     * @param  array{action: string, from?: string|null, to?: string|null, reason: string}  $data
     * @return array<string, mixed>
     */
    public function update(User $admin, string $sqid, array $data, ?string $ip): array
    {
        $group = $this->group($sqid);

        $members = $this->members($group);

        if ($data['action'] === 'sync') {
            $this->seats->sync($group);
            AuditLog::record('admin.groups.seats_synced', $group->director, $admin, after: ['group' => $group->id], reason: $data['reason'], ip: $ip);

            return $this->shown($group, $members);
        }

        $from = Ids::decode((string) ($data['from'] ?? ''));
        $to = Ids::decode((string) ($data['to'] ?? ''));

        if ($from === null || ! GroupSeat::query()->where('group_id', $group->id)->where('user_id', $from)->exists()) {
            throw ValidationException::withMessages(['from' => __('validation.seat_from')]);
        }

        $member = $to === null ? null : $members->firstWhere('user_id', $to);

        if ($member === null || $to === null || GroupSeat::query()->where('group_id', $group->id)->where('user_id', $to)->exists() || ($this->seats->states($group, [$member])[$to] ?? null) === 'own') {
            throw ValidationException::withMessages(['to' => __('validation.seat_to')]);
        }

        // Staff's move: not the director's, so it isn't counted against their limit.
        $this->seats->move($group, $from, $to);
        AuditLog::record('admin.groups.seat_moved', $group->director, $admin, after: ['group' => $group->id, 'from' => $data['from'] ?? null, 'to' => $data['to'] ?? null], reason: $data['reason'], ip: $ip);

        return $this->shown($group, $members);
    }

    /**
     * @return Collection<int, GroupMember>
     */
    private function members(Group $group): Collection
    {
        return GroupMember::query()->with('user.subscriptions')->where('group_id', $group->id)->where('role', GroupMember::MEMBER)->orderBy('id')->get();
    }

    private function group(string $sqid): Group
    {
        return Group::query()->with('director')->whereSqid($sqid)->first() ?? throw new Refusal(404, 'not_found');
    }
}
