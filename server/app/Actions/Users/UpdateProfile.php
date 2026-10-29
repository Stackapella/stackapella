<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Jobs\RedrawPersonsCards;
use App\Models\HandleHold;
use App\Models\Harmony;
use App\Models\User;
use App\Support\Accounts\Age;
use App\Support\Gallery\Gallery;
use App\Support\Push\AppPushes;
use App\Support\Sharing\HarmonyFiles;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The person's own profile (docs/backend-plan.md, B): name, handle, bio, language, time zone, the
 * social handles we credit them by, and the one-time answers (age, how they heard of us). The
 * FormRequest has already checked the handle against the rules.
 */
final class UpdateProfile
{
    private const FIELDS = ['name' => 'name', 'handle' => 'handle', 'bio' => 'bio', 'locale' => 'locale', 'timezone' => 'timezone', 'xHandle' => 'x_handle', 'blueskyHandle' => 'bluesky_handle', 'heardFrom' => 'heard_from', 'ideaEmails' => 'idea_emails', 'hideProfile' => 'hide_profile'];

    /**
     * @param  array<string, mixed>  $changes  validated, in the contract's names
     */
    public function handle(User $user, array $changes): User
    {
        $fields = [];

        foreach (self::FIELDS as $from => $to) {
            if (array_key_exists($from, $changes)) {
                $value = $changes[$from];
                $fields[$to] = is_string($value) ? trim($value) : $value;
            }
        }

        // A public profile (the gallery, N1): 18 or over, with a handle (this change's or theirs).
        if (array_key_exists('publicProfile', $changes)) {
            if ($changes['publicProfile'] === true) {
                Gallery::ensureAdult($user);

                if (($fields['handle'] ?? $user->handle) === null) {
                    throw ValidationException::withMessages(['handle' => __('validation.handle_for_profile')]);
                }
            }

            $fields['public_profile_at'] = $changes['publicProfile'] ? ($user->public_profile_at ?? now()) : null;
        }

        // How they heard of us is asked once: a later answer doesn't replace the first.
        if ($user->heard_from !== null) {
            unset($fields['heard_from']);
        }

        // A theme chosen: what's sent replaces its part, the rest stays (and the first choice
        // fills the other part with the default).
        if (is_array($changes['appearance'] ?? null)) {
            $appearance = [...User::APPEARANCE_DEFAULT, ...($user->appearance ?? []), ...array_intersect_key($changes['appearance'], User::APPEARANCE_DEFAULT)];

            // Their own accent goes with accent "custom", and only with it.
            if ($appearance['accent'] === 'custom' && ! is_string($appearance['customAccent'])) {
                throw ValidationException::withMessages(['appearance.customAccent' => __('validation.required', ['attribute' => 'customAccent'])]);
            }

            $appearance['customAccent'] = $appearance['accent'] === 'custom' ? strtolower((string) $appearance['customAccent']) : null;
            $fields['appearance'] = $appearance;
        }

        // Push to the apps: the kinds sent change, the rest stay; quiet hours are replaced (or null).
        if (is_array($changes['pushTypes'] ?? null) || array_key_exists('quietHours', $changes)) {
            $push = AppPushes::settings($user);
            $types = [...$push['types'], ...array_map(static fn (mixed $on): bool => (bool) $on, array_intersect_key((array) ($changes['pushTypes'] ?? []), $push['types']))];
            $quiet = array_key_exists('quietHours', $changes) ? $changes['quietHours'] : $push['quietHours'];
            $fields['push_settings'] = ['types' => $types, 'quietHours' => is_array($quiet) ? ['start' => (string) $quiet['start'], 'end' => (string) $quiet['end']] : null];
        }

        // When they were born, once: a later answer doesn't replace it (support can). Under 13 is
        // refused and nothing is kept.
        if (isset($changes['birthMonth'], $changes['birthYear']) && ! Age::given($user)) {
            $fields = [...$fields, ...Age::fields($user, (int) $changes['birthMonth'], (int) $changes['birthYear'])];
        }

        $before = $user->handle;

        try {
            DB::transaction(function () use ($user, $fields, $before): void {
                $user->forceFill($fields)->save();

                if ($user->handle !== $before) {
                    HandleHold::query()->where('handle', $user->handle)->where('user_id', $user->id)->delete();

                    // Only the latest handle is held, so changing again and again can't hoard names.
                    HandleHold::query()->where('user_id', $user->id)->delete();

                    if ($before !== null) {
                        $hold = HandleHold::query()->firstOrNew(['handle' => $before]);
                        // A deleted account's hold (no owner) stays forever.
                        if (! $hold->exists || $hold->user_id !== null) {
                            $hold->forceFill(['user_id' => $user->id, 'held_until' => now()->addDays(HandleHold::HOLD_DAYS)])->save();
                        }
                    }
                }
            });
        } catch (UniqueConstraintViolationException) {
            // Someone took the handle a moment ago.
            throw ValidationException::withMessages(['handle' => __('validation.handle.taken')]);
        }

        // Hidden, shown again or renamed: the share cards that name them are drawn again.
        if ($user->wasChanged(['hide_profile', 'name'])) {
            RedrawPersonsCards::dispatch($user->id);
        }

        // A profile made private takes its songs out of the gallery: their guide voices go. (Hiding
        // it keeps them in, as Anonymous; showing it again without a public profile takes them out.)
        if (($changes['publicProfile'] ?? null) === false || ($changes['hideProfile'] ?? null) === false) {
            foreach (Harmony::query()->where('user_id', $user->id)->whereNotNull('gallery_opt_in_at')->get() as $harmony) {
                app(HarmonyFiles::class)->dropGuidesUnlessPublic($harmony);
            }
        }

        return $user;
    }
}
