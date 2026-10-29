<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CoachingPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One coaching payment or refund, once (its `ref` is unique): a review, lesson or pack paid, a
 * package's month paid, or money given back. A refund names the sale it's from (`sale_ref`) and
 * is negative, its fee too. The coach's books and our monthly commission are made from these.
 *
 * @property int $id
 * @property int|null $engagement_id
 * @property int|null $coach_id
 * @property string $kind
 * @property string $ref
 * @property string|null $sale_ref
 * @property int $cents
 * @property int $fee_cents
 * @property string $currency
 * @property string $description
 * @property string|null $stripe_charge
 * @property bool $backfilled
 * @property Carbon $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Engagement|null $engagement
 */
#[UseFactory(CoachingPaymentFactory::class)]
final class CoachingPayment extends Model
{
    /** @use HasFactory<CoachingPaymentFactory> */
    use HasFactory;

    public const SALE = 'sale';

    public const REFUND = 'refund';

    /** Only ever written by the Ledger, from engagements. */
    protected $fillable = ['ref', 'engagement_id', 'coach_id', 'kind', 'sale_ref', 'cents', 'fee_cents', 'currency', 'description', 'stripe_charge', 'backfilled', 'paid_at'];

    protected function casts(): array
    {
        return [
            'cents' => 'integer',
            'fee_cents' => 'integer',
            'backfilled' => 'boolean',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Engagement, $this>
     */
    public function engagement(): BelongsTo
    {
        return $this->belongsTo(Engagement::class);
    }
}
