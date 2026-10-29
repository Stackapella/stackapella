<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CheckoutSessionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A Stripe Checkout someone started: the upgrade moment that led to it (counted on the server),
 * and whether it's finished, for billing.show's `pending`.
 *
 * @property int $id
 * @property int $user_id
 * @property string $stripe_id
 * @property string $moment
 * @property string $interval
 * @property string $currency
 * @property bool $trial
 * @property string|null $country
 * @property Carbon|null $start_now_consent_at
 * @property int|null $start_now_consent_version
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[UseFactory(CheckoutSessionFactory::class)]
final class CheckoutSession extends Model
{
    /** @use HasFactory<CheckoutSessionFactory> */
    use HasFactory;

    protected $fillable = [
        'stripe_id',
        'moment',
        'interval',
        'currency',
        'trial',
    ];

    protected function casts(): array
    {
        return [
            'trial' => 'boolean',
            'completed_at' => 'datetime',
            'start_now_consent_at' => 'datetime',
            'start_now_consent_version' => 'integer',
        ];
    }
}
