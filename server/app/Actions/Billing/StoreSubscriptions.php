<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Exceptions\Refusal;
use App\Models\AuditLog;
use App\Models\StoreSubscription;
use App\Models\User;
use App\Services\Stores\AppleSignedData;
use App\Services\Stores\AppStoreApi;
use App\Services\Stores\PlayApi;
use App\Support\Usage\Plans;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Plus bought in the apps (docs/app-purchases-plan.md, Hillel's option A): App Store and Google
 * Play subscriptions linked to accounts, one Plus whatever sold it. The apps send what the store
 * gave them (billing.store.sync, a purchase or a restore); the stores' notifications keep each one
 * current after that (renewals, grace, expiry, refunds).
 *
 * What the app sends never undoes what the stores said since (an older transaction, or a refund
 * the app's copy predates); a purchase signed with another account's token is that account's; a
 * purchase linked to one account is only moved when the store's newest signed data names another.
 */
final class StoreSubscriptions
{
    /** Each change runs in a transaction, tried this often (a sync and the store's first
     * notification can race to make the same row). */
    private const ATTEMPTS = 3;

    public function __construct(private readonly AppStoreApi $appStore, private readonly PlayApi $play, private readonly Plans $plans) {}

    /**
     * billing.store.sync for iOS and macOS: each signed transaction is checked, then linked.
     * Unknown or unverifiable ones are skipped and counted.
     *
     * @param  list<string>  $transactions
     * @return array{linked: int, elsewhere: int, invalid: int}
     */
    public function syncApple(User $user, array $transactions): array
    {
        // Off until Apple's Server API is set up: the app's copy alone is never the truth.
        if (! $this->appStore->configured()) {
            throw new Refusal(423, 'paused');
        }

        $result = ['linked' => 0, 'elsewhere' => 0, 'invalid' => 0];
        $touched = [$user->id];
        $this->ensureToken($user);

        try {
            foreach ($transactions as $jws) {
                try {
                    $transaction = $this->appleTransaction($jws);
                } catch (Refusal) {
                    $result['invalid']++;

                    continue;
                }

                $token = $transaction['appAccountToken'] ?? null;

                if (! $this->forThisAccount($user, $token)) {
                    $result['elsewhere']++;

                    continue;
                }

                $original = (string) $transaction['originalTransactionId'];
                // Apple's own view of it, now (outside any lock: it's a call out).
                $status = $this->appStore->status($original, (string) ($transaction['environment'] ?? 'Production'));

                if ($status === null) {
                    $result['invalid']++;

                    continue;
                }

                try {
                    $current = AppleSignedData::decode($status['transaction']);
                } catch (Refusal) {
                    $result['invalid']++;

                    continue;
                }

                // Apple's current transaction names its account; the app's copy never does.
                $named = $current['appAccountToken'] ?? null;

                if (! $this->forThisAccount($user, $named)) {
                    $result['elsewhere']++;

                    continue;
                }

                $this->ensureRow(StoreSubscription::APPLE, $original);
                $outcome = DB::transaction(function () use ($user, $transaction, $named, $status, $current, $original): array {
                    $row = $this->lock(StoreSubscription::APPLE, $original);
                    $from = $row->user_id;

                    // Linked to someone else: only Apple's current transaction, signed with this
                    // account's token (a newer purchase on that Apple ID, made here), moves it.
                    if ($from !== null && $from !== $user->id && ! (is_string($named) && $named !== '')) {
                        return ['linked' => false, 'from' => null];
                    }

                    $row->forceFill(['user_id' => $user->id]);
                    // Apple's answer is the truth now; it's stamped with when Apple signed it, so a
                    // notification delayed from before can't overwrite it.
                    $signedAt = self::millis($current['signedDate'] ?? null) ?? now();

                    if ($row->last_event_at === null || $signedAt->gte($row->last_event_at)) {
                        $this->applyApple($row, $transaction, null, $status);
                        $row->forceFill(['last_event_at' => $signedAt]);
                    }

                    $row->save();

                    return ['linked' => true, 'from' => $from !== $user->id ? $from : null];
                }, self::ATTEMPTS);

                $result[$outcome['linked'] ? 'linked' : 'elsewhere']++;
                $touched[] = $outcome['from'];
            }
        } finally {
            $this->recountIds($touched);
        }

        return $result;
    }

    /**
     * billing.store.sync for Android: each purchase token is checked with Google, linked, and
     * acknowledged if Google is still waiting for that.
     *
     * @param  list<array{productId: string, purchaseToken: string}>  $purchases
     * @return array{linked: int, elsewhere: int, invalid: int}
     */
    public function syncGoogle(User $user, array $purchases): array
    {
        if (! $this->play->configured()) {
            throw new Refusal(423, 'paused');
        }

        $result = ['linked' => 0, 'elsewhere' => 0, 'invalid' => 0];
        $touched = [$user->id];
        $this->ensureToken($user);

        try {
            foreach ($purchases as $purchase) {
                $token = $purchase['purchaseToken'];
                $state = $this->play->subscription($token);

                if ($state === null || $purchase['productId'] !== (string) config('services.google_play.subscription') || ! $this->ourGoogleProduct($state)) {
                    $result['invalid']++;

                    continue;
                }

                $owner = $state['externalAccountIdentifiers']['obfuscatedExternalAccountId'] ?? null;
                $owner = is_string($owner) ? $owner : null;

                if (! $this->forThisAccount($user, $owner)) {
                    $result['elsewhere']++;

                    continue;
                }

                $this->ensureGoogleRow($token, $state);
                $outcome = DB::transaction(function () use ($user, $token, $state, $owner): array {
                    $row = $this->lockedGoogleRow($token, $state);
                    $from = $row->user_id;

                    // An older token than the row's now: it never moves or changes the subscription.
                    if ($this->supersededBy($row, $token, $state)) {
                        return ['linked' => $from === $user->id, 'from' => null, 'row' => $row];
                    }

                    if ($from !== null && $from !== $user->id && ($owner === null || $owner === '')) {
                        return ['linked' => false, 'from' => null, 'row' => $row];
                    }

                    $row->forceFill(['user_id' => $user->id]);
                    $this->applyGoogle($row, $token, $state);
                    $row->save();

                    return ['linked' => true, 'from' => $from !== $user->id ? $from : null, 'row' => $row];
                }, self::ATTEMPTS);

                $result[$outcome['linked'] ? 'linked' : 'elsewhere']++;
                $touched[] = $outcome['from'];
                $this->acknowledgeIfWaiting($outcome['row'], $token, $state);
            }
        } finally {
            $this->recountIds($touched);
        }

        return $result;
    }

    /**
     * App Store Server Notifications V2 (`signedPayload`, checked by the caller's decode): the
     * subscription it's about is brought up to date. One that isn't linked yet goes to the account
     * its appAccountToken names, or waits for the sync that links it. Not ours (another product):
     * nothing to do.
     */
    public function appleNotification(string $signedPayload): void
    {
        $notification = AppleSignedData::decode($signedPayload);
        $data = (array) ($notification['data'] ?? []);

        if (($notification['notificationType'] ?? null) === 'TEST' || ! is_string($data['signedTransactionInfo'] ?? null)) {
            return;
        }

        try {
            $transaction = $this->appleTransaction($data['signedTransactionInfo']);
            $renewal = is_string($data['signedRenewalInfo'] ?? null) ? AppleSignedData::decode($data['signedRenewalInfo']) : null;
        } catch (Refusal) {
            return;
        }

        $signedAt = self::millis($notification['signedDate'] ?? null) ?? now();
        $original = (string) $transaction['originalTransactionId'];
        // The notification is only the news: Apple's own state now is what's applied (a refund
        // asked for an old renewal names that renewal, not the period being paid now).
        $status = $this->appStore->configured() ? $this->appStore->status($original, (string) ($transaction['environment'] ?? 'Production')) : null;

        if ($this->appStore->configured() && $status === null) {
            return;
        }

        $this->ensureRow(StoreSubscription::APPLE, $original);
        $ids = DB::transaction(function () use ($notification, $transaction, $renewal, $signedAt, $status, $original): array {
            $row = $this->lock(StoreSubscription::APPLE, $original);

            // Notifications can come out of order: an older one never undoes a newer one.
            if ($row->last_event_at !== null && $signedAt->lt($row->last_event_at)) {
                return [];
            }

            $type = (string) ($notification['notificationType'] ?? '');
            $from = $row->user_id;

            if ($status !== null) {
                $current = AppleSignedData::decode($status['transaction']);
                // Apple's current transaction names its account: it's that account's subscription.
                $row->forceFill(['user_id' => self::userByToken($current['appAccountToken'] ?? null)->id ?? $from]);
                $this->applyApple($row, $current, null, $status);

                if ($type === 'REFUND' && $row->status === StoreSubscription::REVOKED) {
                    $row->forceFill(['refunded_at' => now()]);
                }

                $row->forceFill(['last_event_at' => $signedAt])->save();

                return [$from, $row->user_id];
            }

            // Without Apple's API: a transaction from an earlier period than the row's never
            // changes it.
            $expires = self::millis($transaction['expiresDate'] ?? null);

            if ($row->expires_at !== null && $expires !== null && $expires->lt($row->expires_at)) {
                $row->forceFill(['last_event_at' => $signedAt])->save();

                return [];
            }

            $row->forceFill(['user_id' => self::userByToken($transaction['appAccountToken'] ?? null)->id ?? $from]);
            $this->applyApple($row, $transaction, $renewal, null);

            $subtype = (string) ($notification['subtype'] ?? '');
            $row->forceFill(match (true) {
                $type === 'DID_FAIL_TO_RENEW' && $subtype === 'GRACE_PERIOD' => ['status' => StoreSubscription::GRACE],
                $type === 'DID_FAIL_TO_RENEW', $type === 'GRACE_PERIOD_EXPIRED' => ['status' => StoreSubscription::RETRYING, 'grace_until' => null],
                $type === 'EXPIRED' => ['status' => StoreSubscription::EXPIRED],
                $type === 'REFUND' => ['status' => StoreSubscription::REVOKED, 'refunded_at' => now()],
                $type === 'REVOKE' => ['status' => StoreSubscription::REVOKED],
                default => [],
            });
            $row->forceFill(['last_event_at' => $signedAt])->save();

            return [$from, $row->user_id];
        }, self::ATTEMPTS);

        $this->recountIds($ids);
    }

    /**
     * Google Play's real-time developer notifications: a subscription's change, or a voided
     * purchase (a refund or chargeback), each read again from Google, which is the truth. An
     * event for a token a later purchase replaced changes nothing.
     *
     * @param  array<string, mixed>  $message  the Pub/Sub message's decoded data
     */
    public function googleNotification(array $message): void
    {
        if (($message['packageName'] ?? null) !== (string) config('services.google_play.package') || ! $this->play->configured()) {
            return;
        }

        $voided = (array) ($message['voidedPurchaseNotification'] ?? []);
        $changed = (array) ($message['subscriptionNotification'] ?? []);
        $token = $voided['purchaseToken'] ?? $changed['purchaseToken'] ?? null;

        if (! is_string($token) || $token === '') {
            return;
        }

        $state = $this->play->subscription($token);

        if ($voided !== [] && $state === null) {
            $state = [];
        }

        if ($state === null || ($state !== [] && ! $this->ourGoogleProduct($state))) {
            return;
        }

        if ($voided === []) {
            $this->ensureGoogleRow($token, $state);
        }

        $outcome = DB::transaction(function () use ($token, $state, $voided): ?array {
            $row = $voided !== [] ? $this->existingGoogleRow($token, $state, lock: true) : $this->lockedGoogleRow($token, $state);

            // A token Google no longer knows revokes only if it's the row's current one; a later one
            // than it is never undone by an event about it.
            if ($row === null || ($state === [] && $row->token_hash !== hash('sha256', $token)) || ($state !== [] && $this->supersededBy($row, $token, $state))) {
                return null;
            }

            $from = $row->user_id;
            $owner = $state['externalAccountIdentifiers']['obfuscatedExternalAccountId'] ?? null;
            $row->forceFill(['user_id' => self::userByToken($owner)->id ?? $from]);

            // A voided purchase ends Plus unless Google still has it running (a refund of an
            // earlier order, kept on).
            $running = $state !== [] && in_array($state['subscriptionState'] ?? null, ['SUBSCRIPTION_STATE_ACTIVE', 'SUBSCRIPTION_STATE_IN_GRACE_PERIOD'], true);

            if ($voided !== [] && ! $running) {
                $row->forceFill(['status' => StoreSubscription::REVOKED, 'refunded_at' => now()]);
            } elseif ($state !== []) {
                $this->applyGoogle($row, $token, $state);
            }

            $row->forceFill(['last_event_at' => now()])->save();

            return ['ids' => [$from, $row->user_id], 'row' => $row];
        }, self::ATTEMPTS);

        if ($outcome !== null) {
            $this->recountIds($outcome['ids']);

            if ($state !== []) {
                $this->acknowledgeIfWaiting($outcome['row'], $token, $state);
            }
        }
    }

    /**
     * The hourly backstop for Google's three days: a purchase still unacknowledged (the call
     * failed, and no sync or notification came since) is acknowledged now.
     */
    public function acknowledgePending(): int
    {
        if (! $this->play->configured()) {
            return 0;
        }

        $done = 0;

        foreach (StoreSubscription::query()->where('store', StoreSubscription::GOOGLE)->whereNull('acknowledged_at')->whereNotNull('purchase_token')->where('updated_at', '>', now()->subDays(3))->get() as $row) {
            $token = (string) $row->purchase_token;

            try {
                $state = $this->play->subscription($token);
            } catch (Refusal) {
                // Google didn't answer for this one: the next hour tries again.
                continue;
            }

            if ($state !== null && $this->acknowledgeIfWaiting($row, $token, $state)) {
                $done++;
            }
        }

        return $done;
    }

    /**
     * The account's Plus from the stores: the latest day any of its store subscriptions gives it,
     * worked out under the account's lock so two at once can't leave an older answer.
     */
    public function recount(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->first();

            if ($locked === null) {
                return;
            }

            $until = StoreSubscription::query()->with('user')->where('user_id', $user->id)->get()->map(fn (StoreSubscription $row): ?Carbon => $row->plusUntil())->filter()->max();
            $had = $locked->store_plus_until?->isFuture() === true;
            $has = $until?->isFuture() === true;

            if ($locked->store_plus_until?->toDateTimeString() !== $until?->toDateTimeString()) {
                $locked->forceFill(['store_plus_until' => $until])->save();
                AuditLog::record('billing.store_plus', $locked, null, after: ['until' => $until?->toIso8601ZuluString()]);
            }

            // Plus from a store starting or ending does what Stripe's does: an ending is recorded and
            // the editable projects settled (unless Plus still comes from elsewhere), and the
            // devices hear which projects are read-only now.
            if ($has && ! $had) {
                $locked->forceFill(['plus_ended_at' => null, 'plus_ended_reason' => null])->save();
            }

            if ($had && ! $has && ! $this->plans->isPlus($locked)) {
                $locked->forceFill(['plus_ended_at' => now(), 'plus_ended_reason' => 'canceled'])->save();
                $this->plans->settleEditable($locked);
            }

            if ($had !== $has) {
                $this->plans->readOnlyChanged($locked);
            }

            $user->setRawAttributes($locked->getAttributes(), true);
        }, self::ATTEMPTS);
    }

    /**
     * Every account that has used the app has its token (filled for any made in a deploy's gap,
     * between the migration and the code that sets it).
     */
    public static function fillTokens(): int
    {
        $filled = 0;

        foreach (User::query()->whereNull('store_account_token')->select('id')->lazyById() as $user) {
            $filled += User::query()->whereKey($user->id)->whereNull('store_account_token')->update(['store_account_token' => (string) Str::uuid()]);
        }

        return $filled;
    }

    private function ensureToken(User $user): void
    {
        if ($user->store_account_token === null) {
            User::query()->whereKey($user->id)->whereNull('store_account_token')->update(['store_account_token' => (string) Str::uuid()]);
            $user->refresh();
        }
    }

    /**
     * @param  list<int|null>  $ids
     */
    private function recountIds(array $ids): void
    {
        foreach (User::query()->whereKey(array_values(array_unique(array_filter($ids))))->get() as $user) {
            $this->recount($user);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function appleTransaction(string $jws): array
    {
        $transaction = AppleSignedData::decode($jws);
        $products = array_flip((array) config('services.app_store.products'));

        if (($transaction['bundleId'] ?? null) !== (string) config('services.apple.app_bundle_id') || ! isset($products[$transaction['productId'] ?? '']) || ! is_string($transaction['originalTransactionId'] ?? null)) {
            throw new Refusal(422, 'invalid_purchase');
        }

        return $transaction;
    }

    /**
     * The subscription's row is made, if there isn't one yet, before any transaction locks it: a
     * sync and the store's first notification at once then both find it, rather than both insert
     * (and one deadlock).
     */
    private function ensureRow(string $store, string $originalId): void
    {
        StoreSubscription::query()->insertOrIgnore(['store' => $store, 'original_id' => $originalId, 'product_id' => '', 'status' => StoreSubscription::EXPIRED, 'environment' => 'Production', 'will_renew' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function lock(string $store, string $originalId): StoreSubscription
    {
        return StoreSubscription::query()->where('store', $store)->where('original_id', $originalId)->lockForUpdate()->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function ensureGoogleRow(string $token, array $state): void
    {
        if ($this->existingGoogleRow($token, $state, lock: false) === null) {
            $this->ensureRow(StoreSubscription::GOOGLE, hash('sha256', $token));
        }
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function lockedGoogleRow(string $token, array $state): StoreSubscription
    {
        return $this->existingGoogleRow($token, $state, lock: true) ?? $this->lock(StoreSubscription::GOOGLE, hash('sha256', $token));
    }

    /**
     * A Google subscription keeps its row across resubscribes and plan changes: the token Google
     * links the new one to is followed back.
     *
     * @param  array<string, mixed>  $state
     */
    private function existingGoogleRow(string $token, array $state, bool $lock): ?StoreSubscription
    {
        $hashes = array_values(array_filter([hash('sha256', $token), is_string($state['linkedPurchaseToken'] ?? null) ? hash('sha256', $state['linkedPurchaseToken']) : null]));
        $query = StoreSubscription::query()->where('store', StoreSubscription::GOOGLE)
            ->where(fn ($match) => $match->whereIn('token_hash', $hashes)->orWhereIn('original_id', $hashes));

        return ($lock ? $query->lockForUpdate() : $query)->first();
    }

    /**
     * The row has moved on to a later purchase (a plan change or resubscribe) than this token:
     * a late event about the old one changes nothing.
     *
     * @param  array<string, mixed>  $state
     */
    private function supersededBy(StoreSubscription $row, string $token, array $state): bool
    {
        $current = $row->token_hash;
        $linked = is_string($state['linkedPurchaseToken'] ?? null) ? hash('sha256', $state['linkedPurchaseToken']) : null;

        return $current !== null && $current !== hash('sha256', $token) && $current !== $linked;
    }

    /**
     * A subscription row from Apple's signed data, and Apple's own status when the API answered.
     *
     * @param  array<string, mixed>  $transaction
     * @param  array<string, mixed>|null  $renewal
     * @param  array{status: int, transaction: string, renewal: string|null}|null  $status
     */
    private function applyApple(StoreSubscription $row, array $transaction, ?array $renewal, ?array $status): void
    {
        if ($status !== null) {
            $transaction = AppleSignedData::decode($status['transaction']);
            $renewal = $status['renewal'] !== null ? AppleSignedData::decode($status['renewal']) : $renewal;
        }

        $expires = self::millis($transaction['expiresDate'] ?? null);
        $grace = self::millis($renewal['gracePeriodExpiresDate'] ?? null);
        $products = array_flip((array) config('services.app_store.products'));

        $row->forceFill([
            'product_id' => (string) $transaction['productId'],
            'interval' => $products[$transaction['productId']] ?? null,
            'expires_at' => $expires,
            'grace_until' => $grace,
            'will_renew' => $renewal !== null ? (int) ($renewal['autoRenewStatus'] ?? 1) === 1 : $row->will_renew,
            'environment' => (string) ($transaction['environment'] ?? 'Production'),
            'status' => match (true) {
                isset($transaction['revocationDate']) => StoreSubscription::REVOKED,
                $status !== null && $status['status'] === 4 => StoreSubscription::GRACE,
                $status !== null && $status['status'] === 3 => StoreSubscription::RETRYING,
                $status !== null && $status['status'] === 5 => StoreSubscription::REVOKED,
                $expires !== null && $expires->isFuture() => StoreSubscription::ACTIVE,
                $grace !== null && $grace->isFuture() => StoreSubscription::GRACE,
                default => StoreSubscription::EXPIRED,
            },
        ]);
    }

    /**
     * @param  array<string, mixed>  $state  purchases.subscriptionsv2
     */
    private function applyGoogle(StoreSubscription $row, string $token, array $state): void
    {
        $item = (array) (((array) ($state['lineItems'] ?? []))[0] ?? []);
        $plan = (string) ($item['offerDetails']['basePlanId'] ?? '');
        $expires = is_string($item['expiryTime'] ?? null) ? Carbon::parse($item['expiryTime']) : null;
        $grace = ($state['subscriptionState'] ?? null) === 'SUBSCRIPTION_STATE_IN_GRACE_PERIOD';
        $hash = hash('sha256', $token);

        $row->forceFill([
            'purchase_token' => $token,
            'token_hash' => $hash,
            'product_id' => (string) ($item['productId'] ?? config('services.google_play.subscription')),
            'interval' => array_flip((array) config('services.google_play.base_plans'))[$plan] ?? null,
            'expires_at' => $expires,
            'will_renew' => (bool) ($item['autoRenewingPlan']['autoRenewEnabled'] ?? false),
            'environment' => isset($state['testPurchase']) ? 'Sandbox' : 'Production',
            'status' => match ((string) ($state['subscriptionState'] ?? '')) {
                // Cancelled but paid to its end: Plus until then.
                'SUBSCRIPTION_STATE_ACTIVE', 'SUBSCRIPTION_STATE_CANCELED' => $expires !== null && $expires->isFuture() ? StoreSubscription::ACTIVE : StoreSubscription::EXPIRED,
                'SUBSCRIPTION_STATE_IN_GRACE_PERIOD' => StoreSubscription::GRACE,
                'SUBSCRIPTION_STATE_ON_HOLD', 'SUBSCRIPTION_STATE_PAUSED' => StoreSubscription::RETRYING,
                default => StoreSubscription::EXPIRED,
            },
            // In grace, Plus runs until Google says it's on hold (its notification); at least a day.
            'grace_until' => $grace ? ($expires !== null && $expires->isFuture() ? $expires : now()->addDay()) : null,
            // Each token is acknowledged on its own: a new one (a plan change) waits for its own.
            'acknowledged_at' => ($state['acknowledgementState'] ?? null) === 'ACKNOWLEDGEMENT_STATE_ACKNOWLEDGED' ? ($row->token_hash === $hash ? $row->acknowledged_at ?? now() : now()) : ($row->token_hash === $hash ? $row->acknowledged_at : null),
        ]);
    }

    /**
     * Google refunds a purchase that isn't acknowledged within three days: acknowledged whenever
     * Google says it's still waiting, for the row's current token.
     *
     * @param  array<string, mixed>  $state
     */
    private function acknowledgeIfWaiting(StoreSubscription $row, string $token, array $state): bool
    {
        if (($state['acknowledgementState'] ?? null) !== 'ACKNOWLEDGEMENT_STATE_PENDING' || $row->token_hash !== hash('sha256', $token)) {
            return false;
        }

        if (! $this->play->acknowledge((string) config('services.google_play.subscription'), $token)) {
            return false;
        }

        StoreSubscription::query()->whereKey($row->id)->where('token_hash', $row->token_hash)->update(['acknowledged_at' => now()]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function ourGoogleProduct(array $state): bool
    {
        $item = (array) (((array) ($state['lineItems'] ?? []))[0] ?? []);

        return ($item['productId'] ?? null) === (string) config('services.google_play.subscription');
    }

    /**
     * A purchase may name the account it was made for (Apple's appAccountToken, Google's
     * obfuscatedAccountId, both set by the app to the account's storeAccountToken).
     */
    private function forThisAccount(User $user, mixed $token): bool
    {
        return ! is_string($token) || $token === '' || hash_equals((string) $user->store_account_token, strtolower($token));
    }

    private static function userByToken(mixed $token): ?User
    {
        return is_string($token) && $token !== '' ? User::query()->where('store_account_token', strtolower($token))->first() : null;
    }

    private static function millis(mixed $value): ?Carbon
    {
        return is_numeric($value) ? Carbon::createFromTimestampMs((int) $value) : null;
    }
}
