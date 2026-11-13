<?php

declare(strict_types=1);

namespace App\Actions\Templates;

use App\Actions\Courses\CourseClips;
use App\Enums\TemplateAccess;
use App\Models\SocialPost;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Services\Social\SocialPoster;
use App\Support\Contract\SchemaChecker;
use App\Support\Pages\SongParts;
use App\Support\Templates\TemplateRules;
use App\Support\Tools\KnownSongs;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Load the official templates into the catalogue (config/templates.php): each file is checked
 * against the contract's template.json and the app's rules, and becomes a version.
 *
 * A version's music never changes once published: different notes, timing, parts or sections
 * under the same version number are refused (they need a new number, as recorded guides belong to
 * a version). Its words around the music may: the title, description, credits, licence and the
 * musician's `reviewed` are updated in place. The current version is the newest reviewed one (or
 * the newest, while none is). Safe to run again (it runs on every deploy).
 *
 * A Monday opening is that in-place change: the content flips a hidden song's `reviewed` to true
 * with no new version, and the next sync marks the stored version reviewed and reports it as
 * opened (SyncTemplatesTest). A sync reads the files where it runs, so a checkout whose content
 * wasn't linked since the pin still has the song hidden.
 */
final class SyncOfficialTemplates
{
    /** At most this many songs withdrawn in one sync (or a quarter of them, if more). */
    private const MOST_WITHDRAWN = 3;

    /** @var array<string, list<string>> each file's problems, by its bytes' hash and the schemas' folder */
    private static array $checked = [];

    public function __construct(private readonly TemplateRules $rules, private readonly SocialPoster $poster, private readonly CarryVoicesOver $carry, private readonly CourseClips $clips) {}

    /**
     * @return array{added: list<string>, opened: list<string>, unchanged: list<string>, problems: list<string>, carried: list<string>, withdrawn: list<string>}
     */
    public function handle(): array
    {
        $report = ['added' => [], 'opened' => [], 'unchanged' => [], 'problems' => [], 'carried' => [], 'withdrawn' => []];
        // Every song a file names, valid or not: a broken file is a problem, never a withdrawal.
        $present = [];
        // Each song's staged next version (`<id>.next.json`), by its id: its version number.
        $staged = [];
        // The songs whose file says "hidden": true (Hillel's "hide until", Red River Valley): withdrawn, whatever their
        // versions' reviewed. A newer unreviewed version alone leaves the last reviewed one live, so this is the way to hide.
        $hidden = [];
        $schemas = new SchemaChecker((string) config('templates.schemas'));

        foreach ($this->files() as $path) {
            $json = (string) file_get_contents($path);
            $data = json_decode($json, true);
            $name = basename($path);

            if (is_array($data) && is_string($data['id'] ?? null)) {
                $present[] = $data['id'];
            }

            if (! is_array($data)) {
                $report['problems'][] = "{$name}: isn't valid JSON";

                continue;
            }
            if (($data['hidden'] ?? false) === true && is_string($data['id'] ?? null)) {
                if (str_ends_with($name, '.next.json')) {
                    $report['problems'][] = "{$name}: a staged version is never hidden; hide the song in {$data['id']}.json";
                } else {
                    $hidden[] = $data['id'];
                }
            }

            // A file's checks read nothing but its bytes and the schemas, so they're done once a process for the same
            // bytes (the tests sync the same files many times; a deploy's sync, once).
            $problems = self::$checked[hash('sha256', $json).':'.config('templates.schemas')] ??= [...$schemas->check(json_decode($json, false), 'template.json'), ...$this->rules->problems($data), ...$this->rules->officialProblems($data)];

            // An open song (reviewed and public domain) says who made it and when it was first printed (or, never printed,
            // written), so its rights can be worked out by country (docs/geo-rights-plan.md, step 2).
            if ($problems === [] && ($data['reviewed'] ?? false) === true && SongParts::publicDomain($data) && ! self::hasRightsFacts($data)) {
                $problems[] = 'an open song needs makers and firstPublished (or made) (docs/geo-rights-plan.md)';
            }

            if ($problems !== []) {
                $report['problems'][] = "{$name}: ".implode('; ', $problems);

                continue;
            }

            // A staged next version (docs/decisions.md, 2026-11-12, Amazing Grace) waits beside the live one for new
            // recordings: never reviewed, so it can't go live by itself; the switch renames it over the live file.
            $isStaged = str_ends_with($name, '.next.json');
            if ($isStaged && ($data['reviewed'] ?? false) === true) {
                $report['problems'][] = "{$name}: a staged version is never reviewed; to switch, it replaces {$data['id']}.json";

                continue;
            }

            /** @var array{id: string, version: int, title: string, reviewed: bool} $data */
            $result = $this->publish($data);
            if ($isStaged && $result !== 'problem') {
                $staged[$data['id']] = $data['version'];
            }

            // A song is new to singers when a version of it is first reviewed (added reviewed, or
            // its musician's check just came in): a post for Hillel to approve. Songs already out
            // before aren't proposed on every sync.
            if (($result === 'reviewed' || ($result === 'added' && $data['reviewed'])) && ($template = Template::query()->official()->where('slug', $data['id'])->first()) !== null
                && TemplateVersion::query()->where('template_id', $template->id)->where('reviewed', true)->count() === 1) {
                $this->poster->propose($template, SocialPost::NEW_SONG);
            }

            // The same version, now reviewed: the song opens (a Monday's opening).
            $result = $result === 'reviewed' ? 'opened' : $result;
            $report[$result === 'problem' ? 'problems' : $result][] = $result === 'problem'
                ? "{$name}: version {$data['version']} of {$data['id']} was published with different notes; give the change a new version number"
                : "{$data['id']}@{$data['version']}";
        }

        $report = $this->withdraw($present, $hidden, $report);
        $report['problems'] = [...$report['problems'], ...$this->stage($staged)];

        // Recorded voices whose part sings the same in a newer version follow it there.
        $report['carried'] = $this->carry->handle();

        // The courses' clips, defined beside them (web/course/plus/clips.json).
        $report['problems'] = [...$report['problems'], ...$this->clips->sync()];

        // The free tools' list of known songs reads the library: a sync's songs show at once.
        Cache::forget(KnownSongs::CACHE);

        return $report;
    }

    /**
     * @param  array{id: string, version: int, title: string, reviewed: bool}  $data
     * @return 'added'|'unchanged'|'reviewed'|'problem'
     */
    private function publish(array $data): string
    {
        $sha = self::musicHash($data);

        return DB::transaction(function () use ($data, $sha): string {
            $template = Template::query()->official()->where('slug', $data['id'])->lockForUpdate()->first() ?? new Template(['slug' => $data['id']]);
            $template->fill([
                'title' => $data['title'],
                'access' => in_array($data['id'], (array) config('templates.free'), true) ? TemplateAccess::Free : TemplateAccess::Plus,
            ])->save();

            $existing = TemplateVersion::query()->where('template_id', $template->id)->where('version', $data['version'])->first();

            if ($existing !== null) {
                // The stored hash may come from an older way of working it out (ac0789d hashed the
                // whole file), so the music is compared from the stored data itself.
                if ($existing->sha256 !== $sha && self::musicHash((array) $existing->data) !== $sha) {
                    return 'problem';
                }

                if ($existing->sha256 !== $sha) {
                    TemplateVersion::query()->whereKey($existing->id)->update(['sha256' => $sha]);
                }

                // The same music: the words around it (and the musician's reviewed) may change.
                $reviewedNow = ! $existing->reviewed && $data['reviewed'];

                if ($existing->data != $data) {
                    TemplateVersion::query()->whereKey($existing->id)->update(['data' => json_encode($data), 'reviewed' => $data['reviewed']]);
                }

                $this->pickCurrent($template);

                return $reviewedNow ? 'reviewed' : 'unchanged';
            }

            $version = new TemplateVersion(['version' => $data['version']]);
            $version->forceFill(['template_id' => $template->id, 'reviewed' => $data['reviewed'], 'data' => $data, 'sha256' => $sha])->save();
            $this->pickCurrent($template);

            return 'added';
        });
    }

    /**
     * The newest reviewed version, or the newest while none is reviewed.
     */
    private function pickCurrent(Template $template): void
    {
        $versions = TemplateVersion::query()->where('template_id', $template->id)->orderByDesc('version')->get(['id', 'reviewed']);
        $current = $versions->firstWhere('reviewed', true) ?? $versions->first();
        // The day it opened: the first time any version of it is reviewed (Home's "New and in season").
        $opened = $template->opened_at ?? ($versions->firstWhere('reviewed', true) !== null ? now() : null);
        $template->forceFill(['current_version_id' => $current?->id, 'opened_at' => $opened])->save();
    }

    /**
     * What a version's music is: everything a recorded guide depends on (and nothing a typo fix
     * in the description would change). Object keys are sorted, as MySQL's JSON column keeps
     * its own order, so the file and the stored copy hash the same.
     *
     * @param  array<string, mixed>  $data
     */
    public static function musicHash(array $data): string
    {
        $music = [
            'tempo' => $data['tempo'] ?? null,
            'key' => $data['key'] ?? null,
            'timeSignature' => $data['timeSignature'] ?? null,
            'pickupBeats' => $data['pickupBeats'] ?? null,
            'sections' => $data['sections'] ?? null,
            'parts' => array_map(static fn (mixed $part): array => is_array($part) ? ['key' => $part['key'] ?? null, 'range' => $part['range'] ?? null, 'melody' => $part['melody'] ?? null, 'guide' => $part['guide'] ?? null] : [], (array) ($data['parts'] ?? [])),
        ];

        return hash('sha256', (string) json_encode(self::sorted($music)));
    }

    private static function sorted(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $value = array_map(self::sorted(...), $value);

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }

    /**
     * A song whose file has left the content (Hillel: "Remove them now") is withdrawn: out of the
     * library, its page and the sitemap, still served to
     * the projects made from it. One that comes back returns. Never many at once: a content fetch
     * that went wrong (no files, or most missing) is a problem to look at, not a withdrawal.
     *
     * A song its file hides ("hidden": true) is withdrawn the same way, on purpose, so never counted toward that limit,
     * and returns when the flag goes.
     *
     * @param  list<string>  $present
     * @param  list<string>  $hidden
     * @param  array{added: list<string>, opened: list<string>, unchanged: list<string>, problems: list<string>, carried: list<string>, withdrawn: list<string>}  $report
     * @return array{added: list<string>, opened: list<string>, unchanged: list<string>, problems: list<string>, carried: list<string>, withdrawn: list<string>}
     */
    private function withdraw(array $present, array $hidden, array $report): array
    {
        Template::query()->official()->whereNotNull('withdrawn_at')->whereIn('slug', array_values(array_diff($present, $hidden)))->update(['withdrawn_at' => null]);
        $hiding = array_values(array_map(strval(...), Template::query()->official()->whereNull('withdrawn_at')->whereNull('deleted_at')->whereIn('slug', $hidden)->pluck('slug')->all()));
        if ($hiding !== []) {
            Template::query()->official()->whereIn('slug', $hiding)->update(['withdrawn_at' => now()]);
            $report['withdrawn'] = array_values(array_map(static fn (string $slug): string => "{$slug} (hidden)", $hiding));
        }
        $gone = Template::query()->official()->whereNull('withdrawn_at')->whereNull('deleted_at')->whereNotIn('slug', $present)->pluck('slug')->all();
        $official = Template::query()->official()->whereNull('deleted_at')->count();

        if ($gone === []) {
            return $report;
        }

        if ($present === [] || count($gone) > max(self::MOST_WITHDRAWN, intdiv($official, 4))) {
            $report['problems'][] = count($gone).' songs have no file ('.implode(', ', array_slice($gone, 0, 5)).(count($gone) > 5 ? ', ...' : '').'): not withdrawn. Is the content in place (server/tool/content.sh)?';

            return $report;
        }

        Template::query()->official()->whereIn('slug', $gone)->update(['withdrawn_at' => now()]);
        $report['withdrawn'] = [...$report['withdrawn'], ...array_values($gone)];

        return $report;
    }

    /**
     * Marks each song's staged next version, and clears it from every song without one (switched, or withdrawn). A
     * staged version must be newer than the live one.
     *
     * @param  array<string, int>  $staged  each song's staged version number, by its id
     * @return list<string> problems
     */
    private function stage(array $staged): array
    {
        $problems = [];
        foreach (Template::query()->official()->with('currentVersion')->where(fn ($q) => $q->whereNotNull('staged_version_id')->orWhereIn('slug', array_keys($staged)))->get() as $template) {
            $number = $staged[$template->slug] ?? null;
            $version = $number !== null ? TemplateVersion::query()->where('template_id', $template->id)->where('version', $number)->first() : null;
            if ($version !== null && $version->version <= (int) $template->currentVersion?->version) {
                $problems[] = "{$template->slug}.next.json: its version {$number} isn't newer than the live {$template->currentVersion?->version}";
                $version = null;
            }
            if ($template->staged_version_id !== $version?->id) {
                $template->forceFill(['staged_version_id' => $version?->id])->save();
            }
        }

        return $problems;
    }

    /**
     * Whether a song names its makers (a list; empty for traditional) and its first printing's year, or the year it was
     * written when it was never printed.
     *
     * @param  array<string, mixed>  $data
     */
    private static function hasRightsFacts(array $data): bool
    {
        $printed = is_array($data['firstPublished'] ?? null) && is_int($data['firstPublished']['year'] ?? null);

        return is_array($data['makers'] ?? null) && ($printed || is_int($data['made'] ?? null));
    }

    /**
     * @return list<string>
     */
    private function files(): array
    {
        $files = [];

        foreach ((array) config('templates.folders') as $folder) {
            foreach (glob(rtrim((string) $folder, '/').'/*.json') ?: [] as $path) {
                if (basename($path) !== 'index.json') {
                    $files[] = $path;
                }
            }
        }

        sort($files);

        return $files;
    }
}
