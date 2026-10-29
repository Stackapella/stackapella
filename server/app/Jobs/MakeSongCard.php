<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Template;
use App\Support\Sharing\SongCards;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Make one song's link card (SongCards), asked for by the first page that wants it.
 */
final class MakeSongCard implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(public readonly int $templateId, public readonly string $wording) {}

    public function handle(SongCards $cards): void
    {
        $template = Template::query()->with('currentVersion')->find($this->templateId);

        if ($template !== null) {
            $cards->make($template, $this->wording);
        }
    }
}
