<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BoardChallengeFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A challenge to sing a song's part, sent as a link (its token is kept only as a hash).
 *
 * @property int $id
 * @property int $user_id
 * @property int $template_id
 * @property string $part_key
 * @property string $token_hash
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Template $template
 */
#[UseFactory(BoardChallengeFactory::class)]
final class BoardChallenge extends Model
{
    /** @use HasFactory<BoardChallengeFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Template, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }
}
