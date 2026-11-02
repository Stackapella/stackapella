<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSqid;
use Database\Factories\SchoolTeacherFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A teacher on the school offer (docs/school-offer-plan.md): their codes work once Admin approves
 * them, and their seats over all codes stay within the allowance (300 unless Admin raises it).
 *
 * @property int $id
 * @property int $user_id
 * @property Carbon|null $approved_at
 * @property int|null $approved_by
 * @property Carbon|null $told_at when they were emailed that their codes work (once)
 * @property int $seat_allowance
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[UseFactory(SchoolTeacherFactory::class)]
final class SchoolTeacher extends Model
{
    /** @use HasFactory<SchoolTeacherFactory> */
    use HasFactory;

    use HasSqid;

    public const ALLOWANCE = 300;

    /** Approval is set by Admin's action only (forceFill), never filled. */
    protected $fillable = ['user_id', 'seat_allowance'];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime', 'told_at' => 'datetime', 'seat_allowance' => 'integer'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
