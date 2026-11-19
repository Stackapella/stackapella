<?php

declare(strict_types=1);

namespace App\Actions\Videos;

use App\Exceptions\Refusal;
use App\Models\SongVideo;

/**
 * Takes a synced recording away, its map with it (a map has no use without its video).
 */
final class DeleteSongVideo
{
    public function handle(string $sqid): void
    {
        $row = SongVideo::query()->whereSqid($sqid)->first() ?? throw new Refusal(404, 'not_found');
        $row->delete();
    }
}
