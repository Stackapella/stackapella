<?php

declare(strict_types=1);

namespace App\Support\Usage;

use App\Exceptions\Refusal;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Jobs in flight per account (the CPU review, 2026-11-21): a new take, mix or guide recording waits while
 * usage.processing_at_once of that kind of the account's are still being made (`429 rate_limited`, try again shortly),
 * so one account can't fill the queue. Only rows touched in the last quarter hour count, so one stuck never blocks.
 */
final class InFlight
{
    /**
     * @param  class-string<Model>  $model  a model with user_id and status
     */
    public static function ensure(User $user, string $model, string $processing = 'processing'): void
    {
        $busy = $model::query()->where('user_id', $user->id)->where('status', $processing)->where('updated_at', '>', now()->subMinutes(15))->count();

        if ($busy >= (int) config('usage.processing_at_once')) {
            throw new Refusal(429, 'rate_limited', ['retryAfter' => 30], ['Retry-After' => '30']);
        }
    }
}
