<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ExercisePackFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A coach's exercise pack (F2): free or one-off, reviewed by Hillel before it's sold.
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $coach_id
 * @property string $title
 * @property string $description
 * @property int $price_cents
 * @property string $currency
 * @property list<array<string, mixed>> $exercises
 * @property string|null $approved_title
 * @property list<array<string, mixed>>|null $approved_exercises
 * @property string|null $approved_description
 * @property int|null $approved_price_cents
 * @property string|null $approved_currency
 * @property string $status
 * @property string|null $note
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $coach
 */
#[UseFactory(ExercisePackFactory::class)]
final class ExercisePack extends Model
{
    /** @use HasFactory<ExercisePackFactory> */
    use HasFactory;

    public const DRAFT = 'draft';

    public const SUBMITTED = 'submitted';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    protected $fillable = [
        'title',
        'description',
        'price_cents',
        'currency',
        'exercises',
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'exercises' => 'array',
            'approved_exercises' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    /**
     * @return HasMany<PackPurchase, $this>
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(PackPurchase::class, 'pack_id');
    }
}
