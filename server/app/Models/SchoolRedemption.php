<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSqid;
use Database\Factories\SchoolRedemptionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A student's free year of Plus from a class code (docs/school-offer-plan.md): one per account,
 * ever. Only Admin sees who; the teacher sees a seat used.
 *
 * @property int $id
 * @property int $school_class_id
 * @property int $user_id
 * @property Carbon $plus_until
 * @property Carbon|null $ended_at Admin's End (misuse)
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SchoolClass $schoolClass
 * @property-read User $user
 */
#[UseFactory(SchoolRedemptionFactory::class)]
final class SchoolRedemption extends Model
{
    /** @use HasFactory<SchoolRedemptionFactory> */
    use HasFactory;

    use HasSqid;

    protected function casts(): array
    {
        return ['plus_until' => 'datetime', 'ended_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
