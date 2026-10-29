<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\BuildChoirMix;
use App\Models\Choir;
use Illuminate\Console\Command;

/**
 * Hourly (N6): each choir whose voices changed since its last mix is mixed again, at most once an
 * hour each.
 */
final class ChoirsMix extends Command
{
    protected $signature = 'app:choirs:mix';

    protected $description = 'Mix again each virtual choir whose voices changed';

    public function handle(): int
    {
        $choirs = Choir::query()->whereNotNull('changed_at')
            ->where(fn ($q) => $q->whereNull('mixed_at')->orWhereColumn('changed_at', '>=', 'mixed_at'))
            ->where(fn ($q) => $q->whereNull('mixed_at')->orWhere('mixed_at', '<=', now()->subMinutes(55)))
            ->pluck('id');

        foreach ($choirs as $id) {
            BuildChoirMix::dispatch((int) $id);
        }

        $this->info('Mixing '.$choirs->count().' choirs.');

        return self::SUCCESS;
    }
}
