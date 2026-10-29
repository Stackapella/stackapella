<?php

declare(strict_types=1);

namespace App\Models;

use App\Actions\Mudeo\MudeoList;
use App\Models\Concerns\HasSqid;
use Database\Factories\MudeoContactFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One of mudeo's users on the list for Hillel's one email (docs/mudeo-transition-plan.md): kept
 * only for that, and deleted 90 days after the last batch (an opt-out stays, as a suppression).
 *
 * @property int $id
 * @property string $email
 * @property string|null $name
 * @property int|null $mudeo_id
 * @property Carbon|null $mudeo_created_at
 * @property Carbon|null $mudeo_updated_at
 * @property int|null $mudeo_batch_id
 * @property Carbon|null $sent_at
 * @property Carbon|null $opted_out_at
 * @property Carbon|null $bounced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[UseFactory(MudeoContactFactory::class)]
final class MudeoContact extends Model
{
    /** @use HasFactory<MudeoContactFactory> */
    use HasFactory;

    use HasSqid;

    protected function casts(): array
    {
        return [
            'mudeo_created_at' => 'datetime',
            'mudeo_updated_at' => 'datetime',
            'sent_at' => 'datetime',
            'opted_out_at' => 'datetime',
            'bounced_at' => 'datetime',
        ];
    }

    /**
     * Pruned 90 days after the last batch: only a hash of the address is left.
     */
    public function isForgotten(): bool
    {
        return str_starts_with($this->email, MudeoList::HASHED);
    }

    public function status(): string
    {
        return match (true) {
            $this->opted_out_at !== null => 'opted_out',
            $this->bounced_at !== null => 'bounced',
            $this->sent_at !== null => 'sent',
            default => 'to_send',
        };
    }
}
