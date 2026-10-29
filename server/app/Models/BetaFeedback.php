<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSqid;
use Database\Factories\BetaFeedbackFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A "Tell Hillel" note from a beta singer, with where in the song it's about.
 *
 * @property int $id
 * @property int $beta_singer_id
 * @property string|null $template_slug
 * @property string|null $part_key
 * @property string|null $beat
 * @property string $text
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BetaSinger $singer
 */
#[UseFactory(BetaFeedbackFactory::class)]
final class BetaFeedback extends Model
{
    /** @use HasFactory<BetaFeedbackFactory> */
    use HasFactory;

    use HasSqid;

    protected $table = 'beta_feedback';

    protected $fillable = [
        'template_slug',
        'part_key',
        'beat',
        'text',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function markRead(): void
    {
        if ($this->read_at === null) {
            $this->forceFill(['read_at' => now()])->save();
        }
    }

    /**
     * @return BelongsTo<BetaSinger, $this>
     */
    public function singer(): BelongsTo
    {
        return $this->belongsTo(BetaSinger::class, 'beta_singer_id');
    }
}
