<?php

declare(strict_types=1);

namespace App\Actions\Sharing;

use App\Models\Harmony;
use App\Services\Social\SocialPoster;
use App\Support\Sharing\HarmonyFiles;
use Illuminate\Support\Facades\DB;

/**
 * harmonies.delete: the page, the files and the videos go at once. The row stays without its files
 * (deleted_at), so a page taken down for copyright still counts toward repeat infringers.
 */
final class DeleteHarmony
{
    public function __construct(private readonly HarmonyFiles $files) {}

    public function handle(Harmony $harmony): void
    {
        // Locked, so a ProcessHarmony finishing at the same moment either sees it deleted or has
        // already saved the keys read here.
        $deleted = DB::transaction(function () use ($harmony): ?Harmony {
            $locked = Harmony::query()->whereKey($harmony->id)->whereNull('deleted_at')->lockForUpdate()->first();
            $locked?->forceFill(['deleted_at' => now()])->save();

            return $locked;
        });

        if ($deleted !== null) {
            $this->files->deleteAll($deleted);
            SocialPoster::withdraw($deleted);
        }
    }

    /**
     * Someone's voice left: every harmony with their takes in its mix comes down (in one project,
     * when they left it, or everywhere, when they deleted their account). The owner can share
     * again without them.
     */
    /**
     * A friend's parts left a choir song (choir together): every share of the owner's whose mix held them comes down,
     * as a voice leaving a project takes its shares down. The owner can share again without them.
     */
    public function withFriend(int $memberId): void
    {
        foreach (Harmony::query()->whereNull('deleted_at')->whereJsonContains('own_voice_friends', ['member' => $memberId])->get() as $harmony) {
            $this->handle($harmony);
        }
    }

    /**
     * A singer's own recording gone (a song's lead take, a voice's kept clip): every share that carried it comes down.
     *
     * @param  array<int|string>  $takes  choir songs whose lead went
     * @param  array<int|string>  $clips  voices whose clip went
     */
    public function withRealVoice(array $takes = [], array $clips = []): void
    {
        if ($takes === [] && $clips === []) {
            return;
        }

        $shares = Harmony::query()->whereNull('deleted_at')->whereNotNull('real_voice')->where(fn ($q) => $q->whereIn('real_voice->take', array_map('intval', $takes))->orWhereIn('real_voice->clip', array_map('intval', $clips)))->get();

        foreach ($shares as $harmony) {
            $this->handle($harmony);
        }
    }

    /** A choir song gone: every share of a friend's that held its owner's parts comes down too. */
    public function withOwnersParts(int $songId): void
    {
        foreach (Harmony::query()->whereNull('deleted_at')->whereJsonContains('own_voice_friends', ['song' => $songId])->get() as $harmony) {
            $this->handle($harmony);
        }
    }

    public function withVoiceOf(int $singerId, ?int $projectId = null): void
    {
        $harmonies = Harmony::query()->whereNull('deleted_at')->whereJsonContains('singer_ids', $singerId)
            ->when($projectId !== null, fn ($query) => $query->where('project_id', $projectId))->get();

        foreach ($harmonies as $harmony) {
            $this->handle($harmony);
        }
    }
}
