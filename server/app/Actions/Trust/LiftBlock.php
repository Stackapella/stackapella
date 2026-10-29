<?php

declare(strict_types=1);

namespace App\Actions\Trust;

use App\Models\AuditLog;
use App\Models\Block;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Take an entry off the blocklist. The audit log keeps what it was.
 */
final class LiftBlock
{
    public function handle(Block $block, User $actor, ?string $ip): void
    {
        DB::transaction(function () use ($block, $actor, $ip): void {
            AuditLog::record('block.lifted', $block, $actor, before: ['type' => $block->type->value, 'value' => $block->value, 'reason' => $block->reason], ip: $ip);
            $block->delete();
        });
    }
}
