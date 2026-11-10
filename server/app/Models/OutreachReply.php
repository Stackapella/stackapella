<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSqid;
use Database\Factories\OutreachReplyFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A reply from an organization, with its text: from inbound mail, or logged by hand.
 *
 * @property int $id
 * @property int|null $outreach_target_id
 * @property int|null $outreach_message_id
 * @property string $from_email
 * @property string|null $subject
 * @property string $body
 * @property Carbon $received_at
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read OutreachTarget|null $target
 */
#[UseFactory(OutreachReplyFactory::class)]
final class OutreachReply extends Model
{
    /** @use HasFactory<OutreachReplyFactory> */
    use HasFactory;

    use HasSqid;

    protected $fillable = ['from_email', 'subject', 'body'];

    protected function casts(): array
    {
        return ['received_at' => 'datetime', 'read_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<OutreachTarget, $this>
     */
    public function target(): BelongsTo
    {
        return $this->belongsTo(OutreachTarget::class, 'outreach_target_id');
    }
}
