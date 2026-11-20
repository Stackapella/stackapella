<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OwnChoirConsentFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A person's yes to Hear yourself as a choir's terms (track B): the version, and when.
 *
 * @property int $id
 * @property int $user_id
 * @property int $version
 * @property Carbon $accepted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[UseFactory(OwnChoirConsentFactory::class)]
final class OwnChoirConsent extends Model
{
    /** @use HasFactory<OwnChoirConsentFactory> */
    use HasFactory;

    protected $fillable = [
        'version',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'accepted_at' => 'datetime',
        ];
    }
}
