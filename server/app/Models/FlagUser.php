<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\FlagUserFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A dark feature turned on for one person before everyone.
 *
 * @property int $id
 * @property string $flag
 * @property int $user_id
 * @property Carbon $created_at
 */
#[UseFactory(FlagUserFactory::class)]
final class FlagUser extends Model
{
    /** @use HasFactory<FlagUserFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'flag',
        'user_id',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
