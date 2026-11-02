<?php

declare(strict_types=1);

use App\Jobs\RedrawPersonsCards;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Share cards drawn before friends were named only with their consent (the security review of
 * 2026-11-01): each friend's cards are drawn again, now showing "a singer" until they agree.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! app()->isProduction()) {
            return;
        }

        $friends = DB::table('harmonies')->whereNull('deleted_at')->whereNotNull('card_key')->whereNotNull('singer_ids')->pluck('singer_ids')
            ->flatMap(fn (mixed $ids): array => array_map('intval', (array) json_decode((string) $ids, true)))->unique()->values();

        foreach ($friends as $id) {
            RedrawPersonsCards::dispatch($id);
        }
    }

    public function down(): void
    {
        // Nothing to undo: the cards are drawn from today's rules.
    }
};
