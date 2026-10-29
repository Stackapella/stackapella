<?php

declare(strict_types=1);

namespace App\Support\Coaching;

use App\Actions\Coaching\CoachSetup;
use App\Actions\Coaching\Lessons;
use App\Models\CoachMessage;
use App\Models\CoachOffering;
use App\Models\CoachProfile;
use App\Models\CoachRating;
use App\Models\Engagement;
use App\Models\ExercisePack;
use App\Models\LessonBooking;
use App\Models\ReviewItem;
use App\Models\ReviewMedia;
use App\Models\User;
use App\Support\Http\Hosts;
use Illuminate\Support\Facades\Storage;

/**
 * Coaching as the API shows it (contract/schemas: engagement, review, message, rating, offering,
 * pack, coach.profile, coaches.show). Each side sees only its own: the coach never sees the money
 * (their share is in their Stripe dashboard), and the singer sees a review once it's delivered.
 */
final class CoachingView
{
    /**
     * @return array<string, mixed>
     */
    public static function engagement(Engagement $engagement, ?User $viewer): array
    {
        // No viewer is Hillel (the admin), who sees it as the singer does, money and all.
        $singer = $viewer === null || ($engagement->singer_id !== null && $engagement->singer_id === $viewer->id);
        $lesson = $engagement->kind === Engagement::LESSON ? ($engagement->relationLoaded('lesson') ? $engagement->lesson : $engagement->lesson()->first()) : null;

        return [
            'id' => $engagement->sqid(),
            'kind' => $engagement->kind,
            'status' => $engagement->status,
            'role' => $singer ? 'singer' : 'coach',
            'singer' => self::person($engagement->singer),
            'coach' => self::person($engagement->coach),
            'offering' => $engagement->offering_id !== null && $engagement->relationLoaded('offering') && $engagement->offering !== null ? ['id' => $engagement->offering->sqid(), 'title' => $engagement->offering->title] : null,
            'money' => $singer && $engagement->kind !== Engagement::TEACHER ? ['priceCents' => (int) $engagement->price_cents, 'currency' => $engagement->currency ?? 'usd', 'feeCents' => (int) $engagement->fee_cents, 'refundedCents' => (int) $engagement->refunded_cents] : null,
            'acceptBy' => $engagement->accept_by?->utc()->toIso8601ZuluString(),
            'dueAt' => $engagement->due_at?->utc()->toIso8601ZuluString(),
            'periodEndsAt' => $engagement->period_ends_at?->utc()->toIso8601ZuluString(),
            'reviewsLeft' => $engagement->reviews_left,
            'declineReason' => $engagement->decline_reason,
            'can' => self::can($engagement, $singer, $lesson),
            'rated' => $engagement->relationLoaded('rating') ? $engagement->rating !== null : false,
            'lesson' => $lesson !== null ? self::lesson($lesson) : null,
            'createdAt' => $engagement->created_at?->utc()->toIso8601ZuluString(),
        ];
    }

    /**
     * A lesson as Event Schedule has it: the join, calendar and manage links come once it's booked.
     *
     * @return array<string, mixed>
     */
    public static function lesson(LessonBooking $lesson): array
    {
        return [
            'startsAt' => $lesson->starts_at->utc()->toIso8601ZuluString(),
            'endsAt' => $lesson->ends_at->utc()->toIso8601ZuluString(),
            'timezone' => $lesson->timezone,
            'status' => $lesson->status,
            'joinUrl' => $lesson->location_url,
            'manageUrl' => $lesson->manage_url,
            'icalUrl' => $lesson->ical_url,
            'changedBy' => $lesson->changed_by,
            'refundUntil' => Lessons::refundUntil($lesson)->utc()->toIso8601ZuluString(),
        ];
    }

    /**
     * Someone on an engagement, or a deleted account (the engagement's record stays).
     *
     * @return array{id: string, name: string}
     */
    public static function person(?User $user): array
    {
        return $user !== null ? ['id' => $user->sqid(), 'name' => $user->name] : ['id' => '', 'name' => __('coaching.deleted_account')];
    }

    /**
     * @return list<string>
     */
    public static function can(Engagement $engagement, bool $singer, ?LessonBooking $lesson = null): array
    {
        if ($engagement->kind === Engagement::LESSON) {
            return self::canLesson($engagement, $singer, $lesson);
        }

        $live = in_array($engagement->status, [Engagement::REQUESTED, Engagement::ACCEPTED, Engagement::DELIVERED, Engagement::ACTIVE, Engagement::ENDING], true);
        $can = [];

        if ($singer) {
            if ($engagement->status === Engagement::PENDING_PAYMENT || ($engagement->kind === Engagement::REVIEW && $engagement->status === Engagement::REQUESTED) || ($engagement->kind === Engagement::PACKAGE && $engagement->status === Engagement::ACTIVE)) {
                $can[] = 'cancel';
            }
            if (in_array($engagement->kind, [Engagement::PACKAGE, Engagement::TEACHER], true) && in_array($engagement->status, [Engagement::ACTIVE, Engagement::ENDING], true)) {
                $can[] = 'review';
            }
            if (($engagement->kind === Engagement::REVIEW && $engagement->status === Engagement::DELIVERED) || ($engagement->kind === Engagement::PACKAGE && $engagement->created_at?->lte(now()->subMonth()))) {
                $can[] = 'rate';
            }
        } else {
            if ($engagement->status === Engagement::REQUESTED) {
                array_push($can, 'accept', 'decline');
            }
            if (in_array($engagement->status, [Engagement::ACCEPTED, Engagement::ACTIVE, Engagement::ENDING], true)) {
                $can[] = 'deliver';
            }
            if ($engagement->kind === Engagement::TEACHER && $engagement->status === Engagement::ACTIVE) {
                $can[] = 'cancel';
            }
        }

        if ($live && $engagement->kind !== Engagement::PACK) {
            $can[] = 'message';
        }

        if ($engagement->kind !== Engagement::TEACHER) {
            $can[] = 'report';
        }

        return $can;
    }

    /**
     * A lesson: either side cancels before it starts; the singer sends a take before it and rates
     * it after; the coach reviews what was sent; messages close 30 days after it.
     *
     * @return list<string>
     */
    private static function canLesson(Engagement $engagement, bool $singer, ?LessonBooking $lesson): array
    {
        $upcoming = in_array($engagement->status, [Engagement::REQUESTED, Engagement::BOOKED], true) && $lesson !== null && $lesson->starts_at->isFuture();
        $can = $upcoming ? ['cancel'] : [];

        if ($singer && $upcoming) {
            $can[] = 'review';
        }

        if ($singer && $engagement->status === Engagement::DELIVERED) {
            $can[] = 'rate';
        }

        if (! $singer && in_array($engagement->status, [Engagement::BOOKED, Engagement::DELIVERED], true)) {
            $can[] = 'deliver';
        }

        if (in_array($engagement->status, [Engagement::BOOKING, Engagement::REQUESTED, Engagement::BOOKED], true) || ($engagement->status === Engagement::DELIVERED && $engagement->ended_at?->gt(now()->subDays(30)))) {
            $can[] = 'message';
        }

        $can[] = 'report';

        return $can;
    }

    /**
     * @return array<string, mixed>
     */
    public static function review(ReviewItem $review, ?User $viewer): array
    {
        $coach = $viewer !== null && $review->engagement->coach_id === $viewer->id;
        $private = Storage::disk('private');
        $until = now()->addHour();

        return [
            'id' => $review->sqid(),
            'engagementId' => $review->engagement->sqid(),
            'status' => $review->status,
            'take' => [
                'url' => $review->status !== ReviewItem::CANCELLED ? $private->temporaryUrl($review->take_key, $until) : null,
                'sampleRate' => $review->sample_rate,
                'frames' => $review->frames,
                'template' => $review->template_slug !== null ? ['id' => $review->template_slug, 'version' => (int) $review->template_version, 'partKey' => $review->part_key] : null,
            ],
            // A link to it, as sent (up to 1 MB each: a review list never reads them all).
            'pitchTrackUrl' => $review->pitch_track_key !== null ? $private->temporaryUrl($review->pitch_track_key, $until) : null,
            'scores' => $review->scores,
            'question' => $review->question,
            // The coach works on the draft; the singer sees only what was delivered.
            'review' => $coach ? ($review->draft ?? ($review->status === ReviewItem::ACCEPTED ? ['comments' => [], 'summary' => null, 'exercises' => [], 'demoMediaId' => null] : $review->delivered)) : $review->delivered,
            'media' => $review->media->filter(fn (ReviewMedia $media): bool => $coach || $review->status === ReviewItem::DELIVERED)->map(fn (ReviewMedia $media): array => self::media($media))->values()->all(),
            'deliveredAt' => $review->delivered_at?->utc()->toIso8601ZuluString(),
            'unsendUntil' => $review->delivered_at?->copy()->addMinutes((int) config('coaching.unsend_minutes'))->utc()->toIso8601ZuluString(),
            'followUp' => $review->follow_up_question !== null ? [
                'question' => $review->follow_up_question,
                'askedAt' => $review->follow_up_at?->utc()->toIso8601ZuluString(),
                'answer' => $review->follow_up_answer,
                'answerMediaId' => $review->follow_up_media,
                'answeredAt' => $review->follow_up_answered_at?->utc()->toIso8601ZuluString(),
            ] : null,
            'createdAt' => $review->created_at?->utc()->toIso8601ZuluString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function media(ReviewMedia $media): array
    {
        return [
            'id' => $media->uuid,
            'kind' => $media->kind,
            'status' => $media->status,
            'url' => $media->status === ReviewMedia::READY && $media->file_key !== null ? Storage::disk('private')->temporaryUrl($media->file_key, now()->addHour()) : null,
            'sampleRate' => $media->sample_rate,
            'frames' => $media->frames,
            'createdAt' => $media->created_at?->utc()->toIso8601ZuluString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function message(CoachMessage $message): array
    {
        return [
            'id' => $message->sqid(),
            'from' => self::person($message->sender),
            'text' => $message->body,
            'mediaUrl' => $message->media !== null && $message->media->file_key !== null ? Storage::disk('private')->temporaryUrl($message->media->file_key, now()->addHour()) : null,
            'warning' => $message->warning,
            'createdAt' => $message->created_at?->utc()->toIso8601ZuluString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function rating(CoachRating $rating): array
    {
        // The singer's first name and initial: a public profile shows it.
        $parts = preg_split('/\s+/', trim($rating->singer->name ?? __('coaching.deleted_account'))) ?: [''];
        $name = $parts[0].(isset($parts[1]) ? ' '.mb_substr($parts[1], 0, 1).'.' : '');

        return [
            'id' => $rating->sqid(),
            'stars' => $rating->stars,
            'text' => $rating->text,
            'reply' => $rating->reply,
            'singer' => ['name' => $name],
            'createdAt' => $rating->created_at?->utc()->toIso8601ZuluString(),
            'editableUntil' => $rating->created_at?->copy()->addDays((int) config('coaching.rating_edit_days'))->utc()->toIso8601ZuluString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function offering(CoachOffering $offering): array
    {
        return [
            'id' => $offering->sqid(),
            'kind' => $offering->kind,
            'title' => $offering->title,
            'description' => $offering->description,
            'priceCents' => $offering->price_cents,
            'currency' => $offering->currency,
            'turnaroundDays' => $offering->kind === CoachOffering::LESSON ? null : $offering->turnaround_days,
            'maxSeconds' => $offering->max_seconds,
            'reviewsPerMonth' => $offering->reviews_per_month,
            'archived' => $offering->archived_at !== null,
            'appointmentTypeId' => $offering->es_appointment_type_id,
            'durationMinutes' => $offering->duration_minutes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function pack(ExercisePack $pack, bool $withExercises = true): array
    {
        return [
            'id' => $pack->uuid,
            'title' => $pack->title,
            'description' => $pack->description,
            'priceCents' => $pack->price_cents,
            'currency' => $pack->currency,
            // Buyers get what Hillel approved; the coach sees their draft.
            'exercises' => $withExercises ? $pack->exercises : [],
            'status' => $pack->status,
            'note' => $pack->note,
            'coach' => self::person($pack->coach),
            'updatedAt' => $pack->updated_at?->utc()->toIso8601ZuluString(),
        ];
    }

    /**
     * A pack as buyers see it: what Hillel approved, never a change waiting for review.
     *
     * @return array<string, mixed>
     */
    public static function approvedPack(ExercisePack $pack, bool $withExercises = true): array
    {
        return [...self::pack($pack, $withExercises), 'title' => $pack->approved_title ?? $pack->title, 'description' => $pack->approved_description ?? '', 'priceCents' => $pack->approved_price_cents ?? $pack->price_cents, 'currency' => $pack->approved_currency ?? $pack->currency, 'exercises' => $withExercises ? ($pack->approved_exercises ?? []) : [], 'status' => ExercisePack::APPROVED, 'note' => null];
    }

    /**
     * @return array<string, mixed>
     */
    public static function profile(CoachProfile $profile): array
    {
        $user = $profile->user;

        return [
            'handle' => (string) $user->handle,
            'name' => $user->name,
            'avatarUrl' => $user->avatar_key !== null ? Storage::disk('media')->url($user->avatar_key) : null,
            'status' => $profile->status,
            'bio' => $profile->bio,
            'voiceTypes' => $profile->voice_types,
            'styles' => $profile->styles,
            'languages' => $profile->languages,
            'timezone' => $profile->timezone,
            'cancellationHours' => $profile->cancellation_hours,
            'canSell' => CoachSetup::canSell($profile),
            'chargesEnabled' => $profile->charges_enabled,
            'rating' => self::ratingSummary($profile),
            'profileUrl' => Hosts::publicUrl().'/coach/'.$user->handle,
        ];
    }

    /**
     * @return array{average: float|null, count: int}
     */
    public static function ratingSummary(CoachProfile $profile): array
    {
        $shown = $profile->rating_count >= (int) config('coaching.ratings_to_show');

        return ['average' => $shown && $profile->rating_average !== null ? round((float) $profile->rating_average, 2) : null, 'count' => $profile->rating_count];
    }
}
