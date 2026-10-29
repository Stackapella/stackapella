<?php

declare(strict_types=1);

namespace App\Actions\Coaching;

use App\Exceptions\Refusal;
use App\Jobs\BookLesson;
use App\Models\AuditLog;
use App\Models\CoachOffering;
use App\Models\CoachProfile;
use App\Models\Engagement;
use App\Models\EngagementReport;
use App\Models\ExercisePack;
use App\Models\LessonBooking;
use App\Models\PackPurchase;
use App\Models\ReviewItem;
use App\Models\User;
use App\Services\Coaching\CoachingGateway;
use App\Support\Accounts\Age;
use App\Support\Ids;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Buying from a coach and what happens to the money (docs/coaching-marketplace-plan.md F3): a
 * review is paid when requested and refunded in full if it's declined, not answered in 48 hours,
 * late, or cancelled before it's accepted; the coach's share moves once it's delivered. A package is
 * a monthly subscription, a pack a one-off (or free) purchase. A lesson is paid like a review, then
 * booked in Event Schedule (Lessons).
 *
 * Every call to Stripe that moves money carries an idempotency key made from what's being done, so
 * a retry after anything fails (a lock, a timeout, the database) is the same call, never a second.
 */
final class Engagements
{
    public function __construct(private readonly CoachingGateway $stripe, private readonly Reviews $reviews, private readonly Lessons $lessons) {}

    /**
     * @param  array{offeringId?: string|null, packId?: string|null, takeId?: string|null, question?: string|null, pitchTrack?: array<string, mixed>|null, scores?: array<string, mixed>|null, adult?: bool|null, slot?: string|null, timezone?: string|null, notes?: string|null}  $data
     * @return array{engagement: Engagement, checkoutUrl: string|null}
     */
    public function create(User $singer, array $data): array
    {
        Age::ensureMayClaimAdult($singer);

        if ($singer->adult_confirmed_at === null && ($data['adult'] ?? false) !== true) {
            throw ValidationException::withMessages(['adult' => __('validation.coaching_adult')]);
        }

        $offering = isset($data['offeringId']) ? CoachOffering::query()->whereSqid((string) $data['offeringId'])->whereNull('archived_at')->first() : null;
        $pack = $offering === null && isset($data['packId']) ? ExercisePack::query()->where('uuid', (string) $data['packId'])->whereNotNull('approved_exercises')->whereNotNull('approved_price_cents')->first() : null;

        if ($offering === null && $pack === null) {
            throw new Refusal(404, 'not_found');
        }

        $coachId = (int) ($offering->coach_id ?? $pack?->coach_id);
        // A pack sells at the price Hillel approved, never a change waiting for review.
        $price = (int) ($offering->price_cents ?? $pack?->approved_price_cents);

        if ($coachId === $singer->id) {
            throw new Refusal(409, 'own_coaching');
        }

        if (! self::coachAvailable($coachId, needsCharges: $price > 0)) {
            throw new Refusal(409, 'coach_not_ready');
        }

        if ($price > 0 && ! $this->stripe->ready()) {
            throw new Refusal(423, 'paused');
        }

        $kind = match (true) {
            $pack !== null => Engagement::PACK,
            $offering !== null && $offering->kind === CoachOffering::PACKAGE => Engagement::PACKAGE,
            $offering !== null && $offering->kind === CoachOffering::LESSON => Engagement::LESSON,
            default => Engagement::REVIEW,
        };

        // A lesson's time is asked of Event Schedule afresh before anything is paid.
        $slot = null;

        if ($kind === Engagement::LESSON && $offering !== null) {
            if (! is_string($data['slot'] ?? null) || $data['slot'] === '') {
                throw ValidationException::withMessages(['slot' => __('validation.required', ['attribute' => 'slot'])]);
            }

            $slot = $this->lessons->check($offering, $data['slot']);
        }

        // Again just before it's kept: a birth date given meanwhile still wins.
        Age::ensureMayClaimAdult($singer->fresh() ?? $singer);
        $singer->forceFill(['adult_confirmed_at' => $singer->adult_confirmed_at ?? now()])->save();

        $engagement = DB::transaction(function () use ($singer, $data, $offering, $pack, $kind, $price, $coachId, $slot): Engagement {
            $engagement = new Engagement(['kind' => $kind, 'price_cents' => $price, 'currency' => $offering->currency ?? $pack?->approved_currency, 'turnaround_days' => $offering?->turnaround_days, 'reviews_per_month' => $offering?->reviews_per_month]);
            $engagement->forceFill([
                'singer_id' => $singer->id,
                'coach_id' => $coachId,
                'offering_id' => $offering?->id,
                'pack_id' => $pack?->id,
                'status' => $price === 0 ? Engagement::DELIVERED : Engagement::PENDING_PAYMENT,
                'fee_cents' => self::fee($price),
                'refunded_cents' => 0,
                'period_refunded_cents' => 0,
            ])->save();

            if ($kind === Engagement::REVIEW) {
                $this->reviews->attach($singer, $engagement, $data, $offering?->max_seconds);
            }

            if ($slot !== null && $offering !== null) {
                // The coach's policy as it is now is what the singer agrees to.
                $hours = (int) (CoachProfile::query()->where('user_id', $coachId)->value('cancellation_hours') ?? config('coaching.cancellation_hours'));
                $lesson = new LessonBooking;
                $lesson->forceFill([
                    'engagement_id' => $engagement->id,
                    'es_connection_id' => $slot['connection']->id,
                    'appointment_type_id' => (string) $offering->es_appointment_type_id,
                    'status' => LessonBooking::BOOKING,
                    'starts_at' => $slot['startsAt'],
                    'ends_at' => $slot['startsAt']->copy()->addMinutes((int) $offering->duration_minutes),
                    'agreed_ends_at' => $slot['startsAt']->copy()->addMinutes((int) $offering->duration_minutes),
                    'timezone' => (string) ($slot['connection']->schedule_timezone ?? 'UTC'),
                    'guest_timezone' => (string) ($data['timezone'] ?? 'UTC'),
                    'notes' => $data['notes'] ?? null,
                    'cancellation_hours' => $hours,
                    'refund_until' => $slot['startsAt']->copy()->subHours($hours),
                ])->save();
            }

            if ($price === 0 && $pack !== null) {
                PackPurchase::query()->firstOrCreate(['pack_id' => $pack->id, 'user_id' => $singer->id], ['engagement_id' => $engagement->id]);
            }

            return $engagement;
        });

        if ($price === 0) {
            return ['engagement' => $engagement, 'checkoutUrl' => null];
        }

        $base = rtrim((string) config('app.url'), '/');
        $metadata = ['kind' => 'coaching', 'engagement' => $engagement->sqid()];
        $success = $base.'/coaching/return?engagement='.$engagement->sqid();
        $name = $offering !== null ? $offering->title : ($pack !== null ? ($pack->approved_title ?? $pack->title) : '');
        $destination = (string) CoachProfile::query()->where('user_id', $coachId)->value('stripe_account_id');

        $checkout = match ($kind) {
            Engagement::PACKAGE => $this->stripe->subscriptionCheckout($singer, $price, (string) $engagement->currency, $name, $success, $success.'&cancelled=1', $metadata, $destination, (int) config('coaching.commission_percent')),
            Engagement::PACK => $this->stripe->paymentCheckout($singer, $price, (string) $engagement->currency, $name, $success, $success.'&cancelled=1', $metadata, $destination, $engagement->fee_cents),
            default => $this->stripe->paymentCheckout($singer, $price, (string) $engagement->currency, $name, $success, $success.'&cancelled=1', $metadata),
        };

        $engagement->forceFill(['stripe_checkout_id' => $checkout['id']])->save();

        return ['engagement' => $engagement, 'checkoutUrl' => $checkout['url']];
    }

    /**
     * Stripe's checkout.session.completed (or async_payment_succeeded) for a coaching purchase. Only
     * a paid Checkout counts; one paid after it was cancelled, or for a coach who can no longer
     * sell, is refunded at once.
     */
    public function checkoutCompleted(string $sessionId): void
    {
        $result = $this->stripe->checkoutResult($sessionId);

        if (! in_array($result['paymentStatus'], ['paid', 'no_payment_required'], true)) {
            return;
        }

        $book = null;
        $refund = DB::transaction(function () use ($sessionId, $result, &$book): ?Engagement {
            $engagement = Engagement::query()->where('stripe_checkout_id', $sessionId)->lockForUpdate()->first();

            if ($engagement === null) {
                report(new RuntimeException("A coaching Checkout was paid with no purchase to match: {$sessionId}."));

                return null;
            }

            // Seen before: done, unless its refund didn't go through then (Stripe redelivers; the
            // refund's key makes this the same refund).
            if ($engagement->stripe_payment_intent !== null || $engagement->stripe_subscription !== null) {
                return $engagement->status === Engagement::REFUNDED && $engagement->refunded_cents < $engagement->price_cents ? $engagement : null;
            }

            $engagement->forceFill(['stripe_payment_intent' => $result['paymentIntent'], 'stripe_charge' => $result['charge'], 'stripe_subscription' => $result['subscription']]);

            // Cancelled before paying (or the coach is gone): it's refunded, never delivered.
            if ($engagement->status !== Engagement::PENDING_PAYMENT || ! self::coachAvailable((int) $engagement->coach_id, needsCharges: true)) {
                $engagement->forceFill(['status' => Engagement::REFUNDED, 'decline_reason' => $engagement->status === Engagement::CANCELLED ? 'cancelled' : 'coach_unavailable', 'ended_at' => now()])->save();

                return $engagement;
            }

            match ($engagement->kind) {
                Engagement::REVIEW => $engagement->forceFill(['status' => Engagement::REQUESTED, 'accept_by' => now()->addHours((int) config('coaching.accept_hours'))]),
                Engagement::PACKAGE => $engagement->forceFill(['status' => Engagement::ACTIVE, 'reviews_left' => $engagement->reviews_per_month, 'period_ends_at' => now()->addMonth()]),
                Engagement::LESSON => $engagement->forceFill(['status' => Engagement::BOOKING]),
                default => $engagement->forceFill(['status' => Engagement::DELIVERED]),
            };
            $engagement->save();

            if ($engagement->kind === Engagement::PACK && $engagement->pack_id !== null) {
                PackPurchase::query()->firstOrCreate(['pack_id' => $engagement->pack_id, 'user_id' => $engagement->singer_id], ['engagement_id' => $engagement->id]);
            }

            $book = $engagement->kind === Engagement::LESSON ? $engagement->id : null;

            return null;
        });

        if ($refund !== null) {
            $this->giveBack($refund);
        }

        if ($book !== null) {
            BookLesson::dispatch($book);
        }
    }

    /**
     * A delayed payment that failed (checkout.session.async_payment_failed): nothing was bought.
     */
    public function checkoutFailed(string $sessionId): void
    {
        Engagement::query()->where('stripe_checkout_id', $sessionId)->where('status', Engagement::PENDING_PAYMENT)->update(['status' => Engagement::CANCELLED, 'ended_at' => now(), 'updated_at' => now()]);
    }

    /**
     * A package's month paid (invoice.paid): its reviews start again (unused ones don't roll over).
     */
    public function packagePaid(string $subscriptionId, ?int $periodEnd): bool
    {
        $engagement = Engagement::query()->where('stripe_subscription', $subscriptionId)->first();

        if ($engagement === null) {
            return false;
        }

        if (in_array($engagement->status, [Engagement::ACTIVE, Engagement::ENDING], true)) {
            $engagement->forceFill(['reviews_left' => $engagement->reviews_per_month, 'period_refunded_cents' => 0, 'period_ends_at' => $periodEnd !== null ? now()->setTimestamp($periodEnd) : now()->addMonth()])->save();
        }

        return true;
    }

    /**
     * A package's subscription has ended (customer.subscription.deleted).
     */
    public function packageEnded(string $subscriptionId): bool
    {
        $engagement = Engagement::query()->where('stripe_subscription', $subscriptionId)->first();

        if ($engagement !== null && ! in_array($engagement->status, [Engagement::REFUNDED, Engagement::ENDED], true)) {
            $engagement->forceFill(['status' => Engagement::ENDED, 'ended_at' => now(), 'reviews_left' => 0])->save();
        }

        return $engagement !== null;
    }

    /**
     * A chargeback on a coaching charge: the coach's share never moves after it, and Hillel sees it
     * in the disputes queue (the account itself is handled as for any dispute).
     */
    public function disputed(string $chargeId): void
    {
        $engagement = Engagement::query()->where('stripe_charge', $chargeId)->whereNull('disputed_at')->first();

        if ($engagement !== null) {
            DB::transaction(function () use ($engagement): void {
                $engagement->forceFill(['disputed_at' => now()])->save();
                $by = $engagement->singer_id ?? $engagement->coach_id;

                if ($by !== null) {
                    $report = new EngagementReport(['text' => __('coaching.dispute_flag')]);
                    $report->forceFill(['engagement_id' => $engagement->id, 'user_id' => $by])->save();
                }
            });
        }
    }

    public function accept(User $coach, string $sqid): Engagement
    {
        return DB::transaction(function () use ($coach, $sqid): Engagement {
            $engagement = $this->mine($coach, $sqid, 'coach', lock: true);

            // Only a take review is accepted here (a lesson is approved in Event Schedule).
            if ($engagement->kind !== Engagement::REVIEW || $engagement->status !== Engagement::REQUESTED || $engagement->accept_by?->isPast() || ! self::coachAvailable($coach->id, needsCharges: true)) {
                throw new Refusal(409, 'engagement_state');
            }

            $engagement->forceFill(['status' => Engagement::ACCEPTED, 'due_at' => now()->addDays((int) $engagement->turnaround_days)])->save();
            ReviewItem::query()->where('engagement_id', $engagement->id)->where('status', ReviewItem::REQUESTED)->update(['status' => ReviewItem::ACCEPTED, 'updated_at' => now()]);

            return $engagement;
        });
    }

    public function decline(User $coach, string $sqid, ?string $reason): Engagement
    {
        $engagement = $this->mine($coach, $sqid, 'coach');

        return $this->refundInFull($engagement, $reason, [Engagement::REQUESTED]) ?? throw new Refusal(409, 'engagement_state');
    }

    public function cancel(User $user, string $sqid): Engagement
    {
        $engagement = $this->mine($user, $sqid, 'either');

        // A teacher link ends from either side.
        if ($engagement->kind === Engagement::TEACHER && $engagement->status === Engagement::ACTIVE) {
            $engagement->forceFill(['status' => Engagement::ENDED, 'ended_at' => now()])->save();

            return $engagement;
        }

        // A lesson is cancelled in Event Schedule too, by either side (the coach's policy decides).
        if ($engagement->kind === Engagement::LESSON && $engagement->status !== Engagement::PENDING_PAYMENT) {
            return $this->lessons->cancel($user, $engagement);
        }

        if ($engagement->singer_id !== $user->id) {
            throw new Refusal(409, 'engagement_state');
        }

        if ($engagement->status === Engagement::PENDING_PAYMENT) {
            $engagement->forceFill(['status' => Engagement::CANCELLED, 'ended_at' => now()])->save();

            // The Checkout closes too; one paid in the moment before is refunded when it arrives.
            if ($engagement->stripe_checkout_id !== null) {
                $this->stripe->expireCheckout($engagement->stripe_checkout_id);
            }

            return $engagement;
        }

        return match (true) {
            $engagement->kind === Engagement::REVIEW => $this->refundInFull($engagement, null, [Engagement::REQUESTED]) ?? throw new Refusal(409, 'engagement_state'),
            $engagement->kind === Engagement::PACKAGE && $engagement->status === Engagement::ACTIVE => $this->endPackage($engagement),
            default => throw new Refusal(409, 'engagement_state'),
        };
    }

    /**
     * A coach suspended or gone: their open reviews are refunded, their packages stop now with the
     * month refunded.
     */
    public function endForCoach(Engagement $engagement): void
    {
        if ($engagement->kind === Engagement::LESSON) {
            $this->lessons->end($engagement, 'coach');

            return;
        }

        if ($engagement->kind === Engagement::PACKAGE && in_array($engagement->status, [Engagement::ACTIVE, Engagement::ENDING], true)) {
            if ($engagement->stripe_subscription !== null) {
                $this->stripe->cancelPackageNow($engagement->stripe_subscription);
            }

            $engagement->forceFill(['status' => Engagement::ENDED, 'ended_at' => now(), 'reviews_left' => 0])->save();
            $this->giveBack($engagement);

            return;
        }

        $this->refundInFull($engagement, 'coach_unavailable', [Engagement::REQUESTED, Engagement::ACCEPTED]);

        // A review they delivered (inside Unsend, or one whose release keeps failing) is paid to
        // them now, as they did the work; if it can't move, the singer gets their money back, so
        // neither is lost with the review's rows.
        foreach (ReviewItem::query()->where('engagement_id', $engagement->id)->where('status', ReviewItem::DELIVERED)->whereNull('released_at')->get() as $review) {
            if (! $this->release($review)) {
                $this->refundInFull($engagement, 'coach_unavailable', [Engagement::DELIVERED]);
            }
        }
    }

    /**
     * A singer who deletes their account: an open review is refunded, and one delivered inside
     * Unsend is paid to the coach now (they did the work); a package stops now.
     */
    public function endForSinger(Engagement $engagement): void
    {
        if ($engagement->kind === Engagement::LESSON) {
            $this->lessons->end($engagement, 'singer');

            return;
        }

        $this->refundInFull($engagement, 'singer_left', [Engagement::REQUESTED, Engagement::ACCEPTED]);

        foreach (ReviewItem::query()->where('engagement_id', $engagement->id)->where('status', ReviewItem::DELIVERED)->whereNull('released_at')->get() as $review) {
            $this->release($review);
        }

        if ($engagement->kind === Engagement::PACKAGE && $engagement->stripe_subscription !== null && in_array($engagement->status, [Engagement::ACTIVE, Engagement::ENDING], true)) {
            $this->stripe->cancelPackageNow($engagement->stripe_subscription);
            $engagement->forceFill(['status' => Engagement::ENDED, 'ended_at' => now()])->save();
        }
    }

    public function report(User $user, string $sqid, string $text): void
    {
        $engagement = $this->mine($user, $sqid, 'either');
        $report = new EngagementReport(['text' => $text]);
        $report->forceFill(['engagement_id' => $engagement->id, 'user_id' => $user->id])->save();
    }

    /**
     * Hillel's refund, in full or in part: of the payment (for a package, its last month), with the
     * coach's share coming back in proportion as far as it had moved. The engagement's reports are
     * closed, and a review not yet delivered that's refunded in full is ended.
     */
    public function adminRefund(string $sqid, User $admin, int $amount, string $note, ?string $ip): Engagement
    {
        return DB::transaction(function () use ($sqid, $admin, $amount, $note, $ip): Engagement {
            $engagement = Engagement::query()->whereSqid($sqid)->lockForUpdate()->first() ?? throw new Refusal(404, 'not_found');
            $package = $engagement->kind === Engagement::PACKAGE;
            $paid = $package ? ($engagement->stripe_subscription !== null ? $this->stripe->latestPackagePayment($engagement->stripe_subscription) : null) : $engagement->stripe_payment_intent;
            $left = $engagement->price_cents - ($package ? $engagement->period_refunded_cents : $engagement->refunded_cents);

            if ($amount < 1 || $amount > $left || $paid === null) {
                throw ValidationException::withMessages(['amountCents' => __('validation.refund_amount', ['max' => $paid === null ? 0 : $left])]);
            }

            $key = "admin-refund-{$engagement->id}-{$engagement->refunded_cents}-{$amount}";
            $share = $engagement->price_cents > 0 ? intdiv($amount * ($engagement->price_cents - $engagement->fee_cents), $engagement->price_cents) : 0;

            // The coach's share back first (a review's, moved by our transfer); a pack's and a
            // package's move with their charge, so their refund takes it back itself.
            if ($engagement->stripe_transfer !== null && $share > 0) {
                $this->stripe->reverseTransfer($engagement->stripe_transfer, $share, $key.'-reversal');
            }

            $this->stripe->refund($paid, $amount, $key, reverseTransfer: in_array($engagement->kind, [Engagement::PACK, Engagement::PACKAGE], true));

            $engagement->forceFill(['refunded_cents' => $engagement->refunded_cents + $amount, 'period_refunded_cents' => $engagement->period_refunded_cents + ($package ? $amount : 0)]);

            if ($engagement->kind === Engagement::REVIEW && in_array($engagement->status, [Engagement::REQUESTED, Engagement::ACCEPTED], true) && $engagement->refunded_cents >= $engagement->price_cents) {
                $engagement->forceFill(['status' => Engagement::REFUNDED, 'ended_at' => now()]);
                ReviewItem::query()->where('engagement_id', $engagement->id)->whereIn('status', [ReviewItem::REQUESTED, ReviewItem::ACCEPTED])->update(['status' => ReviewItem::CANCELLED, 'updated_at' => now()]);
            }

            $engagement->save();
            EngagementReport::query()->where('engagement_id', $engagement->id)->whereNull('resolved_at')->update(['resolved_at' => now(), 'updated_at' => now()]);
            AuditLog::record('coaching.refund', $engagement->singer, $admin, after: ['engagement' => $engagement->sqid(), 'amountCents' => $amount], reason: $note, ip: $ip);

            return $engagement;
        });
    }

    /**
     * The deadlines (every few minutes): a review not accepted in 48 hours, or not delivered in
     * time, is refunded in full; a delivered review's share moves once Unsend has passed; a Checkout
     * never finished is let go after a day, once Stripe says it wasn't paid. One item failing (Stripe
     * refusing a refund, say) is reported and the rest carry on.
     *
     * @return array{refunded: int, released: int, abandoned: int, failed: int}
     */
    public function deadlines(): array
    {
        $done = ['refunded' => 0, 'released' => 0, 'abandoned' => 0, 'failed' => 0];
        $each = function (callable $step, string $counter) use (&$done): void {
            try {
                $done[$counter] += $step() ? 1 : 0;
            } catch (Throwable $e) {
                report($e);
                $done['failed']++;
            }
        };

        foreach (Engagement::query()->where('kind', Engagement::REVIEW)->where(fn ($query) => $query->where(fn ($q) => $q->where('status', Engagement::REQUESTED)->where('accept_by', '<', now()))->orWhere(fn ($q) => $q->where('status', Engagement::ACCEPTED)->where('due_at', '<', now())))->get() as $late) {
            $each(fn (): bool => $this->refundInFull($late, $late->status === Engagement::REQUESTED ? 'no_answer' : 'missed_deadline', [$late->status]) !== null, 'refunded');
        }

        $window = now()->subMinutes((int) config('coaching.unsend_minutes'));

        foreach (ReviewItem::query()->where('status', ReviewItem::DELIVERED)->whereNull('released_at')->where('delivered_at', '<', $window)->get() as $review) {
            $each(fn (): bool => $this->release($review), 'released');
        }

        foreach (Engagement::query()->with('reviews')->where('status', Engagement::PENDING_PAYMENT)->where('created_at', '<', now()->subDay())->get() as $stale) {
            $each(fn (): bool => $this->letGo($stale), 'abandoned');
        }

        return ['refunded' => $done['refunded'], 'released' => $done['released'], 'abandoned' => $done['abandoned'], 'failed' => $done['failed']];
    }

    /**
     * The person's engagement, as the singer, the coach or either; anyone else finds nothing. The
     * coach sees nothing before it's paid, or 90 days after it ends.
     */
    public function mine(User $user, string $sqid, string $as, bool $lock = false): Engagement
    {
        $engagement = Engagement::query()->with(['singer', 'coach'])->whereSqid($sqid)
            ->where(fn ($query) => match ($as) {
                'coach' => $query->where('coach_id', $user->id),
                'singer' => $query->where('singer_id', $user->id),
                default => $query->where('singer_id', $user->id)->orWhere('coach_id', $user->id),
            })
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->first();

        if ($engagement === null || ($engagement->coach_id === $user->id && ($engagement->status === Engagement::PENDING_PAYMENT || ! self::coachMaySee($engagement)))) {
            throw new Refusal(404, 'not_found');
        }

        return $engagement;
    }

    /**
     * The coach sees what was sent until 90 days after the engagement ends.
     */
    public static function coachMaySee(Engagement $engagement): bool
    {
        return $engagement->ended_at === null || $engagement->ended_at->gt(now()->subDays((int) config('coaching.access_days')));
    }

    /**
     * An approved coach, not suspended, whose account can take charges (when it's needed).
     */
    public static function coachAvailable(int $coachId, bool $needsCharges): bool
    {
        $profile = CoachProfile::query()->with('user')->where('user_id', $coachId)->first();

        return $profile !== null && $profile->status === CoachProfile::APPROVED && $profile->user->suspended_at === null && (! $needsCharges || $profile->charges_enabled);
    }

    public static function fee(int $price): int
    {
        return intdiv($price * (int) config('coaching.commission_percent') + 50, 100);
    }

    /**
     * The coach's share of a delivered review moves to their account (once; its key makes a retry
     * the same transfer).
     */
    public function release(ReviewItem $review): bool
    {
        return DB::transaction(function () use ($review): bool {
            $engagement = Engagement::query()->whereKey($review->engagement_id)->lockForUpdate()->firstOrFail();
            $item = ReviewItem::query()->whereKey($review->id)->lockForUpdate()->firstOrFail();

            if ($item->released_at !== null || $item->status !== ReviewItem::DELIVERED) {
                return false;
            }

            // A package's and a teacher's review moves no money; a refunded or disputed one never does.
            if ($engagement->kind !== Engagement::REVIEW) {
                $item->forceFill(['released_at' => now()])->save();

                return true;
            }

            if ($engagement->status !== Engagement::DELIVERED || $engagement->disputed_at !== null || $engagement->stripe_transfer !== null) {
                return false;
            }

            $account = CoachProfile::query()->where('user_id', $engagement->coach_id)->value('stripe_account_id');
            $charge = $engagement->stripe_charge ?? ($engagement->stripe_payment_intent !== null ? $this->stripe->chargeOf($engagement->stripe_payment_intent) : null);

            if (! is_string($account) || $charge === null) {
                report(new RuntimeException("A delivered review's share can't move yet (no charge or no account): {$engagement->sqid()}."));

                return false;
            }

            $share = $engagement->price_cents - $engagement->fee_cents - intdiv($engagement->refunded_cents * ($engagement->price_cents - $engagement->fee_cents), max(1, $engagement->price_cents));
            $transfer = $share > 0 ? $this->stripe->transfer($share, (string) $engagement->currency, $account, $charge, $engagement->sqid(), "release-review-{$item->id}") : null;
            $engagement->forceFill(['stripe_charge' => $charge, 'stripe_transfer' => $transfer])->save();
            $item->forceFill(['released_at' => now()])->save();

            return true;
        });
    }

    /**
     * A full refund of a review in one of `$allowed` states (null when it isn't in one), checked
     * under the lock; the copies of what was sent go with it.
     *
     * @param  list<string>  $allowed
     */
    private function refundInFull(Engagement $engagement, ?string $reason, array $allowed): ?Engagement
    {
        $refunded = DB::transaction(function () use ($engagement, $reason, $allowed): ?Engagement {
            $locked = Engagement::query()->whereKey($engagement->id)->lockForUpdate()->firstOrFail();

            if ($locked->kind !== Engagement::REVIEW || ! in_array($locked->status, $allowed, true)) {
                return null;
            }

            $left = $locked->price_cents - $locked->refunded_cents;

            if ($left > 0 && $locked->stripe_payment_intent !== null) {
                $this->stripe->refund($locked->stripe_payment_intent, $left, "refund-full-{$locked->id}-{$locked->refunded_cents}");
            }

            $locked->forceFill(['status' => Engagement::REFUNDED, 'refunded_cents' => $locked->price_cents, 'decline_reason' => $reason, 'ended_at' => now()])->save();
            ReviewItem::query()->where('engagement_id', $locked->id)->whereIn('status', [ReviewItem::REQUESTED, ReviewItem::ACCEPTED])->update(['status' => ReviewItem::CANCELLED, 'updated_at' => now()]);

            return $locked->setRelations($engagement->getRelations());
        });

        if ($refunded !== null) {
            Storage::disk('private')->delete(ReviewItem::query()->where('engagement_id', $refunded->id)->where('status', ReviewItem::CANCELLED)->get(['take_key', 'pitch_track_key'])->flatMap(fn (ReviewItem $r): array => array_values(array_filter([$r->take_key, $r->pitch_track_key])))->all());
        }

        return $refunded;
    }

    /**
     * What was paid for something that can't be had (cancelled first, or the coach gone): back in
     * full. A package's subscription stops now.
     */
    private function giveBack(Engagement $engagement): void
    {
        if ($engagement->stripe_subscription !== null) {
            $this->stripe->cancelPackageNow($engagement->stripe_subscription);
            $payment = $this->stripe->latestPackagePayment($engagement->stripe_subscription);
        } else {
            $payment = $engagement->stripe_payment_intent;
        }

        $left = $engagement->price_cents - $engagement->period_refunded_cents;

        if ($payment !== null && $left > 0) {
            $this->stripe->refund($payment, $left, "give-back-{$engagement->id}", reverseTransfer: in_array($engagement->kind, [Engagement::PACK, Engagement::PACKAGE], true));
        }

        $engagement->forceFill(['refunded_cents' => $engagement->price_cents])->save();
    }

    /**
     * A Checkout left a day: completed if Stripe says it was paid after all (a webhook lost), else
     * closed and forgotten.
     */
    private function letGo(Engagement $stale): bool
    {
        if ($stale->stripe_checkout_id !== null) {
            $result = $this->stripe->checkoutResult($stale->stripe_checkout_id);

            if (in_array($result['paymentStatus'], ['paid', 'no_payment_required'], true)) {
                $this->checkoutCompleted($stale->stripe_checkout_id);

                return false;
            }

            if ($result['status'] === 'open') {
                $this->stripe->expireCheckout($stale->stripe_checkout_id);
            }
        }

        Storage::disk('private')->delete($stale->reviews->flatMap(fn (ReviewItem $r): array => array_values(array_filter([$r->take_key, $r->pitch_track_key])))->all());
        $stale->delete();

        return true;
    }

    private function endPackage(Engagement $engagement): Engagement
    {
        if ($engagement->stripe_subscription !== null) {
            $this->stripe->cancelAtPeriodEnd($engagement->stripe_subscription);
        }

        $engagement->forceFill(['status' => Engagement::ENDING])->save();

        return $engagement;
    }

    /**
     * For admin listings: an engagement by its Sqid.
     */
    public static function find(string $sqid): Engagement
    {
        return Engagement::query()->with(['singer', 'coach'])->whereKey(Ids::decode($sqid) ?? 0)->first() ?? throw new Refusal(404, 'not_found');
    }
}
