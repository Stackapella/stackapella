<?php

declare(strict_types=1);

namespace App\Actions\Coaching;

use App\Exceptions\Refusal;
use App\Models\CoachMessage;
use App\Models\CoachProfile;
use App\Models\CoachRating;
use App\Models\Engagement;
use App\Models\EngagementReport;
use App\Models\ReviewMedia;
use App\Models\TeacherLink;
use App\Models\User;
use App\Support\Accounts\Age;
use App\Support\Trust\Limits;
use App\Support\Usage\Plans;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Between a singer and their coach (docs/coaching-marketplace-plan.md F5, F6): the messages
 * thread, ratings and the coach's reply, and Send to my teacher's link.
 */
final class Conversations
{
    /** Ways of being paid outside the app, which the singer is warned about. */
    private const OFF_PLATFORM = ['paypal', 'venmo', 'cash app', 'cashapp', 'zelle', 'revolut', 'wise.com', 'bank transfer', 'iban', 'sort code', 'western union', 'pay me directly', 'pay outside'];

    public function __construct(private readonly Engagements $engagements, private readonly Plans $plans, private readonly Limits $limits) {}

    public function message(User $user, string $engagementSqid, ?string $text, ?string $mediaId): CoachMessage
    {
        $engagement = $this->engagements->mine($user, $engagementSqid, 'either');

        // A pack has no thread; a finished review's closes 30 days after it's delivered.
        $closed = $engagement->kind === Engagement::PACK
            || in_array($engagement->status, [Engagement::PENDING_PAYMENT, Engagement::REFUNDED, Engagement::CANCELLED, Engagement::DECLINED, Engagement::ENDED], true)
            || ($engagement->ended_at !== null && $engagement->ended_at->lt(now()->subDays(30)));

        if ($closed) {
            throw new Refusal(409, 'engagement_state');
        }

        // 30 an hour in each thread, for each side (contract/api.md, messages.create).
        $key = "coach-messages:{$engagement->id}:{$user->id}";
        $max = $this->limits->max('coach-messages', 'account');

        if (RateLimiter::tooManyAttempts($key, $max)) {
            $wait = max(1, RateLimiter::availableIn($key));

            throw new Refusal(429, 'rate_limited', ['retryAfter' => $wait], ['Retry-After' => (string) $wait]);
        }

        RateLimiter::hit($key, 3600);

        $media = $mediaId !== null ? ReviewMedia::query()->where('uuid', $mediaId)->where('user_id', $user->id)->whereHas('review', fn ($query) => $query->where('engagement_id', $engagement->id))->first() : null;
        $body = $text !== null ? trim($text) : null;
        $warning = $body !== null && self::offPlatform($body) ? 'off_platform' : null;

        return DB::transaction(function () use ($engagement, $user, $body, $media, $warning): CoachMessage {
            $message = new CoachMessage(['body' => $body, 'warning' => $warning]);
            $message->forceFill(['engagement_id' => $engagement->id, 'sender_id' => $user->id, 'media_id' => $media?->id])->save();

            // Flagged for Hillel, in the disputes queue.
            if ($warning !== null) {
                $report = new EngagementReport(['text' => __('coaching.off_platform_flag')]);
                $report->forceFill(['engagement_id' => $engagement->id, 'user_id' => $user->id])->save();
            }

            return $message->setRelation('sender', $user)->setRelation('media', $media);
        });
    }

    public static function offPlatform(string $text): bool
    {
        $lower = mb_strtolower($text);

        foreach (self::OFF_PLATFORM as $phrase) {
            if (str_contains($lower, $phrase)) {
                return true;
            }
        }

        // An email address or a phone number: allowed, but discouraged (F5).
        return preg_match('/[\w.+-]+@[\w-]+\.[\w.]+/u', $text) === 1 || preg_match('/(?:\d[\s.\-()]*){10,}/', $text) === 1;
    }

    public function rate(User $singer, string $engagementSqid, int $stars, ?string $text): CoachRating
    {
        return DB::transaction(function () use ($singer, $engagementSqid, $stars, $text): CoachRating {
            $engagement = $this->engagements->mine($singer, $engagementSqid, 'singer', lock: true);
            $finished = (in_array($engagement->kind, [Engagement::REVIEW, Engagement::LESSON], true) && $engagement->status === Engagement::DELIVERED)
                || ($engagement->kind === Engagement::PACKAGE && $engagement->created_at?->lte(now()->subMonth()) && $engagement->status !== Engagement::PENDING_PAYMENT);

            if (! $finished) {
                throw new Refusal(409, 'engagement_state');
            }

            $rating = CoachRating::query()->where('engagement_id', $engagement->id)->first();

            if ($rating !== null && $rating->created_at?->lt(now()->subDays((int) config('coaching.rating_edit_days')))) {
                throw new Refusal(409, 'engagement_state');
            }

            $rating ??= (new CoachRating)->forceFill(['engagement_id' => $engagement->id, 'singer_id' => $singer->id, 'coach_id' => $engagement->coach_id]);
            $rating->fill(['stars' => $stars, 'text' => $text !== null && trim($text) !== '' ? trim($text) : null])->save();
            self::recount((int) $engagement->coach_id);

            return $rating;
        });
    }

    public function reply(User $coach, string $sqid, string $text): CoachRating
    {
        $rating = CoachRating::query()->whereSqid($sqid)->where('coach_id', $coach->id)->whereNull('removed_at')->first() ?? throw new Refusal(404, 'not_found');

        if ($rating->reply !== null) {
            throw new Refusal(409, 'engagement_state');
        }

        $rating->forceFill(['reply' => trim($text), 'replied_at' => now()])->save();

        return $rating;
    }

    public static function recount(int $coachId): void
    {
        $ratings = CoachRating::query()->where('coach_id', $coachId)->whereNull('removed_at');
        CoachProfile::query()->where('user_id', $coachId)->update(['rating_count' => $ratings->count(), 'rating_average' => $ratings->avg('stars'), 'updated_at' => now()]);
    }

    /**
     * Send to my teacher (Plus): a link for the singer's own teacher.
     *
     * @return array{link: TeacherLink, url: string}
     */
    public function inviteTeacher(User $singer, string $name, bool $adult): array
    {
        if (! $this->plans->isPlus($singer)) {
            throw new Refusal(403, 'plus_required');
        }

        self::ensureAdult($singer, $adult);

        $token = Str::random(24);
        $link = new TeacherLink(['name' => trim($name)]);
        $link->forceFill(['singer_id' => $singer->id, 'token_hash' => hash('sha256', $token), 'status' => TeacherLink::PENDING])->save();

        return ['link' => $link, 'url' => rtrim((string) config('app.url'), '/').'/teacher/'.$token];
    }

    public function teacherLink(string $token): TeacherLink
    {
        $link = TeacherLink::query()->with('singer')->where('token_hash', hash('sha256', $token))->first() ?? throw new Refusal(404, 'not_found');

        if ($link->status !== TeacherLink::PENDING) {
            throw new Refusal(410, 'invite_used');
        }

        return $link;
    }

    public function acceptTeacher(User $teacher, string $token, bool $adult): Engagement
    {
        return DB::transaction(function () use ($teacher, $token, $adult): Engagement {
            self::ensureAdult($teacher, $adult);

            $link = TeacherLink::query()->where('token_hash', hash('sha256', $token))->lockForUpdate()->first() ?? throw new Refusal(404, 'not_found');

            if ($link->status !== TeacherLink::PENDING) {
                throw new Refusal(410, 'invite_used');
            }

            if ($link->singer_id === $teacher->id) {
                throw new Refusal(409, 'own_coaching');
            }

            $engagement = new Engagement(['kind' => Engagement::TEACHER, 'price_cents' => 0]);
            $engagement->forceFill(['singer_id' => $link->singer_id, 'coach_id' => $teacher->id, 'status' => Engagement::ACTIVE, 'fee_cents' => 0])->save();
            $link->forceFill(['teacher_id' => $teacher->id, 'engagement_id' => $engagement->id, 'status' => TeacherLink::ACTIVE])->save();

            return $engagement->load(['singer', 'coach']);
        });
    }

    /**
     * Coaching is 18 and over on both sides, Send to my teacher included: asked once, kept.
     */
    public static function ensureAdult(User $user, bool $adult): void
    {
        if ($user->adult_confirmed_at !== null) {
            return;
        }

        Age::ensureMayClaimAdult($user);

        if (! $adult) {
            throw new Refusal(403, 'adult_required');
        }

        $user->forceFill(['adult_confirmed_at' => now()])->save();
    }
}
