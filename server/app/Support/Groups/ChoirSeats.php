<?php

declare(strict_types=1);

namespace App\Support\Groups;

use App\Actions\Counts\RecordCounts;
use App\Actions\Ideas\Ideas;
use App\Exceptions\Refusal;
use App\Models\AuditLog;
use App\Models\Flag;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupSeat;
use App\Models\GroupSong;
use App\Models\Template;
use App\Models\User;
use App\Support\Flags;
use App\Support\Usage\Plans;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Subscription;

/**
 * Choir seats (Hillel, 2026-11-18: "a key part of pricing needs to be if a choir uses it they all need to pay";
 * docs/choir-seats-plan.md). Behind the `choir_seats` switch; off, nothing here refuses anything.
 *
 * Every member of a choir group needs a seat for what the group does: its own songs, and practice and takes on them.
 * The group's leader buys the seats (a Stripe subscription of the "Choir seats" price, quantity = seats, its Cashier
 * type `choir_<group id>` on the leader's account), and they fill in join order up to the quantity; the leader moves
 * one from a member to another. A seat is Plus, so a member with their own Plus is seated without one, and holding a
 * seat gives the singer every Plus extra too (Plans::plan). The leader is seated while the group's seats are paid,
 * without using one. Groups made before the switch went on have 14 days free for the whole choir from that day.
 *
 * When the seats lapse, the unseated can't practise or record in the group (read-only), and nothing is deleted: the
 * seat rows stay, so the same singers are seated again when the seats return.
 */
final class ChoirSeats
{
    public const PREFIX = 'choir_';

    public const FLAG = 'choir_seats';

    /** @var array{at: CarbonImmutable|null}|null */
    private ?array $switchedOn = null;

    /** @var array<int, Subscription|null> each group's seats subscription, once a request (scoped) */
    private array $subscriptions = [];

    /** @var array<int, list<int>> each group's seat holders */
    private array $holderIds = [];

    /** @var array<int, bool> whether each person holds a seat anywhere */
    private array $any = [];

    public function __construct(private readonly Flags $flags) {}

    public function on(): bool
    {
        return $this->flags->on(self::FLAG);
    }

    /** 403 plus_required saying it's a seat (`reason: seat_needed`): the app shows "Ask your director", nothing to buy. */
    public static function refusal(): Refusal
    {
        return new Refusal(403, 'plus_required', ['reason' => 'seat_needed']);
    }

    /** The group's seats subscription's Cashier type, on its leader's account. */
    public static function type(Group|int $group): string
    {
        return self::PREFIX.($group instanceof Group ? $group->id : $group);
    }

    /** The group a seats subscription's type names, or null when it's another subscription. */
    public static function groupIdOf(?string $type): ?int
    {
        return is_string($type) && preg_match('/^'.self::PREFIX.'(\d+)$/', $type, $m) === 1 ? (int) $m[1] : null;
    }

    /** A pack's size band, as counted (seats_bought_<band>): 10 to 19, 20 to 49, 50 and up. */
    public static function band(int $seats): string
    {
        return match (true) {
            $seats >= 50 => '50',
            $seats >= 20 => '20',
            default => '10',
        };
    }

    /** The group's latest seats subscription, whatever its state, or null when none was ever bought. */
    public function subscription(Group $group): ?Subscription
    {
        if (array_key_exists($group->id, $this->subscriptions)) {
            return $this->subscriptions[$group->id];
        }

        return $this->subscriptions[$group->id] = Subscription::query()->where('user_id', $group->director_id)->where('type', self::type($group))->latest('created_at')->latest('id')->first();
    }

    /** Paid for now: trialing, active, retrying a payment, or cancelled but paid up. */
    public function active(Group $group): bool
    {
        return $this->subscription($group)?->valid() === true;
    }

    /** How many seats are paid for now (0 when none, or lapsed). */
    public function bought(Group $group): int
    {
        $subscription = $this->subscription($group);

        return $subscription !== null && $subscription->valid() ? max(0, (int) $subscription->quantity) : 0;
    }

    /**
     * The moment the switch first went from off to on (from its audit entry, so turning it off and on again never gives
     * a second trial), or null while it's off.
     */
    public function switchedOnAt(): ?CarbonImmutable
    {
        if (! $this->on()) {
            return null;
        }

        if ($this->switchedOn === null) {
            $on = AuditLog::query()->where('action', 'flag.'.self::FLAG)->oldest('id')->get(['before', 'after', 'created_at'])
                ->first(static fn (AuditLog $log): bool => ($log->before['value'] ?? 'off') === 'off' && ($log->after['value'] ?? null) === 'on');
            $at = $on->created_at ?? Flag::query()->where('name', self::FLAG)->value('updated_at');
            $this->switchedOn = ['at' => $at === null ? null : CarbonImmutable::parse($at)];
        }

        return $this->switchedOn['at'];
    }

    /**
     * The end of a group's 14 free days from the switch (a group made before it went on, that never bought seats), past
     * or not; null for any other group.
     */
    public function freeTrialEnds(Group $group): ?CarbonImmutable
    {
        $on = $this->switchedOnAt();

        // No choir trial (2026-11-21): no free days at all.
        if ((int) config('billing.choir.trial_days') <= 0) {
            return null;
        }

        if ($on === null || $group->created_at === null || $group->created_at->gte($on) || $this->subscription($group) !== null) {
            return null;
        }

        return $on->addDays((int) config('billing.choir.trial_days'));
    }

    public function inFreeTrial(Group $group): bool
    {
        return $this->freeTrialEnds($group)?->isFuture() === true;
    }

    /**
     * Whether the person may practise and record in the group with the switch on: their own Plus, the whole choir's
     * free days, or the seats paid and they lead the group or hold one (Plans::seatedIn decides with the switch off).
     */
    public function seated(User $user, Group $group): bool
    {
        if ($this->ownPlus($user) || $this->inFreeTrial($group)) {
            return true;
        }

        if (! $this->active($group)) {
            return false;
        }

        if ($group->director_id === $user->id || $this->holds($user, $group)) {
            return true;
        }

        // A seat may be free for them since the last fill (the pack grew, a holder has their own Plus now): fill, then
        // look again. sync() writes only when something changed, so a full choir's reads never do.
        $this->sync($group);

        return $this->holds($user, $group);
    }

    /**
     * For the director's view: `own` (their own Plus), `seated` or `needed`, for each member at once.
     *
     * @param  iterable<GroupMember>  $members  with their users loaded
     * @return array<int, 'own'|'seated'|'needed'> by user id
     */
    public function states(Group $group, iterable $members): array
    {
        $trial = $this->inFreeTrial($group);
        $active = $this->active($group);
        $holders = array_flip($this->holders($group));
        $states = [];

        foreach ($members as $member) {
            $states[$member->user_id] = match (true) {
                $this->ownPlus($member->user) => 'own',
                $trial, $active && ($member->user_id === $group->director_id || isset($holders[$member->user_id])) => 'seated',
                default => 'needed',
            };
        }

        return $states;
    }

    /**
     * The director's seat panel: where the seats stand, how many are paid for and taken (members on their own Plus never
     * take one), and the dates the app shows.
     *
     * @return array{status: 'none'|'free_trial'|'trialing'|'active'|'past_due'|'canceled'|'lapsed', bought: int, used: int, min: int, trialEndsAt: string|null, endsAt: string|null}
     */
    public function panel(Group $group): array
    {
        $subscription = $this->subscription($group);
        $valid = $subscription !== null && $subscription->valid();
        $free = $this->freeTrialEnds($group);

        $status = match (true) {
            $valid && $subscription->onTrial() => 'trialing',
            $valid && $subscription->pastDue() => 'past_due',
            $valid && $subscription->onGracePeriod() => 'canceled',
            $valid => 'active',
            $free?->isFuture() === true => 'free_trial',
            $subscription !== null => 'lapsed',
            default => 'none',
        };

        $trialEnds = match ($status) {
            'trialing' => $subscription?->trial_ends_at,
            'free_trial' => $free,
            default => null,
        };

        return [
            'status' => $status,
            'bought' => $valid ? max(0, (int) $subscription->quantity) : 0,
            'used' => $valid ? count($this->holders($group)) : 0,
            'min' => (int) config('billing.choir.min_seats'),
            'trialEndsAt' => $trialEnds?->utc()->toIso8601ZuluString(),
            'endsAt' => $valid && $subscription->onGracePeriod() ? $subscription->ends_at?->utc()->toIso8601ZuluString() : null,
        ];
    }

    /**
     * Fill the seats: members on their own Plus give theirs up, a pack made smaller drops the seats given last, and the
     * free ones go to the unseated in join order. Only while the seats are paid for: lapsed, every seat stays as it is.
     */
    public function sync(Group $group): void
    {
        if (! $this->active($group)) {
            return;
        }

        $quantity = $this->bought($group);
        $members = GroupMember::query()->with('user.subscriptions')->where('group_id', $group->id)->where('role', GroupMember::MEMBER)->orderBy('created_at')->orderBy('id')->get();
        $needing = $members->reject(fn (GroupMember $member): bool => $this->ownPlus($member->user))->pluck('user_id')->map(static fn (mixed $id): int => (int) $id)->all();

        // Nothing to change (the usual read of a full choir): no lock, no write.
        $holders = $this->holders($group);
        $kept = array_values(array_intersect($holders, $needing));
        $waiting = array_diff($needing, $holders) !== [];

        if (count($kept) === count($holders) && count($kept) <= $quantity && ! ($waiting && count($kept) < $quantity)) {
            return;
        }

        $this->forget();

        DB::transaction(function () use ($group, $quantity, $needing): void {
            Group::query()->whereKey($group->id)->lockForUpdate()->first();

            // Gone, or on their own Plus now: the seat is free again.
            GroupSeat::query()->where('group_id', $group->id)->whereNotIn('user_id', $needing === [] ? [0] : $needing)->delete();

            $kept = GroupSeat::query()->where('group_id', $group->id)->orderBy('assigned_at')->orderBy('id')->get();

            if ($kept->count() > $quantity) {
                GroupSeat::query()->whereKey($kept->slice($quantity)->pluck('id')->all())->delete();
                $kept = $kept->take($quantity);
            }

            $held = array_flip($kept->pluck('user_id')->map(static fn (mixed $id): int => (int) $id)->all());
            $free = $quantity - $kept->count();

            foreach ($needing as $userId) {
                if ($free <= 0) {
                    break;
                }

                if (! isset($held[$userId])) {
                    (new GroupSeat)->forceFill(['group_id' => $group->id, 'user_id' => $userId, 'assigned_at' => now()])->save();
                    $free--;
                }
            }
        });

        $this->forget();
    }

    /** What was worked out about seats this request: they changed. */
    public function forget(?int $groupId = null): void
    {
        $this->holderIds = [];
        $this->any = [];

        if ($groupId !== null) {
            unset($this->subscriptions[$groupId]);
        }
    }

    /**
     * The leader moves a seat from one member to another (the one giving it up holds it; the one taking it has none and
     * no Plus of their own).
     */
    public function move(Group $group, int $from, int $to): void
    {
        DB::transaction(function () use ($group, $from, $to): void {
            Group::query()->whereKey($group->id)->lockForUpdate()->first();
            GroupSeat::query()->where('group_id', $group->id)->where('user_id', $from)->delete();
            // A fill a moment ago may have seated them already: the seat is theirs either way.
            GroupSeat::query()->insertOrIgnore(['group_id' => $group->id, 'user_id' => $to, 'assigned_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        });

        $this->forget();
    }

    /**
     * After Stripe's word on a seats subscription: the seats fill again, and a first pack bought is counted by its size
     * band (never the group or who bought it).
     */
    public function subscriptionChanged(int $groupId, bool $started, ?string $status, int $quantity): void
    {
        // Cashier has just written the subscription: read it again.
        $this->forget($groupId);
        $group = Group::query()->find($groupId);

        if ($group !== null) {
            $this->sync($group);
        }

        if ($started && in_array($status, ['active', 'trialing'], true)) {
            app(RecordCounts::class)->server('seats_bought_'.self::band($quantity), true, null);
        }
    }

    /**
     * Whether the person needs a seat to sing this song: the switch on, a choir's own song (a person's template, not
     * public, not on a link, not a listed Idea) they reach only through a group they sing in, unseated in every such
     * group. Our library songs never need one: they stay free to learn alone.
     */
    public function needsSeatFor(User $user, Template $template): bool
    {
        if (! $this->on() || $template->owner_id === null || $template->owner_id === $user->id || $template->isPublic() || $template->visibility === 'unlisted' || app(Ideas::class)->isListedFor($template)) {
            return false;
        }

        $groups = Group::query()->whereIn('id', GroupSong::query()->where('template_id', $template->id)->select('group_id'))
            ->whereHas('members', fn ($query) => $query->where('user_id', $user->id))->get();

        return $groups->isNotEmpty() && $groups->every(fn (Group $group): bool => ! $this->seated($user, $group));
    }

    public function ensureSeatFor(User $user, Template $template): void
    {
        if ($this->needsSeatFor($user, $template)) {
            throw self::refusal();
        }
    }

    /**
     * Whether the person holds a seat anywhere now (a seat is Plus): a group's free days, a seat in a group whose seats
     * are paid, or leading such a group. Their own Plus isn't asked here (Plans::plan has it).
     */
    public function holdsAny(User $user): bool
    {
        return $this->any[$user->id] ??= $this->findAny($user);
    }

    private function findAny(User $user): bool
    {
        $on = $this->switchedOnAt();

        if ($on === null) {
            return false;
        }

        $free = (int) config('billing.choir.trial_days') > 0 && $on->addDays((int) config('billing.choir.trial_days'))->isFuture();
        $groups = Group::query()
            ->where(fn ($query) => $query->where('director_id', $user->id)
                ->orWhereIn('id', GroupSeat::query()->where('user_id', $user->id)->select('group_id'))
                ->when($free, fn ($q) => $q->orWhere(fn ($old) => $old->where('created_at', '<', $on->toDateTimeString())->whereIn('id', GroupMember::query()->where('user_id', $user->id)->select('group_id')))))
            ->get();

        foreach ($groups as $group) {
            if ($this->inFreeTrial($group) || ($this->active($group) && ($group->director_id === $user->id || $this->holds($user, $group)))) {
                return true;
            }
        }

        return false;
    }

    /**
     * The seats subscription to cancel when its group goes (deleted, or with its leader's account), if it's still paid.
     */
    public function cancellable(Group $group): ?Subscription
    {
        $subscription = $this->subscription($group);

        return $subscription !== null && $subscription->valid() && ! $subscription->onGracePeriod() ? $subscription : null;
    }

    /**
     * @return list<int> the members holding one of the group's seats
     */
    public function holders(Group $group): array
    {
        return $this->holderIds[$group->id] ??= array_values(array_map('intval', GroupSeat::query()->where('group_id', $group->id)->pluck('user_id')->all()));
    }

    private function holds(User $user, Group $group): bool
    {
        return in_array($user->id, $this->holders($group), true);
    }

    private function ownPlus(User $user): bool
    {
        return app(Plans::class)->ownPlan($user) === 'plus';
    }
}
