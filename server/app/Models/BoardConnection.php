<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BoardConnectionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One side of two people connected for friends' boards (a row each way), and how: an accepted
 * challenge now. Singing on the same song and sharing a group connect people too, worked out as the
 * boards are read (Boards::friendIds), so leaving a project or group ends it at once.
 *
 * @property int $id
 * @property int $user_id
 * @property int $friend_id
 * @property string $source
 * @property Carbon|null $created_at
 */
#[UseFactory(BoardConnectionFactory::class)]
final class BoardConnection extends Model
{
    /** @use HasFactory<BoardConnectionFactory> */
    use HasFactory;

    public const CHALLENGE = 'challenge';

    public $timestamps = false;
}
