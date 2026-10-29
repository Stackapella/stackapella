<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Actions\Beta\AcceptInvite;
use App\Actions\Counts\RecordCounts;
use App\Enums\SecurityEventType;
use App\Exceptions\Refusal;
use App\Mail\Account\AlreadyHaveAnAccount;
use App\Mail\Account\VerifyEmailAddress;
use App\Models\EmailSuppression;
use App\Models\EmailToken;
use App\Models\User;
use App\Support\Accounts\AddressLimits;
use App\Support\Accounts\Age;
use App\Support\Accounts\EarlyAccess;
use App\Support\Accounts\EmailTokens;
use App\Support\Flags;
use App\Support\Sharing\ShareSignUps;
use App\Support\Trust\SecurityLog;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Sign up with an email and a password (docs/backend-plan.md, B). The account isn't signed in:
 * the verification link does that. The answer is the same whether or not the address has an
 * account, so an address that has one gets an email saying so (with a way back in), never a new
 * account. Every email to one address is capped (AddressLimits), so this can't bomb anyone.
 */
final class RegisterWithPassword
{
    public function __construct(
        private readonly EmailTokens $tokens,
        private readonly AddressLimits $limits,
        private readonly SecurityLog $securityLog,
    ) {}

    /**
     * @param  array{month: int, year: int}  $birth  when they were born (Age)
     */
    public function handle(string $name, string $email, string $password, ?string $ref, string $locale, ?string $ip, array $birth, ?string $invite = null, ?string $early = null, ?string $country = null): void
    {
        $email = strtolower(trim($email));
        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null) {
            // An unverified account's password was never proven to be the inbox owner's (anyone
            // could have signed up with this address first), so it goes: the owner chooses one
            // from the link, which also verifies the address.
            if ($existing->email_verified_at === null) {
                EmailToken::query()->where('user_id', $existing->id)->where('purpose', EmailToken::VERIFY)->delete();
                $existing->forceFill(['password' => null, 'pending_invite_hash' => $invite !== null ? hash('sha256', $invite) : $existing->pending_invite_hash])->save();
            }

            if ($this->limits->allows('verification-email', $email) && ! EmailSuppression::isSuppressed($email)) {
                Mail::to((string) $existing->email)->send(new AlreadyHaveAnAccount(Password::broker()->createToken($existing), (string) $existing->email));
            } else {
                // A new account's password costs a bcrypt, and so does the link: the same here.
                Hash::make(Str::random(40));
            }

            return;
        }

        try {
            $user = DB::transaction(function () use ($name, $email, $password, $ref, $locale, $invite, $early, $ip, $birth): User {
                // The early-access link's place, taken with the account (refused while paused
                // when it filled or closed a moment ago, and no beta invite carries them).
                $link = $early !== null ? EarlyAccess::claim($early, $ip) : null;

                if ($link === null && $early !== null && app(Flags::class)->value('pause_signups') !== 'off' && ! AcceptInvite::opens($invite)) {
                    throw new Refusal(423, 'paused');
                }

                $user = new User(['name' => $name, 'email' => $email, 'locale' => $locale]);
                $age = Age::fields($user, $birth['month'], $birth['year']);
                $user->forceFill(['password' => $password, 'ref' => $ref, 'early_access_link_id' => $link?->id, ...$age, 'pending_invite_hash' => $invite !== null ? hash('sha256', $invite) : null])->save();
                ShareSignUps::record($user, $ref);

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            // The same address signed up a moment ago (a double tap): that one's email is on its way.
            return;
        }

        $this->securityLog->record(SecurityEventType::SignUp, $user, $ip, ['method' => 'password', 'ref' => $ref]);
        // The anonymous daily count: the server counts every new account, whichever way it came.
        app(RecordCounts::class)->handle([['kind' => 'sign_up']], true, $country);

        if ($this->limits->allows('verification-email', $email) && ! EmailSuppression::isSuppressed($email)) {
            Mail::to($email)->send(new VerifyEmailAddress($this->tokens->issue($user, EmailToken::VERIFY, $email, 24), $user->name));
        }
    }
}
