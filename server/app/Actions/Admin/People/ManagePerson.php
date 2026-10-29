<?php

declare(strict_types=1);

namespace App\Actions\Admin\People;

use App\Actions\Billing\BillingEvents;
use App\Actions\Notices\Notices;
use App\Exceptions\Refusal;
use App\Mail\Account\RecoveredBySupport;
use App\Mail\Account\ResetPassword;
use App\Models\AuditLog;
use App\Models\Harmony;
use App\Models\Notice;
use App\Models\User;
use App\Services\Billing\BillingGateway;
use App\Support\Accounts\Sessions;
use App\Support\Sharing\HarmonyFiles;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/**
 * The admin's actions on someone's account (docs/backend-plan.md A2, "people"; the operations
 * plan's "Getting back into an account"), each behind sudo and audit-logged with its reason. Staff
 * accounts are changed only from the console, so a stolen admin session can't lock Hillel out.
 */
final class ManagePerson
{
    public function __construct(private readonly Sessions $sessions, private readonly HarmonyFiles $harmonyFiles, private readonly Notices $notices, private readonly BillingGateway $billing, private readonly BillingEvents $billingEvents) {}

    public function suspend(User $person, User $admin, string $reason, ?string $ip): User
    {
        $this->refuseStaff($person);

        if (! $person->isSuspended()) {
            $person->forceFill(['suspended_at' => now()])->save();
            $this->sessions->endAll($person);
            AuditLog::record('person.suspended', $person, $admin, reason: $reason, ip: $ip);
            $this->notices->give($person, Notice::ACCOUNT_SUSPENDED, null, 'terms', $reason);
        }

        $this->syncShared($person);

        return $person;
    }

    public function unsuspend(User $person, User $admin, string $reason, ?string $ip): User
    {
        if ($person->isSuspended()) {
            $person->forceFill(['suspended_at' => null])->save();
            AuditLog::record('person.unsuspended', $person, $admin, reason: $reason, ip: $ip);
        }

        $this->syncShared($person);

        return $person;
    }

    /**
     * A refund of the account's latest Plus payment, in full or in part, within what's left of it
     * (docs/operations-plan.md, "Refunds"; in sudo). Its key is the refund itself, so a retry is
     * the same refund. Any refund from here ends Plus at once, full or part (Hillel, 2026-10-29:
     * "Always ends Plus"); Plus from a store follows the store's own refunds.
     *
     * @return array{amountCents: int, currency: string, refundedCents: int, paidCents: int}
     */
    public function refundPlus(User $person, User $admin, int $cents, string $note, ?string $ip): array
    {
        // One refund at a time per person, and the same amount again within ten minutes is the same
        // click (a double submit, a retry after a timeout): it answers as the first did, never
        // refunding twice.
        return Cache::lock('plus-refund:'.$person->id, 30)->block(10, function () use ($person, $admin, $cents, $note, $ip): array {
            $payment = $this->billing->latestPayment($person) ?? throw new Refusal(409, 'no_payment');
            $memo = 'plus-refund:'.$payment['paymentIntent'].':'.$cents;
            $done = Cache::get($memo);

            if (is_array($done)) {
                /** @var array{amountCents: int, currency: string, refundedCents: int, paidCents: int} $done */
                return $done;
            }

            $left = $payment['cents'] - $payment['refunded'];

            if ($cents < 1 || $cents > $left) {
                throw ValidationException::withMessages(['amountCents' => __('validation.refund_amount', ['max' => $left])]);
            }

            $this->billing->refund($payment['paymentIntent'], $cents, 'plus-refund-'.$payment['paymentIntent'].'-'.$payment['refunded'].'-'.$cents);
            $this->billingEvents->endPlusNow($person, 'refunded');
            AuditLog::record('person.plus_refunded', $person, $admin, after: ['amountCents' => $cents, 'currency' => $payment['currency']], reason: $note, ip: $ip);
            $result = ['amountCents' => $cents, 'currency' => $payment['currency'], 'refundedCents' => $payment['refunded'] + $cents, 'paidCents' => $payment['cents']];
            Cache::put($memo, $result, now()->addMinutes(10));

            return $result;
        });
    }

    /**
     * Set or clear the trust level by hand (docs/trust-and-safety-plan.md, "Trust levels").
     */
    public function setTrust(User $person, User $admin, ?int $level, string $note, ?string $ip): User
    {
        $before = ['level' => $person->trust_level_override];
        $person->forceFill(['trust_level_override' => $level, 'trust_note' => $level !== null ? $note : null])->save();
        AuditLog::record('person.trust', $person, $admin, before: $before, after: ['level' => $level], reason: $note, ip: $ip);

        // Held at 0 or 1 (what they can share and send is limited): the person is told why.
        if ($level !== null && $level <= 1 && ($before['level'] === null || $before['level'] > $level)) {
            $this->notices->give($person, Notice::ACCOUNT_LIMITED, null, 'community_guidelines', $note);
        }

        return $person;
    }

    /**
     * Someone who lost their Google or Apple account, verified by support with what only the
     * owner knows: their account gets the address they can read now, and a link to choose a
     * password. The old address is told, and every session ends.
     */
    public function recover(User $person, User $admin, string $email, string $reason, ?string $ip): User
    {
        $this->refuseStaff($person);
        $email = strtolower(trim($email));
        $before = $person->email;

        if (User::query()->where('email', $email)->whereKeyNot($person->id)->exists()) {
            throw new Refusal(409, 'email_taken');
        }

        try {
            $person->forceFill(['email' => $email, 'email_verified_at' => now()])->save();
        } catch (UniqueConstraintViolationException) {
            throw new Refusal(409, 'email_taken');
        }

        $this->sessions->endAll($person);
        // The audit log is kept for good, so it holds no addresses, only which changed.
        AuditLog::record('person.recovered', $person, $admin, before: ['email' => self::fingerprint($before)], after: ['email' => self::fingerprint($email)], reason: $reason, ip: $ip);

        Mail::to($email)->send(new ResetPassword(Password::broker()->createToken($person), $email));

        if ($before !== null && $before !== $email) {
            Mail::to($before)->send(new RecoveredBySupport($email));
        }

        return $person;
    }

    private static function fingerprint(?string $email): ?string
    {
        return $email !== null ? substr(hash('sha256', $email), 0, 12) : null;
    }

    /**
     * Suspending hides everything they've shared (off the CDN too); lifting it brings it back.
     */
    private function syncShared(User $person): void
    {
        foreach (Harmony::query()->where('user_id', $person->id)->whereNull('deleted_at')->where('status', Harmony::READY)->get() as $harmony) {
            $this->harmonyFiles->sync($harmony);
        }
    }

    private function refuseStaff(User $person): void
    {
        if ($person->is_admin || $person->is_editor) {
            throw new Refusal(409, 'staff_account');
        }
    }
}
