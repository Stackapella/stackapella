<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserLearningFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The course's progress, as the app keeps it (stars, XP, levels, badges, the weekly goal).
 *
 * @property int $id
 * @property int $user_id
 * @property array<string, mixed> $progress
 * @property int $version
 * @property string|null $last_change_id
 * @property int $epoch
 * @property Carbon|null $reset_at
 * @property array<string, mixed>|null $reset_snapshot
 * @property Carbon|null $undo_until
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[UseFactory(UserLearningFactory::class)]
final class UserLearning extends Model
{
    /** @use HasFactory<UserLearningFactory> */
    use HasFactory;

    protected $table = 'user_learning';

    protected $fillable = [
        'progress',
    ];

    protected function casts(): array
    {
        return [
            'progress' => 'array',
            'epoch' => 'integer',
            'reset_at' => 'datetime',
            'reset_snapshot' => 'array',
            'undo_until' => 'datetime',
        ];
    }
}
