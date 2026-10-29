<?php

declare(strict_types=1);

namespace App\Actions\Trust;

use App\Enums\BlockType;
use App\Models\AuditLog;
use App\Models\Block;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Put an email, domain, IP, range or card on the blocklist, audit-logged with the reason.
 */
final class AddBlock
{
    public function handle(BlockType $type, string $value, string $reason, ?CarbonInterface $expiresAt, User $actor, ?string $ip): Block
    {
        try {
            return $this->add($type, $value, $reason, $expiresAt, $actor, $ip);
        } catch (UniqueConstraintViolationException) {
            // The same block added twice at the same moment: the second is a duplicate, as the
            // FormRequest's unique rule says when they don't race.
            throw ValidationException::withMessages(['value' => __('validation.unique', ['attribute' => 'value'])]);
        }
    }

    private function add(BlockType $type, string $value, string $reason, ?CarbonInterface $expiresAt, User $actor, ?string $ip): Block
    {
        return DB::transaction(function () use ($type, $value, $reason, $expiresAt, $actor, $ip): Block {
            $block = new Block(['type' => $type, 'value' => $value, 'reason' => $reason, 'expires_at' => $expiresAt]);
            $block->forceFill(['created_by' => $actor->getKey()])->save();
            $block->setRelation('creator', $actor);

            AuditLog::record('block.created', $block, $actor, after: ['type' => $type->value, 'value' => $value], reason: $reason, ip: $ip);

            return $block;
        });
    }
}
