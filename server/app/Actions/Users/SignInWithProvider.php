<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Actions\Counts\RecordCounts;
use App\Enums\SecurityEventType;
use App\Exceptions\Refusal;
use App\Models\User;
use App\Support\Accounts\EarlyAccess;
use App\Support\Flags;
use App\Support\Sharing\ShareSignUps;
use App\Support\Trust\SecurityLog;
use Illuminate\Support\Facades\DB;

/**
 * Find or make the person behind a Google or Apple account (iaw's SignInWithGoogle). Matching is
 * by the provider's own id only, never by email: a merge by email is an account takeover
 * (docs/backend-plan.md, B). The email is stored only when nobody else has it, and counts as
 * verified (both providers verify it). A new account gives when it was born later (birthGiven).
 *
 * Refusals carry the code the sign-in page words: `paused` (sign-ups paused), `suspended`.
 */
final class SignInWithProvider
{
    public function __construct(private readonly Flags $flags, private readonly SecurityLog $securityLog) {}

    /**
     * @param  'google'|'apple'|'event_schedule'  $provider
     */
    public function handle(string $provider, string $id, string $name, ?string $email, string $locale, ?string $ref, ?string $ip, bool $invited = false, ?string $early = null, ?string $country = null): User
    {
        // The provider's name on one line, as a name typed here must be (OneLine).
        $name = trim((string) preg_replace('/[\p{Cc}\x{2028}\x{2029}]+/u', ' ', $name));
        $column = $provider.'_id';

        $user = DB::transaction(function () use ($column, $id, $name, $email, $locale, $ref, $provider, $invited, $early, $ip, $country): User {
            $user = User::query()->where($column, $id)->lockForUpdate()->first();

            if ($user !== null) {
                return $user;
            }

            // A beta invite gets its singer through paused sign-ups, and the open early-access
            // link its new account (marked with the link either way).
            $link = $early !== null ? EarlyAccess::claim($early, $ip) : null;

            if ($this->flags->value('pause_signups') === 'all' && ! $invited && $link === null) {
                throw new Refusal(423, 'paused');
            }

            $email = $email !== null ? strtolower($email) : null;
            $emailFree = $email !== null && ! User::query()->where('email', $email)->exists();

            $user = new User(['name' => $name !== '' ? $name : (string) $email, 'locale' => $locale]);
            $user->forceFill([
                $column => $id,
                'email' => $emailFree ? $email : null,
                'email_verified_at' => $emailFree ? now() : null,
                'ref' => $ref,
                'early_access_link_id' => $link?->id,
            ])->save();
            ShareSignUps::record($user, $ref);

            $this->securityLog->record(SecurityEventType::SignUp, $user, null, ['method' => $provider, 'ref' => $ref]);
            // The anonymous daily count: the server counts every new account, whichever way it came.
            app(RecordCounts::class)->handle([['kind' => 'sign_up']], true, $country);

            return $user;
        });

        if ($user->isSuspended()) {
            throw new Refusal(403, 'suspended');
        }

        return $user;
    }

    /**
     * Add Google or Apple to the signed-in account, from Settings. That provider account must not
     * belong to someone else.
     *
     * @param  'google'|'apple'|'event_schedule'  $provider
     */
    public function link(User $user, string $provider, string $id): User
    {
        $column = $provider.'_id';
        $owner = User::query()->where($column, $id)->first();

        if ($owner !== null && $owner->id !== $user->id) {
            throw new Refusal(409, 'linked_elsewhere');
        }

        $user->forceFill([$column => $id])->save();

        return $user;
    }
}
