<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\StripeEventFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A Stripe event already handled, so a redelivery changes nothing twice.
 *
 * @property int $id
 * @property string $stripe_id
 * @property string $type
 * @property Carbon|null $created_at
 */
#[UseFactory(StripeEventFactory::class)]
final class StripeEvent extends Model
{
    /** @use HasFactory<StripeEventFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'stripe_id',
        'type',
    ];
}
