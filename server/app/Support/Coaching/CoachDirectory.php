<?php

declare(strict_types=1);

namespace App\Support\Coaching;

use App\Actions\Coaching\Lessons;
use App\Exceptions\Refusal;
use App\Models\CoachOffering;
use App\Models\CoachProfile;
use App\Models\CoachRating;
use App\Models\ExercisePack;
use App\Models\InvoiceNinjaConnection;

/**
 * Coaches as anyone sees them, by their own link (coaches.show and /coach/<handle>). The directory
 * and search come with the network features (roadmap N4).
 */
final class CoachDirectory
{
    /**
     * An approved coach's profile for anyone; anyone else (paused, suspended, not a coach) is not
     * found.
     *
     * @return array<string, mixed>
     */
    public static function publicCoach(string $handle): array
    {
        $profile = CoachProfile::query()->with('user')->where('status', CoachProfile::APPROVED)->whereHas('user', fn ($query) => $query->where('handle', $handle)->whereNull('suspended_at')->where('hide_profile', false))->first()
            ?? throw new Refusal(404, 'not_found');
        $summary = CoachingView::ratingSummary($profile);
        $shown = $summary['average'] !== null;

        return [
            'handle' => (string) $profile->user->handle,
            'name' => $profile->user->name,
            'avatarUrl' => CoachingView::profile($profile)['avatarUrl'],
            'bio' => $profile->bio,
            'voiceTypes' => $profile->voice_types,
            'styles' => $profile->styles,
            'languages' => $profile->languages,
            'timezone' => $profile->timezone,
            'newCoach' => ! $shown,
            // "Your coach keeps their records in Invoice Ninja", at checkout.
            'keepsBooks' => InvoiceNinjaConnection::query()->where('owner_key', 'user:'.$profile->user_id)->exists(),
            'rating' => $summary,
            // Lessons only while they're on sale (the coach's Event Schedule connection works).
            'offerings' => CoachOffering::query()->where('coach_id', $profile->user_id)->whereNull('archived_at')->orderBy('id')->get()
                ->filter(fn (CoachOffering $o): bool => $o->kind !== CoachOffering::LESSON || Lessons::connectionFor($o) !== null)
                ->map(fn (CoachOffering $o): array => CoachingView::offering($o))->values()->all(),
            'packs' => ExercisePack::query()->with('coach')->where('coach_id', $profile->user_id)->whereNotNull('approved_exercises')->whereNotNull('approved_price_cents')->orderBy('id')->get()->map(fn (ExercisePack $p): array => CoachingView::approvedPack($p, withExercises: false))->all(),
            'ratings' => $shown ? CoachRating::query()->with('singer')->where('coach_id', $profile->user_id)->whereNull('removed_at')->latest('id')->limit(20)->get()->map(fn (CoachRating $r): array => CoachingView::rating($r))->all() : [],
        ];
    }
}
