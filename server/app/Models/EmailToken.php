<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EmailTokenFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The token behind a link in one of our emails (verify an address, confirm a new one). Only its
 * hash is kept; it works once, before it expires.
 *
 * @property int $id
 * @property int $user_id
 * @property string $purpose
 * @property string $email
 * @property string $token_hash
 * @property Carbon $expires_at
 * @property Carbon|null $used_at
 * @property Carbon $created_at
 * @property-read User $user
 */
#[UseFactory(EmailTokenFactory::class)]
final class EmailToken extends Model
{
    /** @use HasFactory<EmailTokenFactory> */
    use HasFactory;

    public const VERIFY = 'verify';

    public const EMAIL_CHANGE = 'email_change';

    public const UPDATED_AT = null;

    protected $fillable = [
        'purpose',
        'email',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
