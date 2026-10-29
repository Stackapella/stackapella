<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSqid;
use Database\Factories\ReportFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A report from anyone, signed in or not (docs/trust-and-safety-plan.md, "Uploads and shared
 * audio"). One per person per item: `reporter_key` is the account, or the network's HMAC.
 *
 * @property int $id
 * @property string $reportable_type
 * @property int $reportable_id
 * @property int|null $reporter_id
 * @property string $reporter_key
 * @property string $ip_hash
 * @property string $reason
 * @property string $note
 * @property string $status
 * @property int|null $resolved_by
 * @property Carbon|null $resolved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model $reportable
 * @property-read User|null $reporter
 */
#[UseFactory(ReportFactory::class)]
final class Report extends Model
{
    /** @use HasFactory<ReportFactory> */
    use HasFactory;

    use HasSqid;

    public const REASONS = ['copyright', 'offensive', 'spam', 'personal', 'other'];

    public const OPEN = 'open';

    public const UPHELD = 'upheld';

    public const DISMISSED = 'dismissed';

    protected $fillable = [
        'reason',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }
}
