<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Exceptions\Refusal;
use App\Models\CheckoutSession;
use App\Models\User;
use App\Services\Billing\BillingGateway;
use App\Support\Billing\BillingState;
use App\Support\Billing\Withdrawal;
use App\Support\Pages\Prices;
use App\Support\Trust\TrustLevel;
use App\Support\Usage\Plans;
use Illuminate\Validation\ValidationException;

/**
 * billing.checkout: a Stripe Checkout for Plus (docs/pricing-plan.md, "At checkout"). Only for a
 * verified account (card testing), never a second trial on one account (a second on one card is
 * caught after Checkout, when Stripe shows the card), and the upgrade moment is kept for counting.
 */
final class StartCheckout
{
    /**
     * The upgrade moments the app sends (contract/api.md): the solo extras, and since 2026-11-12 starting something
     * shared (a group, a friend on your song; docs/pricing-plan.md). Anything else, an older app's plus_song or workout
     * too, is "other".
     */
    public const MOMENTS = ['storage_limit', 'mp3', 'stems', 'guide', 'private_template', 'settings', 'group_start', 'invite_friend', 'private_shared', 'choir_download', 'choir_song', 'choir_settings', 'choir_friends'];

    public function __construct(private readonly BillingGateway $gateway, private readonly BillingState $state, private readonly Plans $plans, private readonly TrustLevel $trust) {}

    /**
     * @param  'month'|'year'  $interval
     */
    public function handle(User $user, string $interval, string $currency, bool $trial, string $moment, ?string $country = null, bool $startNowConsent = false): string
    {
        // Before Stripe is set up (no key, or no price), Checkout is simply off.
        if ((string) config('cashier.secret') === '' || (string) config('billing.prices.'.$interval) === '') {
            throw new Refusal(423, 'paused');
        }

        if ($this->trust->of($user) < TrustLevel::VERIFIED) {
            throw new Refusal(403, 'email_unverified');
        }

        // Their own Plus: a choir seat (docs/choir-seats-plan.md) is the choir's, so a seated singer may still buy theirs,
        // and their seat goes to the next singer waiting.
        if ($this->plans->ownPlan($user) === 'plus') {
            throw new Refusal(409, 'already_plus');
        }

        // In the EU, EEA and UK, Plus starts at once only with the person's consent to that.
        $needsConsent = Withdrawal::needsConsent($country) || Withdrawal::needsConsent($user->country);

        if ($needsConsent && ! $startNowConsent) {
            throw ValidationException::withMessages(['startNowConsent' => __('billing.start_now_consent_required')]);
        }

        $offer = $this->state->trial($user);

        // With trials off, a `trial` asked for (an older app's) is ignored: Checkout starts paid.
        if ((int) config('billing.trial_days') <= 0) {
            $trial = false;
        } elseif ($trial && ($offer === null || ! in_array($interval, $offer['intervals'], true))) {
            throw new Refusal(409, 'trial_used');
        }

        // One Checkout at a time (the billing review): an open one is closed first, so two can never both be paid;
        // one that has just been paid (its webhook not here yet) means wait.
        CheckoutSession::query()->where('user_id', $user->id)->whereNull('completed_at')->where('created_at', '>', now()->subDay())->orderBy('id')
            ->each(function (CheckoutSession $open): void {
                if ($this->gateway->closeCheckout($open->stripe_id) === 'complete') {
                    throw new Refusal(409, 'checkout_pending');
                }
            });

        // Stripe ties a customer to its first currency.
        $first = CheckoutSession::query()->where('user_id', $user->id)->whereNotNull('completed_at')->oldest('id')->value('currency');
        $currency = is_string($first) ? $first : $currency;

        $session = $this->gateway->checkout(
            $user,
            (string) config('billing.prices.'.$interval),
            $currency,
            $trial ? (int) config('billing.trial_days') : null,
            // The yearly trial takes a card up front; the monthly one (trust level 2) doesn't.
            ! ($trial && $interval === 'month'),
            url('/plus/return').'?session={CHECKOUT_SESSION_ID}',
            url('/settings/plus?checkout=cancelled'),
            ['user' => $user->sqid(), 'moment' => $moment],
            self::terms($interval, $currency, $trial, $user),
            // A durable copy of the EU, EEA and UK consent, on every receipt and invoice.
            $needsConsent ? (string) __('billing.start_now_receipt', ['date' => now()->toDateString()], $user->locale ?: 'en') : null,
        );

        $record = new CheckoutSession([
            'stripe_id' => $session['id'],
            'moment' => in_array($moment, self::MOMENTS, true) ? $moment : 'other',
            'interval' => $interval,
            'currency' => $currency,
            'trial' => $trial,
        ]);
        $record->forceFill([
            'user_id' => $user->id,
            'country' => $country,
            'start_now_consent_at' => $needsConsent ? now() : null,
            'start_now_consent_version' => $needsConsent ? Withdrawal::version() : null,
        ])->save();

        // A new Checkout clears an old "this card had a trial" notice.
        if ($user->billing_notice === 'card_had_trial') {
            $user->forceFill(['billing_notice' => null, 'billing_notice_at' => null])->save();
        }

        return $session['url'];
    }

    /**
     * What Stripe's Checkout page says above the button: that it renews, at what, and how to
     * cancel; with a trial, the day it starts charging (docs/pricing-plan.md, "At checkout").
     *
     * @param  'month'|'year'  $interval
     */
    private static function terms(string $interval, string $currency, bool $trial, User $user): string
    {
        $locale = $user->locale ?: 'en';
        $price = Prices::of($currency)[$interval];
        $words = [
            'interval' => (string) __('billing.checkout_terms.interval.'.$interval, [], $locale),
            // Where tax isn't in the price (US dollars), Stripe adds it: the words say so.
            'price' => (bool) config('billing.amounts.'.$currency.'.tax_inclusive') ? $price : (string) __('billing.checkout_terms.plus_tax', ['price' => $price], $locale),
            'date' => now()->addDays((int) config('billing.trial_days'))->settings(['locale' => $locale])->isoFormat('LL'),
        ];
        // The monthly trial takes no card, so it ends unless one is added.
        $key = match (true) {
            $trial && $interval === 'month' => 'billing.checkout_terms.trial_no_card',
            $trial => 'billing.checkout_terms.trial',
            default => 'billing.checkout_terms.renews',
        };

        return (string) __($key, $words, $locale);
    }
}
