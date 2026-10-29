<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubmissionStatus;
use App\Models\Concerns\HasSqid;
use Database\Factories\BetaSubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A take a beta singer sent for one assigned part, with what the device measured, and Hillel's
 * review. Takes are dry: the light fixes (offset, trims, gain) are applied only when a guide is
 * published from it.
 *
 * @property int $id
 * @property int $beta_assignment_id
 * @property int|null $template_version_id
 * @property string|null $part_key
 * @property SubmissionStatus $status
 * @property string $music_key
 * @property int $sample_rate
 * @property int $frames
 * @property int $latency_frames
 * @property int $bytes
 * @property string $sha256
 * @property string|null $device
 * @property string|null $mic
 * @property array<string, mixed>|null $scores
 * @property string|null $singer_note
 * @property string $master_key
 * @property list<float>|null $peaks
 * @property string|null $loudness_dbfs
 * @property string|null $peak_dbfs
 * @property string $publish_status
 * @property int $payable_cents
 * @property string|null $failure
 * @property int $offset_ms
 * @property int $trim_start_ms
 * @property int $trim_end_ms
 * @property string $gain_db
 * @property string|null $admin_note
 * @property string|null $admin_note_beat
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BetaAssignment $assignment
 * @property-read TemplateVersion|null $templateVersion
 * @property-read TemplateGuide|null $guide
 */
#[UseFactory(BetaSubmissionFactory::class)]
final class BetaSubmission extends Model
{
    /** @use HasFactory<BetaSubmissionFactory> */
    use HasFactory;

    use HasSqid;

    /** @var array<string, mixed> */
    protected $attributes = [
        'publish_status' => 'none',
        'payable_cents' => 0,
        'offset_ms' => 0,
        'trim_start_ms' => 0,
        'trim_end_ms' => 0,
        'gain_db' => 0,
    ];

    protected $fillable = [
        'music_key',
        'sample_rate',
        'frames',
        'latency_frames',
        'bytes',
        'sha256',
        'device',
        'mic',
        'scores',
        'singer_note',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubmissionStatus::class,
            'sample_rate' => 'integer',
            'frames' => 'integer',
            'latency_frames' => 'integer',
            'bytes' => 'integer',
            'scores' => 'array',
            'peaks' => 'array',
            'offset_ms' => 'integer',
            'trim_start_ms' => 'integer',
            'trim_end_ms' => 'integer',
            'reviewed_at' => 'datetime',
            'paid_at' => 'datetime',
            'payable_cents' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<BetaAssignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(BetaAssignment::class, 'beta_assignment_id');
    }

    /**
     * @return BelongsTo<TemplateVersion, $this>
     */
    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(TemplateVersion::class);
    }

    /**
     * The guide published from this take, once accepted.
     *
     * @return HasOne<TemplateGuide, $this>
     */
    public function guide(): HasOne
    {
        return $this->hasOne(TemplateGuide::class, 'beta_submission_id');
    }

    public function durationSeconds(): float
    {
        return $this->sample_rate > 0 ? $this->frames / $this->sample_rate : 0.0;
    }
}
