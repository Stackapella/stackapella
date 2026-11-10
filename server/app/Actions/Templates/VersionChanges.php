<?php

declare(strict_types=1);

namespace App\Actions\Templates;

/**
 * The pin-time twin of the sync's "published with different notes" refusal: compares two content
 * trees (the one content.lock pins now and the one about to be pinned) and names every song whose
 * music changed under the same version number, or whose version went down. Production's sync
 * refuses those and fails the deploy (sakura-sakura, 2026-11-09), so content-bump.sh refuses the
 * pin first. The music is SyncOfficialTemplates::musicHash, so the two never disagree.
 *
 * It is stricter than the sync, on purpose: it compares with the pinned commit, not with what
 * production has. A song changed at v2 in a pin that never deployed, then changed again at v2,
 * would pass the sync (no v2 is published yet) but is refused here. A new version is always safe,
 * and the gate needn't know what production holds, so keep it this way.
 */
final class VersionChanges
{
    /** The content repo's template folders, as content.sh places them for the sync. */
    public const FOLDERS = ['templates/free', 'templates/library'];

    /**
     * @return list<string> one line per problem, empty when the new content can be pinned
     */
    public function handle(string $oldRoot, string $newRoot): array
    {
        $old = $this->songs($oldRoot);
        $new = $this->songs($newRoot);

        // No songs at all is a wrong folder, not a clean pin.
        if ($old === [] || $new === []) {
            return ['no songs in '.($old === [] ? $oldRoot : $newRoot).' (give a content tree, with '.implode(' and ', self::FOLDERS).')'];
        }

        $problems = [];

        foreach ($new as $id => [$file, $song]) {
            if (! isset($old[$id])) {
                continue;
            }

            $was = (int) ($old[$id][1]['version'] ?? 0);
            $now = (int) ($song['version'] ?? 0);

            if ($now < $was) {
                $problems[] = "{$file}: version {$now} is below the pinned version {$was}";
            } elseif ($now === $was && SyncOfficialTemplates::musicHash($song) !== SyncOfficialTemplates::musicHash($old[$id][1])) {
                $next = $now + 1;
                $problems[] = "{$file}: its notes changed at version {$now} since the pinned content; give it version {$next}";
            }
        }

        return $problems;
    }

    /**
     * Every song file in a content tree by id, as the sync reads them (index.json and broken files
     * left to the sync's own checks).
     *
     * @return array<string, array{0: string, 1: array<string, mixed>}>
     */
    private function songs(string $root): array
    {
        $songs = [];

        foreach (self::FOLDERS as $folder) {
            foreach (glob(rtrim($root, '/')."/{$folder}/*.json") ?: [] as $path) {
                $data = json_decode((string) file_get_contents($path), true);

                if (basename($path) !== 'index.json' && is_array($data) && is_string($data['id'] ?? null)) {
                    /** @var array<string, mixed> $data */
                    $songs[$data['id']] = ["{$folder}/".basename($path), $data];
                }
            }
        }

        return $songs;
    }
}
