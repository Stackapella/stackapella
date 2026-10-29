<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSqid;
use Database\Factories\SongRequestFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A song someone wants to sing (N7), with everyone's votes, and where Hillel has got with it.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $title
 * @property string $match_key
 * @property string|null $writer
 * @property string|null $why
 * @property string $status
 * @property int|null $template_id
 * @property int|null $merged_into_id
 * @property int $votes_count
 * @property Carbon|null $held_at
 * @property Carbon|null $hidden_at
 * @property Carbon|null $released_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Template|null $template
 * @property-read SongRequest|null $mergedInto
 */
#[UseFactory(SongRequestFactory::class)]
final class SongRequest extends Model
{
    /** @use HasFactory<SongRequestFactory> */
    use HasFactory;

    use HasSqid;

    public const STATUSES = ['requested', 'checking', 'arranging', 'ready', 'cant'];

    protected function casts(): array
    {
        return [
            'votes_count' => 'integer',
            'held_at' => 'datetime',
            'hidden_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    /**
     * The words that make two titles the same request: lower case, letters and digits only.
     */
    public static function matchKey(string $title): string
    {
        return mb_substr((string) preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($title)), 0, 120);
    }

    /**
     * @return BelongsTo<SongRequest, $this>
     */
    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_id');
    }

    /**
     * @return BelongsTo<Template, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }
}
