<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SongRequestVoteFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One account's vote for a song request (N7); asking is a vote too.
 *
 * @property int $id
 * @property int $song_request_id
 * @property int $user_id
 * @property Carbon|null $created_at
 */
#[UseFactory(SongRequestVoteFactory::class)]
final class SongRequestVote extends Model
{
    /** @use HasFactory<SongRequestVoteFactory> */
    use HasFactory;

    public const UPDATED_AT = null;
}
