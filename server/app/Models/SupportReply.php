<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SupportReplyFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Hillel's answer to a support message, as emailed to the person.
 *
 * @property int $id
 * @property int $support_message_id
 * @property int|null $admin_id
 * @property string $body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $admin
 */
#[UseFactory(SupportReplyFactory::class)]
final class SupportReply extends Model
{
    /** @use HasFactory<SupportReplyFactory> */
    use HasFactory;

    protected $fillable = ['body'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
