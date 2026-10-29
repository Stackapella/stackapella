<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Templates\Library;
use App\Models\Template;
use App\Models\TemplateStat;
use App\Support\Stats\Counts;
use App\Support\Stats\NetworkReadiness;
use Illuminate\Console\Command;

/**
 * Nightly, from our own records (docs/decisions.md, L): each official song's finishers and what
 * they also learned (What's next), how many people used each person's template (the library,
 * N3), and the network features panel recounted (a status that changed goes in the morning's
 * digest).
 */
final class StatsNightly extends Command
{
    protected $signature = 'app:stats:nightly';

    protected $description = 'Count each song\'s finishers and recount the network features panel';

    public function handle(NetworkReadiness $network, Library $library): int
    {
        $finished = Counts::finished();
        $bySong = $finished->groupBy('template_slug')->map(fn ($songs) => $songs->pluck('user_id')->unique()->values());
        $songsOf = $finished->groupBy('user_id')->map(fn ($songs) => $songs->pluck('template_slug')->unique()->values());
        $templates = Template::query()->official()->get(['id', 'slug'])->keyBy('slug');

        foreach ($templates as $slug => $template) {
            $people = $bySong->get($slug, collect());
            $also = $people->flatMap(fn (int $user) => $songsOf->get($user, collect()))->reject(fn (string $other): bool => $other === $slug)
                // Only songs enough of them share (config's also_learned_min): no small group shows.
                ->countBy()->filter(fn (int $shared): bool => $shared >= (int) config('templates.also_learned_min'))->sortDesc()->keys()
                // Official songs only (people's own templates have no page), then the top 3.
                ->map(fn (string $other): ?int => $templates->get($other)?->id)->filter()->take(3)->values()->all();

            TemplateStat::query()->updateOrCreate(['template_id' => $template->id], ['finishers' => $people->count(), 'also_learned' => $also, 'computed_at' => now()]);
        }

        $uses = $library->recountUses();
        $changes = $network->recount();
        $this->info("Counted {$templates->count()} songs and {$uses} templates' uses; {$changes} network changes for the digest.");

        return self::SUCCESS;
    }
}
