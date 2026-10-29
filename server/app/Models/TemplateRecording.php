<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TemplateRecordingFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A creator's own recorded guide for one part of their template (Plus): uploaded like a take,
 * kept as AAC in `private`, heard through templates.show.
 *
 * @property int $id
 * @property string $uuid
 * @property int $template_id
 * @property int $user_id
 * @property string $part_key
 * @property string $status
 * @property string|null $failure
 * @property int $sample_rate
 * @property int $frames
 * @property int $offset_frames
 * @property int $upload_bytes
 * @property string $sha256
 * @property string $upload_key
 * @property string|null $file_key
 * @property int|null $stored_bytes
 * @property Carbon|null $ready_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Template $template
 * @property-read User $user
 */
#[UseFactory(TemplateRecordingFactory::class)]
final class TemplateRecording extends Model
{
    /** @use HasFactory<TemplateRecordingFactory> */
    use HasFactory;

    public const UPLOADING = 'uploading';

    public const PROCESSING = 'processing';

    public const READY = 'ready';

    public const FAILED = 'failed';

    protected $fillable = [
        'part_key',
        'sample_rate',
        'frames',
        'offset_frames',
        'upload_bytes',
        'sha256',
    ];

    protected function casts(): array
    {
        return [
            'sample_rate' => 'integer',
            'frames' => 'integer',
            'offset_frames' => 'integer',
            'upload_bytes' => 'integer',
            'stored_bytes' => 'integer',
            'ready_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Template, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
