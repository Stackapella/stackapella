<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EmailSuppressionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An address we can't send to: Cloudflare suppressed it after a hard bounce or a complaint.
 *
 * @property int $id
 * @property string $email
 * @property string $reason
 * @property Carbon $suppressed_at
 */
#[UseFactory(EmailSuppressionFactory::class)]
final class EmailSuppression extends Model
{
    /** @use HasFactory<EmailSuppressionFactory> */
    use HasFactory;

    protected $fillable = [
        'email',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'suppressed_at' => 'datetime',
        ];
    }

    public static function record(string $email, string $reason): void
    {
        self::query()->upsert(
            [['email' => strtolower(trim($email)), 'reason' => $reason, 'suppressed_at' => now(), 'created_at' => now(), 'updated_at' => now()]],
            ['email'],
            ['reason', 'suppressed_at', 'updated_at'],
        );
    }

    public static function isSuppressed(string $email): bool
    {
        return self::query()->where('email', strtolower(trim($email)))->exists();
    }
}
