<?php

declare(strict_types=1);

namespace App\Support\Sync;

use App\Exceptions\Refusal;
use App\Http\Resources\Projects\PartResource;
use App\Http\Resources\Projects\ProjectResource;
use App\Http\Resources\Projects\TakeResource;
use App\Models\Part;
use App\Models\SyncTombstone;
use App\Models\Take;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * sync.pull: what changed since a cursor, in the order it changed (contract/api.md, "sync.pull").
 * The cursor is the last change number sent; a page holds at most PAGE changes across every kind,
 * and ends early once its takes' peaks pass PEAKS (a two-minute take has about 11,000; 200 of them
 * would be some 3 MB): more, smaller pages (docs/performance.md).
 */
final class Changes
{
    public const PAGE = 200;

    /** The takes' peaks in one page, at most (about 1.3 MB as peaks8); a page always has one change. */
    public const PEAKS = 1000000;

    /**
     * @return array{data: array{projects: list<array<string, mixed>>, parts: list<array<string, mixed>>, takes: list<array<string, mixed>>, removed: list<array<string, mixed>>}, meta: array{cursor: string, more: bool}}
     */
    public function since(User $user, ?string $cursor): array
    {
        // One snapshot for every read (REPEATABLE READ, MySQL's default): change numbers are given
        // in order under the account's row lock, so a snapshot sees every change up to a point
        // and none after it, whatever commits between the reads.
        return DB::transaction(fn (): array => $this->read($user, $cursor));
    }

    /**
     * @return array{data: array{projects: list<array<string, mixed>>, parts: list<array<string, mixed>>, takes: list<array<string, mixed>>, removed: list<array<string, mixed>>}, meta: array{cursor: string, more: bool}}
     */
    private function read(User $user, ?string $cursor): array
    {
        $after = $cursor === null ? 0 : (self::decode($cursor) ?? 0);
        $user = User::query()->findOrFail($user->id, ['id', 'sync_seq', 'sync_pruned_seq']);

        // Deletions older than what's remembered can't be told: start again without a cursor.
        if ($after > 0 && $after < $user->sync_pruned_seq) {
            throw new Refusal(410, 'cursor_expired');
        }

        $limit = self::PAGE + 1;
        $projects = ProjectResource::query()->where('user_id', $user->id)->where('seq', '>', $after)->orderBy('seq')->limit($limit)->get();
        $parts = Part::query()->with(['project', 'currentTake:id,uuid', 'creator'])->whereHas('project', fn ($query) => $query->where('user_id', $user->id))->where('seq', '>', $after)->orderBy('seq')->limit($limit)->get();
        // Without their peaks at first: the page is sized by how many each has, and only the takes
        // it holds have theirs read (below).
        $takes = Take::query()->select(Take::WITHOUT_PEAKS)->with('part')->where('user_id', $user->id)->where('seq', '>', $after)->orderBy('seq')->limit($limit)->get();
        $removed = $cursor === null ? collect() : SyncTombstone::query()->where('user_id', $user->id)->where('seq', '>', $after)->orderBy('seq')->limit($limit)->get();

        // The PAGE lowest change numbers across every kind, fewer once the takes' peaks fill a page.
        $seqs = collect([...$projects->pluck('seq'), ...$parts->pluck('seq'), ...$takes->pluck('seq'), ...$removed->pluck('seq')])->map(fn ($seq): int => (int) $seq)->sort()->values();
        // How many peaks each has, from its length (reading the peaks to count them would read them all).
        $weights = $takes->mapWithKeys(static fn (Take $take): array => [(int) $take->seq => $take->frames_per_peak > 0 ? (int) ceil($take->frames / $take->frames_per_peak) : 0])->all();
        $taken = 0;
        $peaks = 0;

        foreach ($seqs->take(self::PAGE) as $seq) {
            $peaks += $weights[$seq] ?? 0;

            if ($taken > 0 && $peaks > self::PEAKS) {
                break;
            }

            $taken++;
        }

        $more = $seqs->count() > $taken;
        $last = (int) ($taken > 0 ? $seqs[$taken - 1] : max($after, $cursor === null ? $user->sync_seq : $after));
        $in = static fn ($row): bool => (int) $row->seq <= $last;
        $sent = $takes->filter($in);
        $peaks = $sent->isEmpty() ? collect() : Take::query()->whereKey($sent->modelKeys())->toBase()->pluck('peaks', 'id');
        $sent->each(static fn (Take $take) => $take->setRawAttributes([...$take->getAttributes(), 'peaks' => $peaks[$take->id] ?? null], true));

        return [
            'data' => [
                'projects' => array_values($projects->filter($in)->map(fn ($p): array => ProjectResource::fields($p))->all()),
                'parts' => array_values($parts->filter($in)->map(fn ($p): array => PartResource::fields($p))->all()),
                'takes' => array_values($sent->map(fn ($t): array => TakeResource::fields($t))->all()),
                'removed' => array_values($removed->filter($in)->map(fn ($r): array => ['kind' => $r->kind, 'id' => $r->uuid])->all()),
            ],
            'meta' => ['cursor' => self::encode($last), 'more' => $more],
        ];
    }

    public static function encode(int $seq): string
    {
        return rtrim(strtr(base64_encode('s'.$seq), '+/', '-_'), '=');
    }

    /**
     * The change a cursor stands for, or null when it isn't one of ours. `s0` (from an account with
     * no changes yet) is a real cursor: from the start.
     */
    public static function decode(string $cursor): ?int
    {
        $raw = base64_decode(strtr($cursor, '-_', '+/'), true);

        return is_string($raw) && preg_match('/^s(\d{1,19})$/', $raw, $match) === 1 ? (int) $match[1] : null;
    }
}
