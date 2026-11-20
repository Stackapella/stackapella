<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSqid;
use Database\Factories\OwnChoirVoiceFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A singer's voice, kept for their next songs (Hillel, 2026-11-20: "i think we shoud store a model to make it easier"):
 * the reference the renderer prepared from their first clip (a short WAV and its own metadata), never the raw clip.
 * Private, only for their own songs; deleted on their delete, with the account, or after own_choir.voice_months unused.
 * `uploading` while the renderer sends it; `ready` is their voice (one at a time).
 *
 * @property int $id
 * @property int $user_id
 * @property string $status
 * @property string $upload_key
 * @property string|null $reference_key
 * @property int $sample_rate
 * @property int $frames
 * @property int $bytes
 * @property string $sha256
 * @property array<string, mixed> $meta
 * @property bool $wordless prepared from a clip of a part with no words (a hum)
 * @property Carbon|null $saved_at
 * @property Carbon|null $last_used_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[UseFactory(OwnChoirVoiceFactory::class)]
final class OwnChoirVoice extends Model
{
    /** @use HasFactory<OwnChoirVoiceFactory> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'sample_rate' => 'integer',
            'frames' => 'integer',
            'bytes' => 'integer',
            'meta' => 'array',
            'wordless' => 'boolean',
            'saved_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    /** When it's deleted if not used again. */
    public function expiresAt(): Carbon
    {
        return ($this->last_used_at ?? $this->saved_at ?? now())->copy()->addMonths((int) config('own_choir.voice_months'));
    }
}
