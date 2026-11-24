<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSqid;
use Database\Factories\ChoirGalleryItemFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A choir song in the choir gallery (docs/choir-gallery-plan.md): its share (the exact mix staff heard), the consent,
 * and where it stands. App\Support\OwnChoir\ChoirGallery says what anyone sees of it.
 *
 * @property int $id
 * @property int $user_id
 * @property int $own_choir_request_id
 * @property int|null $harmony_id
 * @property string $parts_sha
 * @property string $status
 * @property string|null $reason
 * @property string|null $reason_text
 * @property int $consent_version
 * @property string $words_hash
 * @property string $locale
 * @property Carbon $added_at
 * @property Carbon|null $decided_at
 * @property int|null $decided_by
 * @property Carbon|null $released_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read OwnChoirRequest $request
 * @property-read Harmony|null $harmony
 */
#[UseFactory(ChoirGalleryItemFactory::class)]
final class ChoirGalleryItem extends Model
{
    /** @use HasFactory<ChoirGalleryItemFactory> */
    use HasFactory;

    use HasSqid;

    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const DECLINED = 'declined';

    public const HELD = 'held';

    public const REMOVED = 'removed';

    /** Counted toward the 20 live. */
    public const LIVE = [self::PENDING, self::APPROVED, self::HELD];

    public const REASONS = ['noise', 'broken', 'not_own', 'not_library', 'other'];

    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['added_at' => 'datetime', 'decided_at' => 'datetime', 'released_at' => 'datetime', 'consent_version' => 'integer'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<OwnChoirRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(OwnChoirRequest::class, 'own_choir_request_id');
    }

    /**
     * @return BelongsTo<Harmony, $this>
     */
    public function harmony(): BelongsTo
    {
        return $this->belongsTo(Harmony::class);
    }
}
