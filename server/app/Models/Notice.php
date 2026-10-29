<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSqid;
use Database\Factories\NoticeFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A statement of reasons (the DSA; docs/trust-and-safety-plan.md, "Legal duties"): what was done
 * to someone's harmony, account or coaching, why, whether it was automatic, and their appeal with
 * its decision.
 *
 * @property int $id
 * @property int $user_id
 * @property string $token_hash
 * @property string $kind
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string $rule
 * @property string|null $note
 * @property bool $automatic
 * @property string|null $appeal_text
 * @property Carbon|null $appealed_at
 * @property string|null $decision
 * @property string|null $decision_note
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Model|null $subject
 */
#[UseFactory(NoticeFactory::class)]
final class Notice extends Model
{
    /** @use HasFactory<NoticeFactory> */
    use HasFactory;

    use HasSqid;

    public const HARMONY_HIDDEN = 'harmony_hidden';

    public const HARMONY_REMOVED = 'harmony_removed';

    public const ACCOUNT_LIMITED = 'account_limited';

    public const ACCOUNT_SUSPENDED = 'account_suspended';

    public const COACH_PAUSED = 'coach_paused';

    public const COACH_SUSPENDED = 'coach_suspended';

    /** A comment in the gallery hidden by a moderator (N1). */
    public const COMMENT_HIDDEN = 'comment_hidden';

    /** A template in the library hidden by a moderator (N3). */
    public const TEMPLATE_HIDDEN = 'template_hidden';

    /** An idea, or one voice on it, hidden by a moderator (Ideas). */
    public const IDEA_HIDDEN = 'idea_hidden';

    public const IDEA_VOICE_HIDDEN = 'idea_voice_hidden';

    /** A voice taken out of a virtual choir by a moderator (N6). */
    public const CHOIR_VOICE_REMOVED = 'choir_voice_removed';

    public const KEPT = 'kept';

    public const REVERSED = 'reversed';

    /** An appeal can be made this long after the notice. */
    public const APPEAL_MONTHS = 6;

    protected $fillable = ['kind', 'rule', 'note'];

    protected function casts(): array
    {
        return [
            'automatic' => 'boolean',
            'appealed_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function canAppeal(): bool
    {
        return $this->appealed_at === null && $this->created_at !== null && $this->created_at->gt(now()->subMonths(self::APPEAL_MONTHS));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
