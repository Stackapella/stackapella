<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSqid;
use Database\Factories\PushDeviceFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A device that gets practice reminders (a web push subscription). Its token lets the service
 * worker change or remove it without a session; only the token's hash is kept.
 *
 * @property int $id
 * @property int $user_id
 * @property string $endpoint
 * @property string $endpoint_hash
 * @property string $p256dh
 * @property string $auth
 * @property string $token_hash
 * @property Carbon|null $last_sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[UseFactory(PushDeviceFactory::class)]
final class PushDevice extends Model
{
    /** @use HasFactory<PushDeviceFactory> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'endpoint',
        'p256dh',
        'auth',
    ];

    protected function casts(): array
    {
        return [
            'last_sent_at' => 'datetime',
        ];
    }
}
