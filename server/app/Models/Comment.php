<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSqid;
use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A comment on a harmony in the gallery (N1): plain text, up to 500 characters, held by the word
 * lists until reviewed, deletable by its author or the harmony's owner.
 *
 * @property int $id
 * @property int $harmony_id
 * @property int $user_id
 * @property string $body
 * @property Carbon|null $held_at
 * @property Carbon|null $hidden_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[UseFactory(CommentFactory::class)]
final class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory;

    use HasSqid;

    protected $fillable = ['body'];

    public const MAX = 500;

    protected function casts(): array
    {
        return [
            'held_at' => 'datetime',
            'hidden_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Harmony, $this>
     */
    public function harmony(): BelongsTo
    {
        return $this->belongsTo(Harmony::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
