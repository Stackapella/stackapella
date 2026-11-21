<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OwnChoirFreeSongFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A free choir song used, by a keyed hash of an email address or a sign-in account id (OwnChoir::freeKeys): kept after
 * the account is deleted, so the free song is once per address ever.
 *
 * @property int $id
 * @property string $key_hash
 * @property int|null $own_choir_request_id
 * @property Carbon|null $created_at
 */
#[UseFactory(OwnChoirFreeSongFactory::class)]
final class OwnChoirFreeSong extends Model
{
    /** @use HasFactory<OwnChoirFreeSongFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = ['key_hash'];
}
