<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSqid;
use Database\Factories\PartScoreFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Someone's best score on one part of one version of a song, under one scoring version (the part
 * leaderboards; App\Actions\Boards\Boards), with the facts that made it count.
 *
 * @property int $id
 * @property int $user_id
 * @property int $template_id
 * @property int $template_version_id
 * @property string $part_key
 * @property int $scoring_version
 * @property int $score
 * @property string $mode
 * @property int $key_offset
 * @property string $speed
 * @property bool $guide_muted
 * @property bool $timing_checked
 * @property string|null $output
 * @property int|null $take_id
 * @property int|null $alignment_ms
 * @property int $pitch_detector_version
 * @property list<array<string, mixed>>|null $notes
 * @property string|null $app_version
 * @property Carbon|null $flagged_at
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $hidden_at
 * @property Carbon $recorded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Template $template
 */
#[UseFactory(PartScoreFactory::class)]
final class PartScore extends Model
{
    /** @use HasFactory<PartScoreFactory> */
    use HasFactory;

    use HasSqid;

    protected function casts(): array
    {
        return [
            'guide_muted' => 'boolean',
            'timing_checked' => 'boolean',
            'flagged_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'hidden_at' => 'datetime',
            'recorded_at' => 'datetime',
            'notes' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Take, $this>
     */
    public function take(): BelongsTo
    {
        return $this->belongsTo(Take::class);
    }

    /**
     * @return BelongsTo<TemplateVersion, $this>
     */
    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(TemplateVersion::class);
    }

    /**
     * @return BelongsTo<Template, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }
}
