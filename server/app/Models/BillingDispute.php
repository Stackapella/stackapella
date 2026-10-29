<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSqid;
use Database\Factories\BillingDisputeFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A chargeback, from Stripe's webhook: the account drops to trust level 0 and Plus ends.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $stripe_id
 * @property int $amount_cents
 * @property string $currency
 * @property string $reason
 * @property string $status
 * @property Carbon|null $applied_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[UseFactory(BillingDisputeFactory::class)]
final class BillingDispute extends Model
{
    /** @use HasFactory<BillingDisputeFactory> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'stripe_id',
        'amount_cents',
        'currency',
        'reason',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'applied_at' => 'datetime',
        ];
    }
}
