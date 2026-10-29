<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSqid;
use Database\Factories\MudeoBatchFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Up to 50 of mudeo's users Hillel emailed in one Bcc, by hand (docs/mudeo-transition-plan.md).
 *
 * @property int $id
 * @property int $count
 * @property string $mode
 * @property string $status
 * @property int $delivered
 * @property int $bounced
 * @property int $failed
 * @property string|null $error
 * @property Carbon $sent_at
 * @property Carbon|null $undone_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[UseFactory(MudeoBatchFactory::class)]
final class MudeoBatch extends Model
{
    /** @use HasFactory<MudeoBatchFactory> */
    use HasFactory;

    use HasSqid;

    /** Sent by the app, or marked sent by hand (the copy fallback). */
    public const APP = 'app';

    public const MANUAL = 'manual';

    public const SENDING = 'sending';

    public const SENT = 'sent';

    public const FAILED = 'failed';

    public function bounceRate(): float
    {
        return $this->count > 0 ? round($this->bounced / $this->count, 3) : 0.0;
    }

    protected function casts(): array
    {
        return [
            'count' => 'integer',
            'delivered' => 'integer',
            'bounced' => 'integer',
            'failed' => 'integer',
            'sent_at' => 'datetime',
            'undone_at' => 'datetime',
        ];
    }
}
