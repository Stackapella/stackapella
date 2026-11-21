<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UploadLinkFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A presigned upload link (App\Support\Storage\Uploads): what the sweeper checks.
 *
 * @property int $id
 * @property string $upload_key
 * @property int $bytes
 * @property Carbon $expires_at
 * @property Carbon|null $created_at
 */
#[UseFactory(UploadLinkFactory::class)]
final class UploadLink extends Model
{
    /** @use HasFactory<UploadLinkFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = ['upload_key', 'bytes', 'expires_at'];

    protected function casts(): array
    {
        return ['bytes' => 'integer', 'expires_at' => 'datetime'];
    }
}
