<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSqid;
use Database\Factories\InvoiceNinjaRecordFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One thing to record in someone's Invoice Ninja, once (unique per connection, kind and our
 * reference), with Invoice Ninja's ids as they're made so a retry carries on where it stopped.
 *
 * @property int $id
 * @property int $connection_id
 * @property string $kind
 * @property string $ref
 * @property string $status
 * @property int|null $user_id
 * @property int $cents
 * @property string $currency
 * @property array<string, mixed> $data
 * @property string|null $in_client_id
 * @property string|null $in_invoice_id
 * @property string|null $in_payment_id
 * @property string|null $in_expense_id
 * @property string|null $error
 * @property int $attempts
 * @property Carbon|null $recorded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read InvoiceNinjaConnection $connection
 */
#[UseFactory(InvoiceNinjaRecordFactory::class)]
final class InvoiceNinjaRecord extends Model
{
    /** @use HasFactory<InvoiceNinjaRecordFactory> */
    use HasFactory;

    use HasSqid;

    /** A sale (an invoice and its payment), in a coach's books. */
    public const SALE = 'sale';

    /** Stackapella's commission on a sale, as the coach's expense. */
    public const FEE = 'fee';

    /** Money given back, as a refund on the sale's payment. */
    public const REFUND = 'refund';

    /** A paid Plus invoice from Stripe, in Stackapella's books. */
    public const PLUS = 'plus';

    /** A month's commission from one coach, in Stackapella's books. */
    public const COMMISSION = 'commission';

    /** A year of Plus invoiced by Hillel, waiting to be paid (paying by invoice). */
    public const PLUS_INVOICE = 'plus_invoice';

    public const PENDING = 'pending';

    public const RECORDING = 'recording';

    public const RECORDED = 'recorded';

    public const FAILED = 'failed';

    /** A Plus invoice sent, waiting for its payment. */
    public const SENT = 'sent';

    /** A Plus invoice paid: the account has Plus. */
    public const PAID = 'paid';

    /** Only ever written by Books, from the ledger and the webhooks. */
    protected $fillable = ['connection_id', 'kind', 'ref', 'status', 'user_id', 'cents', 'currency', 'data'];

    protected function casts(): array
    {
        return [
            'cents' => 'integer',
            'data' => 'array',
            'attempts' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<InvoiceNinjaConnection, $this>
     */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(InvoiceNinjaConnection::class, 'connection_id');
    }
}
