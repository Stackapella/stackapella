<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\BetaSinger;
use App\Models\EmailToken;
use App\Models\User;
use App\Support\Accounts\Age;
use App\Support\Flags;
use App\Support\Gallery\Gallery;
use App\Support\Legal\Terms;
use App\Support\Push\AppPushes;
use App\Support\Usage\Plans;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * The signed-in person's own account (contract/schemas/account.json).
 *
 * @property User $resource
 */
final class AccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->resource;
        $pending = EmailToken::query()->where('user_id', $user->id)->where('purpose', EmailToken::EMAIL_CHANGE)->whereNull('used_at')->where('expires_at', '>', now())->value('email');

        return [
            'id' => $user->sqid(),
            'name' => $user->name,
            'handle' => $user->handle,
            'email' => $user->email,
            'emailVerified' => $user->email_verified_at !== null,
            'pendingEmail' => is_string($pending) ? $pending : null,
            'locale' => $user->locale,
            // Null until they choose a theme (the device's own choice stands until then).
            // (A choice made before accents came reads as the palette's own.)
            'appearance' => $user->appearance !== null ? [...User::APPEARANCE_DEFAULT, ...$user->appearance] : null,
            // The gallery (N1): their public profile, and what it lets them do (never their level).
            'publicProfile' => $user->public_profile_at !== null,
            // Hide profile: others see "Anonymous", with no handle, picture or profile page.
            'hideProfile' => (bool) ($user->getAttributes()['hide_profile'] ?? false),
            // Game mode's boards: their arcade initials, and whether boards show them or the name.
            'initials' => $user->getAttributes()['initials'] ?? null,
            'boardsName' => (string) ($user->getAttributes()['boards_name'] ?? 'name'),
            'ideaEmails' => (bool) ($user->getAttributes()['idea_emails'] ?? true),
            // Push to the apps (account.devices.push.update): each bell kind, on unless turned off.
            'pushTypes' => (object) AppPushes::settings($user)['types'],
            'quietHours' => AppPushes::settings($user)['quietHours'],
            'gallery' => app(Gallery::class)->abilities($user),
            'timezone' => $user->timezone,
            'bio' => $user->bio,
            'avatarUrl' => $user->avatar_key !== null ? Storage::disk('media')->url($user->avatar_key) : null,
            'xHandle' => $user->x_handle,
            'blueskyHandle' => $user->bluesky_handle,
            'methods' => ['google' => $user->google_id !== null, 'apple' => $user->apple_id !== null, 'eventSchedule' => $user->event_schedule_id !== null, 'password' => $user->password !== null],
            // 18 and over (by their birth month and year, or as they said), and whether they've
            // given when they were born; the date itself is never sent.
            'isAdult' => ($user->getAttributes()['adult_confirmed_at'] ?? null) !== null,
            // On the worldwide part leaderboards (boards.global.update; 18 and over).
            'boardsGlobal' => ($user->getAttributes()['board_global_at'] ?? null) !== null,
            'birthGiven' => Age::given($user),
            'heardFrom' => $user->heard_from,
            'plan' => app(Plans::class)->plan($user),
            // A live beta singer: the app shows "Your beta parts" and sends waiting takes.
            // A display hint only (the admin's version badge); nothing is granted by it.
            'staff' => $user->is_admin ? 'admin' : ($user->is_editor ? 'editor' : null),
            // Whether the app shows the Plus courses: everyone once plus_courses is on, staff before.
            // The terms of use they agreed to, and the current version (a new one asks once).
            'terms' => Terms::of($user),
            'plusCourses' => app(Flags::class)->on('plus_courses') || $user->is_admin || $user->is_editor,
            'betaSinger' => BetaSinger::query()->where('user_id', $user->id)->whereNull('revoked_at')->whereNull('forgotten_at')->exists(),
            'createdAt' => $user->created_at?->utc()->toIso8601ZuluString(),
        ];
    }
}
