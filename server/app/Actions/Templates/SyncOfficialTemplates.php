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
use App\Support\Templates\TemplateRules;
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
 */
final class SyncOfficialTemplates
{
    /** At most this many songs withdrawn in one sync (or a quarter of them, if more). */
    private const MOST_WITHDRAWN = 3;

    public function __construct(private readonly TemplateRules $rules, private readonly SocialPoster $poster, private readonly CarryVoicesOver $carry, private readonly CourseClips $clips) {}

    /**
     * @return array{added: list<string>, unchanged: list<string>, problems: list<string>, carried: list<string>, withdrawn: list<string>}
     */
    public function handle(): array
    {
        $report = ['added' => [], 'unchanged' => [], 'problems' => [], 'carried' => [], 'withdrawn' => []];
        // Every song a file names, valid or not: a broken file is a problem, never a withdrawal.
        $present = [];
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

            $problems = [...$schemas->check(json_decode($json, false), 'template.json'), ...$this->rules->problems($data), ...$this->rules->officialProblems($data)];

            if ($problems !== []) {
                $report['problems'][] = "{$name}: ".implode('; ', $problems);

                continue;
            }

            /** @var array{id: string, version: int, title: string, reviewed: bool} $data */
            $result = $this->publish($data);

            // A song is new to singers when a version of it is first reviewed (added reviewed, or
            // its musician's check just came in): a post for Hillel to approve. Songs already out
            // before aren't proposed on every sync.
            if (($result === 'reviewed' || ($result === 'added' && $data['reviewed'])) && ($template = Template::query()->official()->where('slug', $data['id'])->first()) !== null
                && TemplateVersion::query()->where('template_id', $template->id)->where('reviewed', true)->count() === 1) {
                $this->poster->propose($template, SocialPost::NEW_SONG);
            }

            $result = $result === 'reviewed' ? 'unchanged' : $result;
            $report[$result === 'problem' ? 'problems' : $result][] = $result === 'problem'
                ? "{$name}: version {$data['version']} of {$data['id']} was published with different notes; give the change a new version number"
                : "{$data['id']}@{$data['version']}";
        }

        $report = $this->withdraw($present, $report);

        // Recorded voices whose part sings the same in a newer version follow it there.
        $report['carried'] = $this->carry->handle();

        // The courses' clips, defined beside them (web/course/plus/clips.json).
        $report['problems'] = [...$report['problems'], ...$this->clips->sync()];

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
        $template->forceFill(['current_version_id' => $current?->id])->save();
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
     * A song whose file has left the content (Hillel: "Remove them now", for the two the lawyer
     * hasn't cleared) is withdrawn: out of the library, its page and the sitemap, still served to
     * the projects made from it. One that comes back returns. Never many at once: a content fetch
     * that went wrong (no files, or most missing) is a problem to look at, not a withdrawal.
     *
     * @param  list<string>  $present
     * @param  array{added: list<string>, unchanged: list<string>, problems: list<string>, carried: list<string>, withdrawn: list<string>}  $report
     * @return array{added: list<string>, unchanged: list<string>, problems: list<string>, carried: list<string>, withdrawn: list<string>}
     */
    private function withdraw(array $present, array $report): array
    {
        Template::query()->official()->whereNotNull('withdrawn_at')->whereIn('slug', $present)->update(['withdrawn_at' => null]);
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
        $report['withdrawn'] = array_values($gone);

        return $report;
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
