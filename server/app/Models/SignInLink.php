<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SignInLinkFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A one-time sign-in link sent by email (SignInByLink): 15 minutes, once. The token itself is
 * only in the email; what it carries is encrypted.
 *
 * @property int $id
 * @property string $email
 * @property string $token_hash
 * @property array{intended: string, invite: string|null, early: string|null} $context
 * @property Carbon $expires_at
 * @property Carbon|null $used_at
 * @property Carbon|null $created_at
 */
#[UseFactory(SignInLinkFactory::class)]
final class SignInLink extends Model
{
    /** @use HasFactory<SignInLinkFactory> */
    use HasFactory;

    public const MINUTES = 15;

    public const UPDATED_AT = null;

    protected $hidden = ['context'];

    protected function casts(): array
    {
        return [
            'context' => 'encrypted:array',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
