<?php

declare(strict_types=1);

namespace App\Support\Accounts;

use App\Exceptions\Refusal;
use App\Models\EmailToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The links in our emails: a random token, kept only as a hash, that works once before it expires
 * (a 410 link_expired otherwise, used or not, so a link can't be told apart from one never made).
 */
final class EmailTokens
{
    /**
     * @return string the token to put in the link
     */
    public function issue(User $user, string $purpose, string $email, int $hours, bool $onlyNewest = true): string
    {
        // Only the newest link of each kind works (but every undo link keeps working: ChangeEmail).
        if ($onlyNewest) {
            EmailToken::query()->where('user_id', $user->id)->where('purpose', $purpose)->whereNull('used_at')->delete();
        }

        $token = Str::random(48);
        $row = new EmailToken(['purpose' => $purpose, 'email' => strtolower($email), 'expires_at' => now()->addHours($hours)]);
        $row->forceFill(['user_id' => $user->id, 'token_hash' => hash('sha256', $token)])->save();

        return $token;
    }

    /**
     * Use a token up, or refuse it.
     */
    public function redeem(string $token, string $purpose): EmailToken
    {
        return DB::transaction(function () use ($token, $purpose): EmailToken {
            $row = EmailToken::query()->with('user')->where('token_hash', hash('sha256', $token))->where('purpose', $purpose)->lockForUpdate()->first();

            if ($row === null || $row->used_at !== null || $row->expires_at->isPast()) {
                throw new Refusal(410, 'link_expired');
            }

            $row->forceFill(['used_at' => now()])->save();

            return $row;
        });
    }
}
