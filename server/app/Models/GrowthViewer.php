<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\GrowthViewerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A growth viewer (App\Support\Admin\GrowthViewers): may read Admin's anonymous growth numbers, nothing else.
 *
 * @property int $id
 * @property string $email
 * @property string|null $google_id
 * @property Carbon $granted_at
 * @property Carbon|null $revoked_at
 */
final class GrowthViewer extends Model
{
    /** @use HasFactory<GrowthViewerFactory> */
    use HasFactory;

    protected $fillable = ['email', 'google_id', 'granted_at', 'revoked_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
