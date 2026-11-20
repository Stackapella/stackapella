<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AiUploadStatus;
use App\Models\Concerns\HasSqid;
use Database\Factories\AiUploadFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An AI guide's master from the batch renderer (the worker API): sent to `upload_key`, checked and copied to
 * `master_key`, then published as a guide (App\Jobs\PublishAiGuide). The renderer's checks and settings are kept for
 * Admin.
 *
 * @property int $id
 * @property int $template_version_id
 * @property string $part_key
 * @property int $ai_voice_id
 * @property AiUploadStatus $status
 * @property string|null $failure
 * @property string $upload_key
 * @property string|null $master_key
 * @property int $sample_rate
 * @property int $frames
 * @property int $bytes
 * @property string $sha256
 * @property array<string, mixed> $checks
 * @property array<string, mixed> $renderer
 * @property int|null $template_guide_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TemplateVersion $templateVersion
 * @property-read AiVoice $voice
 */
#[UseFactory(AiUploadFactory::class)]
final class AiUpload extends Model
{
    /** @use HasFactory<AiUploadFactory> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'part_key',
    ];

    protected function casts(): array
    {
        return [
            'status' => AiUploadStatus::class,
            'sample_rate' => 'integer',
            'frames' => 'integer',
            'bytes' => 'integer',
            'checks' => 'array',
            'renderer' => 'array',
        ];
    }

    /**
     * @return BelongsTo<TemplateVersion, $this>
     */
    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(TemplateVersion::class);
    }

    /**
     * @return BelongsTo<AiVoice, $this>
     */
    public function voice(): BelongsTo
    {
        return $this->belongsTo(AiVoice::class, 'ai_voice_id');
    }
}
