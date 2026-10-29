<?php

declare(strict_types=1);

namespace App\Actions\Gallery;

use App\Exceptions\Refusal;
use App\Models\Harmony;
use App\Models\HarmonyConsent;
use App\Models\User;
use App\Services\Social\SocialPoster;
use App\Support\Gallery\Bell;
use App\Support\Gallery\Gallery;
use App\Support\Sharing\HarmonyFiles;
use Illuminate\Support\Facades\DB;

/**
 * Friends' consent (N1; docs/backend-plan.md D6, "Everyone who sang on it agrees"): before a
 * harmony with their voice goes in the gallery or is featured, each friend who sang on it is asked
 * in the app, once, and answers yes or no. A no can be changed to a yes later, and a yes withdrawn
 * (which takes it out of the gallery and stops featuring).
 */
final class Consents
{
    public function ask(User $owner, Harmony $harmony): Harmony
    {
        if ($harmony->user_id !== $owner->id) {
            throw new Refusal(404, 'not_found');
        }

        foreach ($harmony->singer_ids ?? [] as $singerId) {
            $consent = HarmonyConsent::query()->firstOrCreate(['harmony_id' => $harmony->id, 'user_id' => $singerId], ['status' => HarmonyConsent::ASKED]);

            if ($consent->wasRecentlyCreated) {
                Bell::tell($singerId, 'consent_request', $owner, $harmony);
            }
        }

        return $harmony;
    }

    public function decide(User $user, HarmonyConsent $consent, bool $agree): HarmonyConsent
    {
        if ($consent->user_id !== $user->id) {
            throw new Refusal(404, 'not_found');
        }

        // Saying yes puts their voice and name in public: 18 or over only (a no is always open).
        if ($agree) {
            Gallery::ensureAdult($user);
        }

        $harmony = Harmony::query()->findOrFail($consent->harmony_id);

        DB::transaction(function () use ($consent, $agree, $harmony): void {
            $consent->forceFill(['status' => $agree ? HarmonyConsent::AGREED : HarmonyConsent::DECLINED, 'decided_at' => now()])->save();

            // A yes taken back: out of the gallery, and no more featuring.
            if (! $agree) {
                $harmony->forceFill(['gallery_opt_in_at' => null, 'may_feature_at' => null])->save();
                SocialPoster::withdraw($harmony);
            }
        });

        Bell::tell($harmony->user_id, 'consent_answer', $user, $harmony);

        if (! $agree) {
            app(HarmonyFiles::class)->dropGuidesUnlessPublic($harmony);
        }

        return $consent->refresh();
    }

    /**
     * Every friend on it has said yes (or there are none).
     */
    public static function allAgreed(Harmony $harmony): bool
    {
        $singers = $harmony->singer_ids ?? [];

        return $singers === [] || HarmonyConsent::query()->where('harmony_id', $harmony->id)->whereIn('user_id', $singers)->where('status', HarmonyConsent::AGREED)->count() === count($singers);
    }

    /**
     * @return list<array{name: string, consent: string|null}>
     */
    public static function singers(Harmony $harmony): array
    {
        $singers = $harmony->singer_ids ?? [];

        if ($singers === []) {
            return [];
        }

        $answers = HarmonyConsent::query()->where('harmony_id', $harmony->id)->pluck('status', 'user_id');

        return array_values(User::query()->whereKey($singers)->orderBy('id')->get(['id', 'name'])->map(fn (User $u): array => ['name' => $u->name, 'consent' => $answers->get($u->id)])->all());
    }
}
