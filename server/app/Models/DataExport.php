<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DataExportFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One "Download my data": prepared in the background, kept 7 days under exports/ in `private`.
 *
 * @property int $id
 * @property int $user_id
 * @property string $status
 * @property string|null $file_key
 * @property Carbon|null $ready_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[UseFactory(DataExportFactory::class)]
final class DataExport extends Model
{
    /** @use HasFactory<DataExportFactory> */
    use HasFactory;

    public const DAYS = 7;

    protected $fillable = [
        'status',
    ];

    protected function casts(): array
    {
        return [
            'ready_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
