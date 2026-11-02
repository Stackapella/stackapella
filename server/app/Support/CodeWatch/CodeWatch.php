<?php

declare(strict_types=1);

namespace App\Support\CodeWatch;

use App\Services\GitHub\CodeSearch;
use App\Support\Http\Hosts;
use App\Support\Ids;
use App\Support\Stats\ReportDay;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Code watch (docs/code-watch-plan.md): marker strings found in other people's public code, and
 * other websites calling our API. Counts and names of repositories and domains only.
 */
final class CodeWatch
{
    public function __construct(private readonly CodeSearch $search) {}

    /**
     * The weekly search: every repository outside our own that holds a marker.
     *
     * @return int repositories seen this run
     */
    public function search(): int
    {
        $own = (array) config('codewatch.own_owners');
        $seen = 0;

        foreach ((array) config('codewatch.markers') as $marker) {
            foreach ($this->search->repositories((string) $marker) as $repo) {
                if (in_array(strtolower(strtok($repo['repo'], '/') ?: ''), $own, true)) {
                    continue;
                }

                self::seen('marker', $repo['repo'], $repo['url']);
                $seen++;
            }
        }

        return $seen;
    }

    /**
     * A request from another website (its Origin, else its Referer): counted for its domain today,
     * and listed once it passes the day's threshold. Our own hosts and local ones aren't counted.
     */
    public function count(?string $origin): void
    {
        $domain = self::domain($origin);

        if ($domain === null || self::ours($domain)) {
            return;
        }

        $day = ReportDay::today();
        DB::statement('insert into code_watch_domain_days (day, domain, count) values (?, ?, 1) on duplicate key update count = count + 1', [$day, $domain]);

        if ((int) DB::table('code_watch_domain_days')->where('day', $day)->where('domain', $domain)->value('count') >= (int) config('codewatch.domain_threshold')) {
            self::seen('domain', $domain, 'https://'.$domain);
        }
    }

    public static function domain(?string $origin): ?string
    {
        $host = is_string($origin) && $origin !== '' ? parse_url($origin, PHP_URL_HOST) : null;
        $host = is_string($host) ? strtolower(rtrim($host, '.')) : '';

        return $host !== '' && strlen($host) <= 190 ? (str_starts_with($host, 'www.') ? substr($host, 4) : $host) : null;
    }

    private static function ours(string $domain): bool
    {
        if (in_array($domain, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($domain, '.test') || str_ends_with($domain, '.local')) {
            return true;
        }

        foreach ([Hosts::appUrl(), Hosts::publicUrl()] as $url) {
            $ours = strtolower((string) parse_url($url, PHP_URL_HOST));
            $base = implode('.', array_slice(explode('.', $ours), -2));

            if ($ours !== '' && ($domain === $ours || $domain === $base || str_ends_with($domain, '.'.$base))) {
                return true;
            }
        }

        return false;
    }

    /**
     * admin.codeWatch.list's hits: undismissed first, then the latest seen; a domain with its API
     * calls over the last 30 days.
     *
     * @return list<array<string, mixed>>
     */
    public function hits(): array
    {
        $calls = $this->calls();

        return array_values(DB::table('code_watch_hits')->orderByRaw('dismissed_at is not null')->orderByDesc('last_seen_at')->limit(200)->get()
            ->map(fn (stdClass $h): array => self::view($h, (int) ($calls[$h->key] ?? 0)))->all());
    }

    /**
     * Dismiss (or bring back) a hit, or set its note.
     *
     * @return array<string, mixed>|null null when there's no such hit
     */
    public function update(int $id, ?bool $dismissed, bool $setNote, ?string $note): ?array
    {
        $row = DB::table('code_watch_hits')->where('id', $id)->first();

        if ($row === null) {
            return null;
        }

        $changes = ['updated_at' => now()];

        if ($dismissed !== null) {
            $changes['dismissed_at'] = $dismissed ? ($row->dismissed_at ?? now()) : null;
        }

        if ($setNote) {
            $changes['note'] = $note !== null && trim($note) !== '' ? trim($note) : null;
        }

        DB::table('code_watch_hits')->where('id', $id)->update($changes);
        $row = DB::table('code_watch_hits')->where('id', $id)->first();

        return $row !== null ? self::view($row, (int) ($this->calls()[$row->key] ?? 0)) : null;
    }

    /**
     * @return Collection<string, mixed>
     */
    private function calls(): Collection
    {
        return DB::table('code_watch_domain_days')->where('day', '>=', ReportDay::now()->subDays(29)->toDateString())->groupBy('domain')->selectRaw('domain, sum(count) as n')->pluck('n', 'domain');
    }

    /**
     * @return array<string, mixed>
     */
    private static function view(stdClass $h, int $calls): array
    {
        $iso = static fn (mixed $at): ?string => $at !== null ? Carbon::parse((string) $at, 'UTC')->toIso8601ZuluString() : null;

        return [
            'id' => Ids::encode((int) $h->id),
            'kind' => (string) $h->kind,
            'key' => (string) $h->key,
            'url' => $h->url !== null ? (string) $h->url : null,
            'firstSeenAt' => $iso($h->first_seen_at),
            'lastSeenAt' => $iso($h->last_seen_at),
            // For a domain, its API calls over the last 30 days.
            'calls' => $h->kind === 'domain' ? $calls : null,
            'dismissedAt' => $iso($h->dismissed_at),
            'note' => $h->note !== null ? (string) $h->note : null,
        ];
    }

    private static function seen(string $kind, string $key, string $url): void
    {
        $now = now();
        DB::statement('insert into code_watch_hits (kind, `key`, url, first_seen_at, last_seen_at, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?) on duplicate key update last_seen_at = values(last_seen_at), updated_at = values(updated_at)',
            [$kind, mb_substr($key, 0, 190), mb_substr($url, 0, 500), $now, $now, $now, $now]);
    }
}
