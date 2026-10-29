<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EngagementReportFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * "Report a problem" on an engagement, for Hillel.
 *
 * @property int $id
 * @property int $engagement_id
 * @property int $user_id
 * @property string $text
 * @property Carbon|null $resolved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Engagement $engagement
 * @property-read User $user
 */
#[UseFactory(EngagementReportFactory::class)]
final class EngagementReport extends Model
{
    /** @use HasFactory<EngagementReportFactory> */
    use HasFactory;

    protected $fillable = [
        'text',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Engagement, $this>
     */
    public function engagement(): BelongsTo
    {
        return $this->belongsTo(Engagement::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
