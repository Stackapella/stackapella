<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Actions\Beta\AcceptInvite;
use App\Exceptions\Refusal;
use App\Mail\Account\SignInLinkMail;
use App\Models\EmailSuppression;
use App\Models\EmailToken;
use App\Models\SignInLink;
use App\Models\User;
use App\Support\Accounts\AddressLimits;
use App\Support\Accounts\EarlyAccess;
use App\Support\Accounts\Sessions;
use App\Support\Admin\IntendedUrl;
use App\Support\Flags;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Sign in by email (auth.link.send and auth.link.open), for "Accept invite" with just an address:
 * a one-time link for 15 minutes that signs in, or makes the account for an address that has
 * none (verified by the link itself; its name and age are asked after). Sending always answers
 * the same, so it never tells whether an address has an account: a link that mustn't go
 * (suspended, paused sign-ups with nothing that opens them, past the address's limits) simply
 * isn't sent.
 */
final class SignInByLink
{
    public function __construct(private readonly AddressLimits $limits, private readonly Flags $flags, private readonly Sessions $sessions) {}

    public function send(string $email, ?string $intended, ?string $invite, ?string $early, ?string $ip): void
    {
        $email = strtolower(trim($email));
        $user = User::query()->where('email', $email)->first();

        if ($user?->isSuspended() === true || ($user === null && ! $this->opens($invite, $early))) {
            return;
        }

        if (! $this->limits->allows('verification-email', $email, $ip) || EmailSuppression::isSuppressed($email)) {
            return;
        }

        // An account's link goes to its own address, never the one typed (docs/security-review.md).
        $email = $user !== null ? (string) $user->email : $email;
        $token = Str::random(48);
        (new SignInLink)->forceFill([
            'email' => $email,
            'token_hash' => hash('sha256', $token),
            'context' => ['intended' => IntendedUrl::app($intended), 'invite' => $invite, 'early' => $early],
            'expires_at' => now()->addMinutes(SignInLink::MINUTES),
        ])->save();

        Mail::to($email)->send(new SignInLinkMail($token));
    }

    /**
     * Opens a link: its address's account, made if there's none (the caller signs it in).
     *
     * @return array{user: User, created: bool, intended: string}
     */
    public function open(string $token, ?string $ip): array
    {
        [$link, $user, $created] = DB::transaction(function () use ($token, $ip): array {
            $link = SignInLink::query()->where('token_hash', hash('sha256', $token))->lockForUpdate()->first();

            if ($link === null || $link->expires_at->isPast() && $link->used_at === null) {
                throw new Refusal(410, 'link_expired');
            }

            if ($link->used_at !== null) {
                throw new Refusal(410, 'link_used');
            }

            $link->forceFill(['used_at' => now()])->save();
            $user = User::query()->where('email', $link->email)->lockForUpdate()->first();

            if ($user !== null) {
                // The link proved the inbox. An account it never had proven may be someone else's
                // sign-up with this address: its password and what it said about itself go (as at
                // sign-up), and the owner starts fresh, name and age asked again.
                if ($user->email_verified_at === null) {
                    EmailToken::query()->where('user_id', $user->id)->where('purpose', EmailToken::VERIFY)->delete();
                    $user->forceFill([
                        'email_verified_at' => now(),
                        'password' => null,
                        'name' => '',
                        'pending_invite_hash' => null,
                        'birth_month' => null,
                        'birth_year' => null,
                        'age_confirmed_at' => null,
                        'adult_confirmed_at' => null,
                    ])->save();
                    $this->sessions->endAll($user);
                }

                return [$link, $user, false];
            }

            $invite = $link->context['invite'] ?? null;
            $early = $link->context['early'] ?? null;

            // Sign-ups paused since it was sent, and nothing it carries opens them.
            if (! $this->opens($invite, $early)) {
                throw new Refusal(423, 'paused');
            }

            $access = $early !== null ? EarlyAccess::claim($early, $ip) : null;
            $user = new User(['name' => '', 'email' => $link->email, 'locale' => app()->getLocale()]);
            $user->forceFill([
                'email_verified_at' => now(),
                'early_access_link_id' => $access?->id,
                'pending_invite_hash' => $invite !== null ? hash('sha256', $invite) : null,
            ])->save();

            return [$link, $user, true];
        });

        return ['user' => $user, 'created' => $created, 'intended' => (string) ($link->context['intended'] ?? '/')];
    }

    /**
     * Whether a new account may be made: sign-ups are open, or a beta invite or the early-access
     * link carried opens them.
     */
    private function opens(?string $invite, ?string $early): bool
    {
        return $this->flags->value('pause_signups') === 'off' || AcceptInvite::opens($invite) || EarlyAccess::opens($early);
    }
}
