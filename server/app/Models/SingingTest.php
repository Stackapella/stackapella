<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\SingingTest\Scoring;
use Database\Factories\SingingTestFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One singing test, numbers only (docs/singing-test-v1.md): its items as sent, and the server's own score at
 * `scored_version` (App\Support\SingingTest\Scoring), scored again whenever that's older than the current version.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int $test_version
 * @property int $device_scoring_version
 * @property Carbon $taken_at
 * @property string $anchor
 * @property string $device
 * @property bool $warm_up
 * @property array{match: list<array<string, mixed>>, back: list<array<string, mixed>>, hold: list<array<string, mixed>>} $items
 * @property array<string, mixed>|null $device_score
 * @property int $scored_version
 * @property string $status
 * @property string|null $match_points
 * @property string|null $back_points
 * @property string|null $hold_points
 * @property int|null $total
 * @property string|null $band
 * @property string|null $items_hash
 * @property Carbon|null $flagged_at
 * @property list<string>|null $flag_reasons
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $hidden_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[UseFactory(SingingTestFactory::class)]
final class SingingTest extends Model
{
    /** @use HasFactory<SingingTestFactory> */
    use HasFactory;

    public const PRACTICE = 'practice';

    public const COUNTED = 'counted';

    /** A held test's season shows on the global board anyway after this long, if nobody looks (as the part boards'). */
    public const HOLD_HOURS = 48;

    /**
     * The fingerprint of a test's items, to find the same data sent twice.
     *
     * @param  array<string, mixed>  $items
     */
    public static function hashItems(array $items): string
    {
        return hash('sha256', (string) json_encode($items));
    }

    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'taken_at' => 'datetime',
            'warm_up' => 'boolean',
            'items' => 'array',
            'device_score' => 'array',
            'test_version' => 'integer',
            'device_scoring_version' => 'integer',
            'scored_version' => 'integer',
            'total' => 'integer',
            'flagged_at' => 'datetime',
            'flag_reasons' => 'array',
            'reviewed_at' => 'datetime',
            'hidden_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The server's own score, at the current scoring version (unsaved). */
    public function rescore(): self
    {
        $items = $this->items;
        $scored = Scoring::score($items['match'] ?? [], $items['back'] ?? [], $items['hold'] ?? [], $this->warm_up);

        return $this->forceFill([
            'scored_version' => Scoring::VERSION,
            'status' => $scored['status'],
            'match_points' => $scored['scores']['match'],
            'back_points' => $scored['scores']['back'],
            'hold_points' => $scored['scores']['hold'],
            'total' => $scored['total'],
            'band' => $scored['band'],
        ]);
    }
}
