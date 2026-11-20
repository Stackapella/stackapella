<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSqid;
use Database\Factories\OwnChoirPartFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One part of a song in a singer's own voice (track B), private to them: sent by the renderer (`upload_key`), checked
 * (`master_key`), then encoded as an AAC on the private bucket (`media_key`) and the master deleted.
 *
 * @property int $id
 * @property int $own_choir_request_id
 * @property string $part_key
 * @property string $status uploading, processing, ready or failed
 * @property string|null $failure
 * @property string $upload_key
 * @property string|null $master_key
 * @property string|null $media_key
 * @property int $sample_rate
 * @property int $frames
 * @property int $bytes
 * @property string $sha256
 * @property array<string, mixed> $checks
 * @property array<string, mixed> $renderer
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read OwnChoirRequest $request
 */
#[UseFactory(OwnChoirPartFactory::class)]
final class OwnChoirPart extends Model
{
    /** @use HasFactory<OwnChoirPartFactory> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'part_key',
    ];

    protected function casts(): array
    {
        return [
            'sample_rate' => 'integer',
            'frames' => 'integer',
            'bytes' => 'integer',
            'checks' => 'array',
            'renderer' => 'array',
        ];
    }

    /**
     * @return BelongsTo<OwnChoirRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(OwnChoirRequest::class, 'own_choir_request_id');
    }
}
