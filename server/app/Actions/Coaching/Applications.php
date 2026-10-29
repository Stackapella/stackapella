<?php

declare(strict_types=1);

namespace App\Actions\Coaching;

use App\Exceptions\Refusal;
use App\Models\AuditLog;
use App\Models\CoachApplication;
use App\Models\CoachProfile;
use App\Models\User;
use App\Support\Accounts\Age;
use App\Support\Flags;
use Illuminate\Support\Facades\DB;

/**
 * Becoming a coach (docs/coaching-marketplace-plan.md F1): an application with a sample review,
 * then Hillel's decision. Approving makes the coach's profile; they can sell once Stripe says
 * their account can take charges.
 */
final class Applications
{
    /**
     * @param  array{experience: string, training: string, voiceTypes: list<string>, styles: list<string>, languages: list<string>, timezone: string, introUrl?: string|null, sampleReview: array<string, mixed>}  $data
     */
    public function apply(User $user, array $data): CoachApplication
    {
        if (! app(Flags::class)->on('coach_signup')) {
            throw new Refusal(409, 'coach_signup_closed');
        }

        // Coaching is 18 and over: a birth date that says younger isn't overruled by the form.
        Age::ensureMayClaimAdult($user);

        return DB::transaction(function () use ($user, $data): CoachApplication {
            User::query()->whereKey($user->id)->lockForUpdate()->first(['id']);

            if ($user->handle === null) {
                throw new Refusal(409, 'handle_required');
            }

            $last = CoachApplication::query()->where('user_id', $user->id)->latest('id')->first();

            if ($last !== null && ($last->status !== CoachApplication::DECLINED || $last->decided_at?->gt(now()->subDays((int) config('coaching.reapply_days'))))) {
                throw new Refusal(409, 'application_open');
            }

            $application = new CoachApplication([
                'answers' => ['experience' => $data['experience'], 'training' => $data['training'], 'voiceTypes' => $data['voiceTypes'], 'styles' => $data['styles'], 'languages' => $data['languages'], 'timezone' => $data['timezone'], 'introUrl' => $data['introUrl'] ?? null],
                'sample_review' => Reviews::clean($data['sampleReview']),
                'agreement_version' => (int) config('coaching.agreement_version'),
            ]);
            $application->forceFill(['user_id' => $user->id, 'status' => CoachApplication::SUBMITTED])->save();
            // Again under the lock: a birth date given meanwhile still wins.
            Age::ensureMayClaimAdult($user->fresh() ?? $user);
            $user->forceFill(['adult_confirmed_at' => $user->adult_confirmed_at ?? now()])->save();

            return $application;
        });
    }

    public function decide(CoachApplication $application, User $admin, bool $approve, ?string $note, ?string $ip): CoachApplication
    {
        return DB::transaction(function () use ($application, $admin, $approve, $note, $ip): CoachApplication {
            $locked = CoachApplication::query()->with('user')->whereKey($application->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== CoachApplication::SUBMITTED) {
                throw new Refusal(409, 'engagement_state');
            }

            $locked->forceFill(['status' => $approve ? CoachApplication::APPROVED : CoachApplication::DECLINED, 'decided_by' => $admin->id, 'decided_at' => now(), 'note' => $note])->save();

            if ($approve) {
                $answers = $locked->answers;
                $profile = CoachProfile::query()->where('user_id', $locked->user_id)->first() ?? new CoachProfile;
                $profile->fill([
                    'bio' => $profile->exists ? $profile->bio : (string) ($answers['experience'] ?? ''),
                    'voice_types' => $answers['voiceTypes'] ?? [],
                    'styles' => $answers['styles'] ?? [],
                    'languages' => $answers['languages'] ?? [],
                    'timezone' => (string) ($answers['timezone'] ?? 'UTC'),
                ]);
                $profile->forceFill(['user_id' => $locked->user_id, 'status' => CoachProfile::APPROVED, 'approved_at' => now()])->save();
                $locked->user->forceFill(['coach_active_at' => now()])->save();
                app(CoachSetup::class)->plusChanged($locked->user);
            }

            AuditLog::record($approve ? 'coaching.application.approved' : 'coaching.application.declined', $locked->user, $admin, reason: $note, ip: $ip);

            return $locked;
        });
    }
}
