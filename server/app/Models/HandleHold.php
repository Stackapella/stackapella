<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\HandleHoldFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A handle no one can take: a deleted account's (forever), or one its owner changed away from
 * (for HOLD_DAYS, and they may take it back).
 *
 * @property int $id
 * @property string $handle
 * @property int|null $user_id
 * @property Carbon|null $held_until
 */
#[UseFactory(HandleHoldFactory::class)]
final class HandleHold extends Model
{
    /** @use HasFactory<HandleHoldFactory> */
    use HasFactory;

    public const HOLD_DAYS = 30;

    protected $fillable = [
        'handle',
        'held_until',
    ];

    protected function casts(): array
    {
        return [
            'held_until' => 'datetime',
        ];
    }
}
