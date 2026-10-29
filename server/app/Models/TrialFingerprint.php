<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TrialFingerprintFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A card that has had a trial: its Stripe fingerprint only, never the number (one trial per card).
 *
 * @property int $id
 * @property string $fingerprint
 * @property int|null $user_id
 * @property string|null $subscription_id
 * @property Carbon|null $created_at
 */
#[UseFactory(TrialFingerprintFactory::class)]
final class TrialFingerprint extends Model
{
    /** @use HasFactory<TrialFingerprintFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'fingerprint',
    ];
}
