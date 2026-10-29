<?php

declare(strict_types=1);

namespace App\Actions\Coaching;

use App\Actions\Notices\Notices;
use App\Exceptions\Refusal;
use App\Models\AuditLog;
use App\Models\CoachOffering;
use App\Models\CoachProfile;
use App\Models\Engagement;
use App\Models\EsConnection;
use App\Models\ExercisePack;
use App\Models\Notice;
use App\Models\User;
use App\Services\Coaching\CoachingGateway;
use App\Services\EventSchedule\EventSchedule;
use App\Support\Ids;
use App\Support\Usage\Plans;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A coach's own setup (docs/coaching-marketplace-plan.md F1, F2): the profile, Stripe Express
 * onboarding, what they sell, and their exercise packs; and Hillel's pause, suspend and pack review.
 */
final class CoachSetup
{
    public function __construct(private readonly CoachingGateway $stripe, private readonly Engagements $engagements, private readonly Plans $plans, private readonly EventSchedule $es) {}

    /**
     * A coach's free Plus starting or ending moves which of their projects are read-only, as any
     * other start or end of Plus does: settled, and every device hears.
     */
    public function plusChanged(User $coach): void
    {
        $coach = User::query()->with('subscriptions')->findOrFail($coach->id);

        if (! $this->plans->isPlus($coach)) {
            $this->plans->settleEditable($coach);
        }

        $this->plans->readOnlyChanged($coach);
    }

    /**
     * The person's coach profile; anyone who isn't a coach finds nothing.
     */
    public function profile(User $user): CoachProfile
    {
        return CoachProfile::query()->with('user')->where('user_id', $user->id)->first() ?? throw new Refusal(404, 'not_found');
    }

    /**
     * Whether people can buy from this coach now.
     */
    public static function canSell(CoachProfile $profile): bool
    {
        return $profile->status === CoachProfile::APPROVED && $profile->charges_enabled;
    }

    /**
     * @param  array{bio?: string, voiceTypes?: list<string>, styles?: list<string>, languages?: list<string>, timezone?: string, cancellationHours?: int}  $data
     */
    public function updateProfile(User $user, array $data): CoachProfile
    {
        $profile = $this->profile($user);
        $profile->fill(array_filter([
            'bio' => $data['bio'] ?? null,
            'voice_types' => $data['voiceTypes'] ?? null,
            'styles' => $data['styles'] ?? null,
            'languages' => $data['languages'] ?? null,
            'timezone' => $data['timezone'] ?? null,
            'cancellation_hours' => $data['cancellationHours'] ?? null,
        ], static fn (mixed $value): bool => $value !== null))->save();

        return $profile;
    }

    /**
     * Start or continue Stripe Express onboarding.
     */
    public function onboarding(User $user, ?string $country): string
    {
        $profile = $this->profile($user);

        if (! $this->stripe->ready()) {
            throw new Refusal(423, 'paused');
        }

        if ($profile->stripe_account_id === null) {
            $profile->forceFill(['stripe_account_id' => $this->stripe->createAccount($user, strtoupper($country ?? 'US'))])->save();
        }

        $base = rtrim((string) config('app.url'), '/');

        return $this->stripe->onboardingUrl((string) $profile->stripe_account_id, $base.'/coaching/connected?again=1', $base.'/coaching/connected');
    }

    /**
     * Where onboarding stands, asked of Stripe again (and kept, as Stripe's webhook does).
     *
     * @return array{accountExists: bool, chargesEnabled: bool, payoutsEnabled: bool, detailsSubmitted: bool, dashboardUrl: string|null}
     */
    public function connectStatus(User $user): array
    {
        $profile = $this->profile($user);

        if ($profile->stripe_account_id === null || ! $this->stripe->ready()) {
            return ['accountExists' => $profile->stripe_account_id !== null, 'chargesEnabled' => $profile->charges_enabled, 'payoutsEnabled' => $profile->payouts_enabled, 'detailsSubmitted' => $profile->details_submitted, 'dashboardUrl' => null];
        }

        $this->accountUpdated($profile->stripe_account_id, $this->stripe->account($profile->stripe_account_id));
        $profile->refresh();

        return [
            'accountExists' => true,
            'chargesEnabled' => $profile->charges_enabled,
            'payoutsEnabled' => $profile->payouts_enabled,
            'detailsSubmitted' => $profile->details_submitted,
            'dashboardUrl' => $profile->details_submitted ? $this->stripe->dashboardUrl((string) $profile->stripe_account_id) : null,
        ];
    }

    /**
     * Stripe's account.updated (the Connect webhook), or a fresh look.
     *
     * @param  array{chargesEnabled: bool, payoutsEnabled: bool, detailsSubmitted: bool}  $state
     */
    public function accountUpdated(string $accountId, array $state): void
    {
        CoachProfile::query()->where('stripe_account_id', $accountId)->update([
            'charges_enabled' => $state['chargesEnabled'],
            'payouts_enabled' => $state['payoutsEnabled'],
            'details_submitted' => $state['detailsSubmitted'],
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array{kind?: string, title?: string, description?: string, priceCents?: int, currency?: string, turnaroundDays?: int, maxSeconds?: int|null, reviewsPerMonth?: int|null, archived?: bool, appointmentTypeId?: string}  $data
     */
    public function saveOffering(User $user, ?string $sqid, array $data): CoachOffering
    {
        $this->profile($user);
        $offering = $sqid === null ? new CoachOffering(['kind' => $data['kind'] ?? CoachOffering::REVIEW]) : (CoachOffering::query()->whereSqid($sqid)->where('coach_id', $user->id)->first() ?? throw new Refusal(404, 'not_found'));

        $offering->fill(array_filter([
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'price_cents' => $data['priceCents'] ?? null,
            'currency' => $data['currency'] ?? null,
            'turnaround_days' => $data['turnaroundDays'] ?? null,
            'max_seconds' => $data['maxSeconds'] ?? null,
            'reviews_per_month' => $offering->kind === CoachOffering::PACKAGE ? ($data['reviewsPerMonth'] ?? null) : null,
        ], static fn (mixed $value): bool => $value !== null));

        // A lesson is one of the coach's ES appointment types, checked with ES; its length is ES's.
        if ($offering->kind === CoachOffering::LESSON && ($offering->es_appointment_type_id === null || isset($data['appointmentTypeId']))) {
            $connection = EsConnection::query()->where('user_id', $user->id)->where('status', '!=', EsConnection::DISCONNECTED)->first();

            if ($connection === null || $connection->schedule === null) {
                throw new Refusal(409, 'event_schedule_required');
            }

            if (! $connection->usable()) {
                throw new Refusal(409, 'reconnect_required');
            }

            $type = collect($this->es->appointmentTypes($connection, $connection->schedule))->firstWhere('id', (string) ($data['appointmentTypeId'] ?? $offering->es_appointment_type_id));

            if ($type === null) {
                throw ValidationException::withMessages(['appointmentTypeId' => __('validation.in', ['attribute' => 'appointment type'])]);
            }

            $offering->forceFill(['es_appointment_type_id' => $type['id'], 'es_schedule' => $connection->schedule, 'duration_minutes' => $type['durationMinutes'], 'turnaround_days' => 0, 'max_seconds' => null]);
        }

        if (array_key_exists('archived', $data)) {
            $offering->forceFill(['archived_at' => $data['archived'] ? ($offering->archived_at ?? now()) : null]);
        }

        $offering->forceFill(['coach_id' => $user->id])->save();

        return $offering;
    }

    /**
     * @param  array{title: string, description: string, priceCents: int, currency: string, exercises: list<array<string, mixed>>}  $data
     */
    public function savePack(User $user, string $uuid, array $data): ExercisePack
    {
        $this->profile($user);
        $pack = ExercisePack::query()->where('uuid', $uuid)->first();

        if ($pack !== null && $pack->coach_id !== $user->id) {
            throw new Refusal(404, 'not_found');
        }

        $pack ??= (new ExercisePack)->forceFill(['uuid' => $uuid, 'coach_id' => $user->id]);
        $pack->fill(['title' => $data['title'], 'description' => $data['description'], 'price_cents' => $data['priceCents'], 'currency' => $data['currency'], 'exercises' => $data['exercises']]);
        // Any change goes back to a draft: it's reviewed again before anyone can buy it.
        $pack->forceFill(['status' => ExercisePack::DRAFT])->save();

        return $pack;
    }

    public function submitPack(User $user, string $uuid): ExercisePack
    {
        $pack = ExercisePack::query()->where('uuid', $uuid)->where('coach_id', $user->id)->first() ?? throw new Refusal(404, 'not_found');

        if (in_array($pack->status, [ExercisePack::DRAFT, ExercisePack::REJECTED], true)) {
            $pack->forceFill(['status' => ExercisePack::SUBMITTED])->save();
        }

        return $pack;
    }

    public function decidePack(ExercisePack $pack, User $admin, bool $approve, ?string $note, ?string $ip): ExercisePack
    {
        $pack->forceFill(['status' => $approve ? ExercisePack::APPROVED : ExercisePack::REJECTED, 'note' => $note, 'reviewed_by' => $admin->id, 'reviewed_at' => now()]);

        // What buyers get is what Hillel saw: a later change waits for its own review.
        if ($approve) {
            $pack->forceFill(['approved_title' => $pack->title, 'approved_exercises' => $pack->exercises, 'approved_description' => $pack->description, 'approved_price_cents' => $pack->price_cents, 'approved_currency' => $pack->currency]);
        }

        $pack->save();
        AuditLog::record($approve ? 'coaching.pack.approved' : 'coaching.pack.rejected', $pack->coach, $admin, reason: $note, ip: $ip);

        return $pack;
    }

    /**
     * Hillel pauses, suspends or reactivates a coach. Suspending refunds their open reviews and
     * ends their packages; paused or suspended, they sell nothing, and lose the free Plus.
     */
    public function setStatus(string $coachSqid, User $admin, string $status, string $note, ?string $ip): CoachProfile
    {
        $profile = CoachProfile::query()->with('user')->where('user_id', Ids::decode($coachSqid) ?? 0)->first() ?? throw new Refusal(404, 'not_found');

        DB::transaction(function () use ($profile, $admin, $status, $note, $ip): void {
            $before = $profile->status;
            $profile->forceFill(['status' => $status])->save();
            $profile->user->forceFill(['coach_active_at' => $status === CoachProfile::APPROVED ? ($profile->user->coach_active_at ?? now()) : null])->save();
            $this->plusChanged($profile->user);
            AuditLog::record('coaching.coach.'.$status, $profile->user, $admin, before: ['status' => $before], after: ['status' => $status], reason: $note, ip: $ip);

            if ($before !== $status && in_array($status, [CoachProfile::PAUSED, CoachProfile::SUSPENDED], true)) {
                app(Notices::class)->give($profile->user, $status === CoachProfile::PAUSED ? Notice::COACH_PAUSED : Notice::COACH_SUSPENDED, $profile, 'coach_agreement', $note);
            }
        });

        if ($status === CoachProfile::SUSPENDED) {
            foreach (Engagement::query()->where('coach_id', $profile->user_id)->whereIn('status', [Engagement::REQUESTED, Engagement::ACCEPTED, Engagement::ACTIVE, Engagement::BOOKING, Engagement::BOOKED])->get() as $engagement) {
                $this->engagements->endForCoach($engagement);
            }
        }

        return $profile->refresh();
    }
}
