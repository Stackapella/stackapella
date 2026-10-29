<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSqid;
use Database\Factories\TeacherLinkFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Send to my teacher (Plus): a singer's link to their own teacher, and the engagement it began.
 *
 * @property int $id
 * @property int $singer_id
 * @property int|null $teacher_id
 * @property int|null $engagement_id
 * @property string $name
 * @property string $token_hash
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $singer
 * @property-read User|null $teacher
 * @property-read Engagement|null $engagement
 */
#[UseFactory(TeacherLinkFactory::class)]
final class TeacherLink extends Model
{
    /** @use HasFactory<TeacherLinkFactory> */
    use HasFactory;

    use HasSqid;

    public const PENDING = 'pending';

    public const ACTIVE = 'active';

    public const ENDED = 'ended';

    protected $fillable = [
        'name',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function singer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'singer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * @return BelongsTo<Engagement, $this>
     */
    public function engagement(): BelongsTo
    {
        return $this->belongsTo(Engagement::class, 'engagement_id');
    }
}
