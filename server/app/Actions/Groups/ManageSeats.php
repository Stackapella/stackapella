<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Exceptions\Refusal;
use App\Models\AuditLog;
use App\Models\CheckoutSession;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupSeat;
use App\Models\User;
use App\Services\Billing\BillingGateway;
use App\Support\Billing\CheckoutCounts;
use App\Support\Billing\Withdrawal;
use App\Support\Groups\ChoirSeats;
use App\Support\Ids;
use App\Support\Pages\Prices;
use App\Support\Trust\TrustLevel;
use App\Support\Usage\Plans;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Laravel\Cashier\Exceptions\IncompletePayment;
use Laravel\Cashier\Subscription;

/**
 * A choir's seats, bought and managed by its leader (docs/choir-seats-plan.md; Hillel, 2026-11-18): Stripe Checkout
 * for a pack (from 10 seats, yearly, paid from the start since 2026-11-21), more or fewer seats later (Stripe
 * prorates; the portal can do the same), and a seat moved from one member to another. Behind the choir_seats switch.
 */
final class ManageSeats
{
    public function __construct(private readonly ManageGroups $groups, private readonly ChoirSeats $seats, private readonly BillingGateway $gateway, private readonly TrustLevel $trust, private readonly Plans $plans) {}

    /**
     * groups.seats.checkout: the Checkout page's address.
     */
    public function checkout(User $user, string $sqid, int $seats, string $currency, ?string $country = null, bool $startNowConsent = false): string
    {
        // Counted, started or refused and why (CheckoutCounts): the funnel in Admin.
        return CheckoutCounts::counted('seats', fn (): string => $this->startCheckout($user, $sqid, $seats, $currency, $country, $startNowConsent));
    }

    private function startCheckout(User $user, string $sqid, int $seats, string $currency, ?string $country, bool $startNowConsent): string
    {
        $group = $this->groups->directed($user, $sqid);

        // Off until the switch is on, and until Stripe is set up (a key and the "Choir seats" price).
        if (! $this->seats->on() || (string) config('cashier.secret') === '' || (string) config('billing.choir.price') === '') {
            throw new Refusal(423, 'paused');
        }

        if ($this->trust->of($user) < TrustLevel::VERIFIED) {
            throw new Refusal(403, 'email_unverified');
        }

        // Paid for already: more or fewer is groups.seats.update (or the portal).
        if ($this->seats->active($group)) {
            throw new Refusal(409, 'seats_active');
        }

        if (Withdrawal::needsConsent($country) && ! $startNowConsent) {
            throw ValidationException::withMessages(['startNowConsent' => __('billing.start_now_consent_required')]);
        }

        // Stripe ties a customer to its first currency (Plus's first Checkout, if any).
        $first = CheckoutSession::query()->where('user_id', $user->id)->whereNotNull('completed_at')->oldest('id')->value('currency');
        $currency = is_string($first) ? $first : $currency;
        $trialEnd = $this->trialEnd($group);
        $session = $this->gateway->seatsCheckout(
            $user,
            ChoirSeats::type($group),
            (string) config('billing.choir.price'),
            $seats,
            $currency,
            $trialEnd,
            url('/groups/'.$group->sqid()).'?seats=bought',
            url('/groups/'.$group->sqid()).'?seats=cancelled',
            ['user' => $user->sqid(), 'group' => $group->sqid(), 'kind' => 'choir_seats'],
            self::terms($seats, $currency, $trialEnd, $user),
        );

        return $session['url'];
    }

    /**
     * groups.seats.update: more or fewer seats on a pack that's paid for. Fewer than are taken drops the seats given
     * last; more fill in join order.
     */
    public function update(User $user, string $sqid, int $seats): Group
    {
        $group = $this->groups->directed($user, $sqid);
        $subscription = $this->seats->subscription($group);

        if (! $this->seats->on() || $subscription === null || ! $subscription->valid()) {
            throw new Refusal(409, 'no_subscription');
        }

        if ((int) $subscription->quantity !== $seats) {
            try {
                $this->gateway->updateSeats($subscription, $seats);
            } catch (IncompletePayment $e) {
                // The prorated invoice needs the card's bank to confirm (or failed): the new number stands on Stripe, and
                // Stripe's own email asks for the payment; the seats follow as the webhook says.
                report($e);
            }
        }

        $this->seats->sync($group);

        return $group;
    }

    /**
     * groups.seats.move: the leader gives a member's seat to another member, who has none and no Plus of their own.
     */
    public function move(User $user, string $sqid, string $fromSqid, string $toSqid): Group
    {
        $group = $this->groups->directed($user, $sqid);

        if (! $this->seats->on() || ! $this->seats->active($group)) {
            throw new Refusal(409, 'no_subscription');
        }

        $from = Ids::decode($fromSqid);
        $to = Ids::decode($toSqid);

        if ($from === null || ! GroupSeat::query()->where('group_id', $group->id)->where('user_id', $from)->exists()) {
            throw ValidationException::withMessages(['from' => __('validation.seat_from')]);
        }

        $member = $to === null ? null : GroupMember::query()->with('user.subscriptions')->where('group_id', $group->id)->where('user_id', $to)->where('role', GroupMember::MEMBER)->first();

        if ($member === null || $to === null || GroupSeat::query()->where('group_id', $group->id)->where('user_id', $to)->exists() || $this->plans->ownPlan($member->user) === 'plus') {
            throw ValidationException::withMessages(['to' => __('validation.seat_to')]);
        }

        // At most billing.choir.moves_a_month in 30 days, each logged (the coordinator for Hillel), so a pack of seats
        // can't be passed round the whole choir.
        $moves = AuditLog::query()->where('action', 'groups.seat_moved')->where('after->group', $group->id)->where('created_at', '>', now()->subDays(30));

        if ($moves->count() >= (int) config('billing.choir.moves_a_month')) {
            $next = $moves->oldest('created_at')->value('created_at');
            $wait = max(1, (int) now()->diffInSeconds(Carbon::parse((string) $next)->addDays(30), true));

            throw new Refusal(429, 'rate_limited', ['retryAfter' => $wait], ['Retry-After' => (string) $wait]);
        }

        $this->seats->move($group, $from, $to);
        AuditLog::record('groups.seat_moved', null, $user, after: ['group' => $group->id, 'from' => $fromSqid, 'to' => $toSqid]);

        return $group;
    }

    /**
     * The whole choir's 14 days, once a group and once a director: a first pack's trial runs to the end of the group's
     * free days from the switch (when it has them), or 14 days; a group that bought seats before, or a director who
     * bought seats for any group, gets none.
     */
    private function trialEnd(Group $group): ?CarbonImmutable
    {
        // No choir trial (2026-11-21): seats are paid from the start.
        if ((int) config('billing.choir.trial_days') <= 0 || $this->seats->subscription($group) !== null) {
            return null;
        }

        // One choir trial a director: a new group (or one deleted and made again) gets no second 14 days, since its
        // seats make its director Plus too.
        if (Subscription::query()->where('user_id', $group->director_id)->where('type', 'like', ChoirSeats::PREFIX.'%')->exists()) {
            return null;
        }

        $free = $this->seats->freeTrialEnds($group);

        return $free !== null ? ($free->isFuture() ? $free : null) : CarbonImmutable::now()->addDays((int) config('billing.choir.trial_days'));
    }

    /**
     * What Stripe's Checkout page says above the button: the seats, what they renew at each year, how to cancel, and
     * the trial's end.
     */
    private static function terms(int $seats, string $currency, ?CarbonImmutable $trialEnd, User $user): string
    {
        $locale = $user->locale ?: 'en';
        $amounts = (array) config('billing.choir.amounts.'.$currency);
        $total = Prices::format((int) $amounts['seat'] * $seats, $currency);
        $words = [
            'seats' => (string) $seats,
            'seat' => Prices::format((int) $amounts['seat'], $currency),
            // Where tax isn't in the price (US dollars), Stripe adds it: the words say so.
            'price' => (bool) $amounts['tax_inclusive'] ? $total : (string) __('billing.checkout_terms.plus_tax', ['price' => $total], $locale),
            'date' => $trialEnd?->settings(['locale' => $locale])->isoFormat('LL') ?? '',
        ];

        return (string) __($trialEnd !== null ? 'billing.seats_terms.trial' : 'billing.seats_terms.renews', $words, $locale);
    }
}
