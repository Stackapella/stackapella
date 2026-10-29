<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PackPurchaseFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An exercise pack someone has, bought or free.
 *
 * @property int $id
 * @property int $pack_id
 * @property int $user_id
 * @property int|null $engagement_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ExercisePack $pack
 * @property-read User $user
 */
#[UseFactory(PackPurchaseFactory::class)]
final class PackPurchase extends Model
{
    /** @use HasFactory<PackPurchaseFactory> */
    use HasFactory;

    protected $fillable = [
        'pack_id',
        'user_id',
        'engagement_id',
    ];

    /**
     * @return BelongsTo<ExercisePack, $this>
     */
    public function pack(): BelongsTo
    {
        return $this->belongsTo(ExercisePack::class, 'pack_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
