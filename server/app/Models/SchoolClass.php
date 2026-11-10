<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSqid;
use App\Support\Stats\ReportDay;
use Database\Factories\SchoolClassFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A teacher's class code (docs/school-offer-plan.md): students who redeem it get Plus free for a
 * year. The teacher sees only its seats used, never who.
 *
 * @property int $id
 * @property string $kind class (a teacher's) or partner (Admin's, for an outreach partner's members)
 * @property int|null $teacher_id
 * @property int|null $outreach_target_id the partner organization a partner code is for
 * @property string $name
 * @property string $code
 * @property int $seats
 * @property int $used seats used (kept when a student's account goes)
 * @property Carbon $expires_at
 * @property Carbon|null $closed_at
 * @property string|null $closed_by teacher or admin
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $teacher
 * @property-read OutreachTarget|null $partner
 * @property-read SchoolTeacher|null $teacherRecord
 * @property int|null $today_count redemptions since the start of today (the report timezone)
 */
#[UseFactory(SchoolClassFactory::class)]
final class SchoolClass extends Model
{
    /** @use HasFactory<SchoolClassFactory> */
    use HasFactory;

    use HasSqid;

    /** The code's letters: no 0/O or 1/I/L, so it reads aloud and copies cleanly. */
    public const PARTNER = 'partner';

    public const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    protected function casts(): array
    {
        return ['seats' => 'integer', 'used' => 'integer', 'expires_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<OutreachTarget, $this>
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(OutreachTarget::class, 'outreach_target_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * The teacher's approval and seat allowance.
     *
     * @return HasOne<SchoolTeacher, $this>
     */
    public function teacherRecord(): HasOne
    {
        return $this->hasOne(SchoolTeacher::class, 'user_id', 'teacher_id');
    }

    /**
     * @return HasMany<SchoolRedemption, $this>
     */
    public function redemptions(): HasMany
    {
        return $this->hasMany(SchoolRedemption::class);
    }

    /**
     * open, full, expired, closed (a deleted teacher's code too), or pending (the teacher isn't
     * approved yet). `$used`: the count to judge full by (the teacher's is a day behind).
     */
    public function status(?int $used = null): string
    {
        return match (true) {
            // A class with no teacher is a deleted account's; a partner code never has one.
            $this->closed_at !== null || ($this->kind !== self::PARTNER && $this->teacher_id === null) => 'closed',
            $this->expires_at->isPast() => 'expired',
            $this->kind !== self::PARTNER && $this->teacherRecord?->approved_at === null => 'pending',
            ($used ?? $this->used) >= $this->seats => 'full',
            default => 'open',
        };
    }

    /**
     * Seats used as the teacher sees them: as of the start of today (Hillel: "updates once a day"),
     * so watching it can't tell them who just joined.
     */
    public function usedForTeacher(): int
    {
        $today = $this->today_count ?? $this->redemptions()->where('created_at', '>=', ReportDay::start(ReportDay::today()))->count();

        return max(0, $this->used - (int) $today);
    }

    /**
     * What this code takes of the teacher's seat allowance: all its seats while open (or its used
     * ones, if more joined than it now has seats), its used ones once closed.
     */
    public function allowanceTaken(): int
    {
        return $this->closed_at !== null || $this->expires_at->isPast() ? $this->used : max($this->seats, $this->used);
    }

    /** As shown: KQ7M-3XPA. */
    public function shown(): string
    {
        return substr($this->code, 0, 4).'-'.substr($this->code, 4);
    }

    /** A typed or linked code, without its dash or spaces, upper case. */
    public static function normalise(string $code): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $code));
    }
}
