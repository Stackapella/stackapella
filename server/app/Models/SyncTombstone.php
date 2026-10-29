<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SyncTombstoneFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Something gone for good (a purged project or take, a deleted part), so sync.pull can tell the
 * other devices. Kept 90 days: a device away longer than that pulls everything again.
 *
 * @property int $id
 * @property int $user_id
 * @property string $kind
 * @property string $uuid
 * @property int $seq
 * @property Carbon|null $created_at
 */
#[UseFactory(SyncTombstoneFactory::class)]
final class SyncTombstone extends Model
{
    /** @use HasFactory<SyncTombstoneFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    public const KEEP_DAYS = 90;

    protected $fillable = [
        'kind',
        'uuid',
    ];
}
