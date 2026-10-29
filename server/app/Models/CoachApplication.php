<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSqid;
use Database\Factories\CoachApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An application to coach (docs/coaching-marketplace-plan.md F1): the answers, a sample review of
 * the sample take, and Hillel's decision.
 *
 * @property int $id
 * @property int $user_id
 * @property array<string, mixed> $answers
 * @property array<string, mixed> $sample_review
 * @property int $agreement_version
 * @property string $status
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[UseFactory(CoachApplicationFactory::class)]
final class CoachApplication extends Model
{
    /** @use HasFactory<CoachApplicationFactory> */
    use HasFactory;

    use HasSqid;

    public const SUBMITTED = 'submitted';

    public const APPROVED = 'approved';

    public const DECLINED = 'declined';

    protected $fillable = [
        'answers',
        'sample_review',
        'agreement_version',
    ];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'sample_review' => 'array',
            'agreement_version' => 'integer',
            'decided_at' => 'datetime',
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
