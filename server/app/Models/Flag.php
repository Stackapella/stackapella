<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\FlagFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A switch or dark feature's current value (config/flags.php; read through App\Support\Flags).
 *
 * @property int $id
 * @property string $name
 * @property string $value
 * @property string|null $reason
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $updater
 */
#[UseFactory(FlagFactory::class)]
final class Flag extends Model
{
    /** @use HasFactory<FlagFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'value',
        'reason',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
