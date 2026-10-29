<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CoachProfileFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An approved coach (F1): their public profile, their Stripe Express account and whether it can take
 * charges, and their rating.
 *
 * @property int $id
 * @property int $user_id
 * @property string $status
 * @property string $bio
 * @property list<string> $voice_types
 * @property list<string> $styles
 * @property list<string> $languages
 * @property string $timezone
 * @property int $cancellation_hours
 * @property string|null $stripe_account_id
 * @property bool $charges_enabled
 * @property bool $payouts_enabled
 * @property bool $details_submitted
 * @property string|null $rating_average
 * @property int $rating_count
 * @property Carbon|null $approved_at
 * @property Carbon|null $featured_at
 * @property int|null $from_cents
 * @property string|null $from_currency
 * @property int|null $turnaround_days
 * @property Carbon|null $next_lesson_at
 * @property Carbon|null $directory_refreshed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[UseFactory(CoachProfileFactory::class)]
final class CoachProfile extends Model
{
    /** @use HasFactory<CoachProfileFactory> */
    use HasFactory;

    public const APPROVED = 'approved';

    public const PAUSED = 'paused';

    public const SUSPENDED = 'suspended';

    protected $fillable = [
        'bio',
        'voice_types',
        'styles',
        'languages',
        'timezone',
        'cancellation_hours',
    ];

    protected function casts(): array
    {
        return [
            'voice_types' => 'array',
            'styles' => 'array',
            'languages' => 'array',
            'cancellation_hours' => 'integer',
            'charges_enabled' => 'boolean',
            'payouts_enabled' => 'boolean',
            'details_submitted' => 'boolean',
            'rating_count' => 'integer',
            'approved_at' => 'datetime',
            'featured_at' => 'datetime',
            'from_cents' => 'integer',
            'turnaround_days' => 'integer',
            'next_lesson_at' => 'datetime',
            'directory_refreshed_at' => 'datetime',
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
