<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CorrectionKind;
use App\Enums\CorrectionState;
use App\Models\Concerns\HasSqid;
use Database\Factories\CorrectionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A reader's correction or suggestion on one of our pages (docs/corrections-plan.md), and the team's decision on it.
 * Nothing about the reader is kept. The decision's fields are set with forceFill by DecideCorrection.
 *
 * @property int $id
 * @property CorrectionKind $kind
 * @property CorrectionState $state
 * @property string $text
 * @property string|null $source
 * @property string $page_type
 * @property string $page_path
 * @property string $page_title
 * @property string $lang
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $decider
 */
#[UseFactory(CorrectionFactory::class)]
final class Correction extends Model
{
    /** @use HasFactory<CorrectionFactory> */
    use HasFactory;

    use HasSqid;

    /** The pages a correction can be about. */
    public const PAGE_TYPES = ['song', 'part', 'course', 'lesson', 'tool'];

    protected $fillable = [
        'kind',
        'text',
        'source',
        'page_type',
        'page_path',
        'page_title',
        'lang',
    ];

    protected function casts(): array
    {
        return [
            'kind' => CorrectionKind::class,
            'state' => CorrectionState::class,
            'decided_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
