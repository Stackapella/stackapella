<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EsDeliveryFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An Event Schedule webhook delivery already taken in, so a redelivery is handled once.
 *
 * @property int $id
 * @property int $es_connection_id
 * @property string $delivery_id
 * @property string $type
 * @property Carbon|null $created_at
 */
#[UseFactory(EsDeliveryFactory::class)]
final class EsDelivery extends Model
{
    /** @use HasFactory<EsDeliveryFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = ['delivery_id', 'type'];
}
