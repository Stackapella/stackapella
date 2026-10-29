<?php

declare(strict_types=1);

namespace App\Support\Usage;

use App\Exceptions\Refusal;
use App\Models\User;
use App\Support\Trust\Limits;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The daily cap on bytes uploaded (the upload-bytes limiter: free_mb or plus_mb), shared by every
 * audio upload that starts with a presigned PUT: takes, idea voices and coaching review media.
 */
final class UploadBytes
{
    public function __construct(private readonly Plans $plans, private readonly Limits $limits) {}

    /**
     * Refuses (429) an upload that would pass today's cap; counts it once the row is saved, so a
     * rolled-back attempt (its retry) never counts twice.
     */
    public function count(User $user, int $bytes): void
    {
        $rule = $this->plans->isPlus($user) ? 'plus_mb' : 'free_mb';
        $key = "upload-bytes:{$rule}:u{$user->id}";

        if (RateLimiter::attempts($key) + $bytes > $this->limits->max('upload-bytes', $rule) * 1_000_000) {
            $wait = max(1, RateLimiter::availableIn($key));

            throw new Refusal(429, 'rate_limited', ['retryAfter' => $wait], ['Retry-After' => (string) $wait]);
        }

        DB::afterCommit(fn () => RateLimiter::increment($key, 86400, $bytes));
    }
}
