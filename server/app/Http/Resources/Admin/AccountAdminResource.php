<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Enums\SecurityEventType;
use App\Models\BetaSinger;
use App\Models\Group;
use App\Models\Harmony;
use App\Models\Project;
use App\Models\Score;
use App\Models\SecurityEvent;
use App\Models\Take;
use App\Models\User;
use App\Models\UserLearning;
use App\Support\Billing\BillingState;
use App\Support\Gallery\Gallery;
use App\Support\Trust\TrustLevel;
use App\Support\Usage\Plans;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Someone's account as the admin sees it (contract/schemas/admin.account.json). Load it through
 * query(), which adds when they last signed in.
 *
 * @property User $resource
 */
final class AccountAdminResource extends JsonResource
{
    private bool $detail = false;

    /**
     * @return Builder<User>
     */
    public static function query(): Builder
    {
        return User::query()->select('users.*')->withCount(['takes as ready_takes_count' => fn ($query) => $query->where('status', Take::READY)])->addSelect(['last_sign_in_at' => SecurityEvent::query()
            ->select('created_at')
            ->whereColumn('security_events.user_id', 'users.id')
            ->where('type', SecurityEventType::SignIn)
            ->orderByDesc('id')
            ->limit(1),
            // Last seen on any of their browsers or devices (each request touches it at most daily).
            'last_active_at' => DB::table('user_devices')->selectRaw('max(last_seen_at)')->whereColumn('user_devices.user_id', 'users.id'),
            'beta_singer_id' => BetaSinger::query()->select('id')->whereColumn('beta_singers.user_id', 'users.id')->limit(1),
            // Usage, as counts and times only (Hillel's "stats about each person"): never what's in them.
            'songs_count' => Project::query()->selectRaw('count(*)')->whereColumn('projects.user_id', 'users.id')->whereNull('binned_at'),
            'last_take_at' => Take::query()->selectRaw('max(created_at)')->whereColumn('takes.user_id', 'users.id'),
            'last_practice_at' => Score::query()->selectRaw('max(recorded_at)')->whereColumn('scores.user_id', 'users.id'),
            'last_song_at' => Project::query()->selectRaw('max(updated_at)')->whereColumn('projects.user_id', 'users.id'),
            'last_share_at' => Harmony::query()->selectRaw('max(created_at)')->whereColumn('harmonies.user_id', 'users.id')])
            ->with('learning');
    }

    /**
     * The person page's own fields too: their plan and what they've made public (never their
     * recordings, private songs or birth date).
     */
    public function detailed(): self
    {
        $this->detail = true;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->resource;
        $lastSignIn = $user->getAttribute('last_sign_in_at');
        $lastActive = $user->getAttribute('last_active_at');

        return [
            'id' => $user->sqid(),
            'name' => $user->name,
            'handle' => $user->handle,
            'email' => $user->email,
            'emailVerified' => $user->email_verified_at !== null,
            // Apple's Hide My Email: support can see the relay address (the operations plan).
            'appleRelay' => $user->email !== null && str_ends_with($user->email, '@privaterelay.appleid.com'),
            'methods' => ['google' => $user->google_id !== null, 'apple' => $user->apple_id !== null, 'eventSchedule' => $user->event_schedule_id !== null, 'password' => $user->password !== null],
            'staff' => $user->is_admin ? 'admin' : ($user->is_editor ? 'editor' : null),
            // Plus paid by invoice (M10), until this time.
            'plusPaidUntil' => $user->plus_paid_until?->utc()->toIso8601ZuluString(),
            // Their sandbox store purchases give Plus (App Review, TestFlight), since then.
            'storeTesterSince' => $user->store_tester_at?->utc()->toIso8601ZuluString(),
            'trustLevel' => app(TrustLevel::class)->of($user),
            'trustOverride' => $user->trust_level_override,
            'trustNote' => $user->trust_note,
            'suspendedAt' => $user->suspended_at?->utc()->toIso8601ZuluString(),
            'ref' => $user->ref,
            'heardFrom' => $user->heard_from,
            'createdAt' => $user->created_at?->utc()->toIso8601ZuluString(),
            'lastSignInAt' => $lastSignIn !== null ? Carbon::parse((string) $lastSignIn)->utc()->toIso8601ZuluString() : null,
            'lastActiveAt' => $lastActive !== null ? Carbon::parse((string) $lastActive)->utc()->toIso8601ZuluString() : null,
            // 18 or over, under, or not known (no birth date given): never the date itself.
            'adult' => $user->adult_confirmed_at !== null ? true : ($user->birth_year !== null ? false : null),
            'betaSinger' => $user->getAttribute('beta_singer_id') !== null,
            'country' => $user->country,
            'stats' => $this->stats($user),
            ...($this->detail ? $this->detail($user) : []),
        ];
    }

    /**
     * How much they use the app, never what they made: the list's few, the person page's more.
     *
     * @return array<string, mixed>
     */
    private function stats(User $user): array
    {
        $progress = (array) ($user->relationLoaded('learning') ? $user->learning?->progress : UserLearning::query()->where('user_id', $user->id)->value('progress'));
        $lessons = array_filter((array) ($progress['lessons'] ?? []), 'is_array');
        $weeks = (array) ($progress['weeks'] ?? []);
        // Weeks in a row as the app shows them: a run that ended before last week is none.
        $monday = now()->startOfWeek();
        $streak = in_array($weeks['last'] ?? null, [$monday->toDateString(), $monday->copy()->subWeek()->toDateString()], true) ? (int) ($weeks['streak'] ?? 0) : 0;
        $times = array_filter(['take' => $user->getAttribute('last_take_at'), 'practice' => $user->getAttribute('last_practice_at'), 'song' => $user->getAttribute('last_song_at'), 'share' => $user->getAttribute('last_share_at'), 'sign_in' => $user->getAttribute('last_sign_in_at')]);
        arsort($times);
        $kind = array_key_first($times);

        $stats = [
            'songs' => (int) $user->getAttribute('songs_count'),
            'lessonsDone' => count(array_filter($lessons, static fn (array $lesson): bool => (int) ($lesson['stars'] ?? 0) > 0)),
            'streakWeeks' => $streak,
            'lastActivity' => $kind !== null ? ['kind' => $kind, 'at' => Carbon::parse((string) $times[$kind])->utc()->toIso8601ZuluString()] : null,
        ];

        if (! $this->detail) {
            return $stats;
        }

        $xp = (int) ($progress['xp']['total'] ?? 0);
        $practice = array_map('intval', array_filter((array) ($progress['practice'] ?? []), 'is_numeric'));
        $week = now()->subDays(6)->toDateString();

        return [
            ...$stats,
            'takes' => (int) $user->getAttribute('ready_takes_count'),
            'stars' => array_sum(array_map(static fn (array $lesson): int => (int) ($lesson['stars'] ?? 0), $lessons)),
            'xp' => $xp,
            // The app's voice level: level n starts at 50 x (n - 1) x n XP (web/js/course.js).
            'level' => (int) floor((1 + sqrt(1 + $xp / 12.5)) / 2),
            'practiceMinutes' => [
                'lastWeek' => intdiv(array_sum(array_filter($practice, static fn (string $day): bool => $day >= $week, ARRAY_FILTER_USE_KEY)), 60),
                'total' => intdiv(array_sum($practice), 60),
            ],
            'storageBytes' => app(Plans::class)->storageUsed($user),
        ];
    }

    /**
     * Their plan now, from the billing state: the person page's, and a support message's sender's.
     *
     * @return array{plus: bool, source: string|null, until: string|null}
     */
    public static function plan(User $user): array
    {
        $billing = app(BillingState::class)->show($user);
        $subscription = is_array($billing['subscription'] ?? null) ? $billing['subscription'] : [];
        $store = is_array($billing['store'] ?? null) ? $billing['store'] : [];
        $until = $subscription['endsAt'] ?? $subscription['renewsAt'] ?? $store['expiresAt'] ?? $user->plus_paid_until?->utc()->toIso8601ZuluString();

        return ['plus' => $billing['plan'] === 'plus', 'source' => is_string($billing['source'] ?? null) ? $billing['source'] : null, 'until' => is_string($until) ? $until : null];
    }

    /**
     * @return array{plan: array{plus: bool, source: string|null, until: string|null}, counts: array{publicHarmonies: int, groups: int}}
     */
    private function detail(User $user): array
    {
        $public = Harmony::query()->with('user')->where('user_id', $user->id)->where(fn ($q) => $q->whereNotNull('may_feature_at')->orWhereNotNull('gallery_opt_in_at'))->get()
            ->filter(fn (Harmony $harmony): bool => app(Gallery::class)->isPublic($harmony))->count();

        return [
            'plan' => self::plan($user),
            'counts' => [
                'publicHarmonies' => $public,
                'groups' => Group::query()->where('director_id', $user->id)->orWhereHas('members', fn ($q) => $q->where('user_id', $user->id))->count(),
            ],
        ];
    }
}
