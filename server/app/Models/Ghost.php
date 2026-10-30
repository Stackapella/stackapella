<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\GhostFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The pitch line of a singer's best take on a part, for game mode's Sing lane: their own only.
 * `pitches` is little-endian Int16 (MIDI times 100 a frame, 0 for none), `rate` frames a second.
 *
 * @property int $id
 * @property int $user_id
 * @property int $template_version_id
 * @property string $part_key
 * @property int $scoring_version
 * @property int $score
 * @property int $rate
 * @property string $start_seconds
 * @property int $key_offset
 * @property string $pitches
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read TemplateVersion $templateVersion
 */
#[UseFactory(GhostFactory::class)]
final class Ghost extends Model
{
    /** @use HasFactory<GhostFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<TemplateVersion, $this>
     */
    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(TemplateVersion::class);
    }
}
