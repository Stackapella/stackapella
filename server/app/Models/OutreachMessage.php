<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSqid;
use Database\Factories\OutreachMessageFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One email to an organization: a draft Hillel edits, then sent by his own press (or marked sent,
 * when he used their contact form), and what became of it. A first email, or its one follow-up.
 *
 * @property int $id
 * @property int $outreach_target_id
 * @property string $kind first|follow_up
 * @property string $subject
 * @property string $body
 * @property string $status draft|sent|delivered|bounced
 * @property string|null $to_email
 * @property string|null $message_id
 * @property string|null $sent_via email|form
 * @property Carbon|null $sent_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $bounced_at
 * @property string|null $bounce_note
 * @property int|null $sent_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read OutreachTarget $target
 */
#[UseFactory(OutreachMessageFactory::class)]
final class OutreachMessage extends Model
{
    /** @use HasFactory<OutreachMessageFactory> */
    use HasFactory;

    use HasSqid;

    public const FIRST = 'first';

    public const FOLLOW_UP = 'follow_up';

    protected $fillable = ['kind', 'subject', 'body'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'delivered_at' => 'datetime', 'bounced_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<OutreachTarget, $this>
     */
    public function target(): BelongsTo
    {
        return $this->belongsTo(OutreachTarget::class, 'outreach_target_id');
    }
}
