<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Ideas\Ideas;
use App\Actions\Templates\Library;
use App\Enums\SecurityEventType;
use App\Exceptions\Refusal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Templates\ShowTemplateRequest;
use App\Http\Resources\CatalogueSongResource;
use App\Http\Resources\GuideRecording;
use App\Models\Project;
use App\Models\Template;
use App\Models\TemplateGuide;
use App\Models\TemplateRecording;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Support\Templates\Catalogue;
use App\Support\Templates\PersonsTemplate;
use App\Support\Trust\IpHash;
use App\Support\Trust\Limits;
use App\Support\Trust\SecurityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * templates.catalogue: every song, for anyone, never the notes.
 */
final class TemplatesController extends Controller
{
    public function index(Request $request, Catalogue $catalogue): JsonResponse
    {
        $user = $request->user();
        $user = $user instanceof User ? $user : null;
        $songs = $catalogue->songs();

        $startable = $songs->filter(fn (Template $template): bool => $catalogue->mayStart($template, $user));
        $guides = $catalogue->guides($startable->pluck('current_version_id')->filter()->map(static fn (mixed $id): int => (int) $id)->values()->all());

        return response()->json([
            'data' => $songs->map(fn (Template $template): array => (new CatalogueSongResource($template, $startable->contains($template) ? $guides : collect()))->toArray($request))->values(),
        ]);
    }

    /**
     * templates.show: a song's full template and its voices, for whoever may start it. Sent with
     * an ETag; a 304, or the same song again the same day, isn't counted toward the daily cap.
     */
    public function show(ShowTemplateRequest $request, string $template, Catalogue $catalogue, SecurityLog $securityLog, Limits $limits): Response
    {
        $user = $request->user();
        $user = $user instanceof User ? $user : null;
        $song = Template::query()->with('currentVersion')->where('slug', $template)->whereNull('deleted_at')->first();

        if ($song === null) {
            throw new Refusal(404, 'not_found');
        }

        if (! $song->isOfficial()) {
            return $this->own($request, $song, $user, $catalogue);
        }

        $grant = $catalogue->grant($song, $user);

        if ($grant === null) {
            throw new Refusal(403, 'plus_required');
        }

        $number = $request->validated('version');
        $version = $number !== null ? TemplateVersion::query()->where('template_id', $song->id)->where('version', (int) $number)->first() : $song->currentVersion;

        if ($version === null) {
            throw new Refusal(404, 'not_found');
        }

        $voices = $catalogue->guides([$version->id])->flatten(1)->values();
        $etag = '"'.hash('sha256', $version->sha256.'|'.$voices->map(fn (TemplateGuide $g): string => $g->id.':'.$g->is_default.':'.$g->submission?->assignment->singer->credit())->implode(',')).'"';

        if ($request->headers->get('If-None-Match') === $etag) {
            return response()->noContent(304)->setEtag(trim($etag, '"'));
        }

        // The daily cap on Plus songs (free songs are bundled with the app anyway; admins review many).
        if (! in_array($grant, ['free', 'admin'], true)) {
            $who = $user !== null ? 'u'.$user->id : 'ip'.IpHash::daily((string) $request->ip());
            $seen = 'plus-downloads:seen:'.$who.':'.$song->id.':'.now()->toDateString();

            if (! Cache::has($seen)) {
                $key = 'plus-downloads:'.$who;
                $max = $limits->max('plus-downloads', 'account');

                if (RateLimiter::tooManyAttempts($key, $max)) {
                    $securityLog->record(SecurityEventType::RateLimited, $user, $request->ip(), ['limiter' => 'plus-downloads']);
                    $wait = max(1, RateLimiter::availableIn($key));

                    throw new Refusal(429, 'rate_limited', ['retryAfter' => $wait], ['Retry-After' => (string) $wait]);
                }

                RateLimiter::hit($key, 86400);
                Cache::put($seen, true, now()->endOfDay());
            }
        }

        return response()->json(['data' => [
            'template' => $version->data,
            'voices' => $voices->map(fn (TemplateGuide $g): array => [...GuideRecording::of($g), 'default' => $g->is_default])->all(),
            'grant' => $grant,
            'freeUntil' => $grant === 'month' ? $song->free_until?->copy()->endOfDay()->utc()->toIso8601ZuluString() : null,
        ]])->setEtag(trim($etag, '"'));
    }

    /**
     * A person's own template: for its owner, anyone with the link while it's unlisted, and the
     * members of a choir group it's shared with. Anyone else finds nothing. Its voices are the
     * creator's recorded guides, by temporary URL. In the library (N3): anyone, while it's listed;
     * held, as if unlisted; hidden by Hillel, its owner alone.
     */
    private function own(ShowTemplateRequest $request, Template $song, ?User $user, Catalogue $catalogue): Response
    {
        $library = app(Library::class);
        $grant = match (true) {
            $user !== null && $song->owner_id === $user->id => 'own',
            // Hidden by Hillel: its owner alone, whatever it's set to now.
            $song->hidden_at !== null => throw new Refusal(404, 'not_found'),
            $song->isPublic() && $library->isListed($song) => 'public',
            // A tune shared as an idea: anyone may sing it while it's listed.
            app(Ideas::class)->isListedFor($song) => 'idea',
            ($song->visibility === 'unlisted' || $song->isPublic()) && ! $song->isImported() => 'link',
            $user !== null && $catalogue->inGroup($song, $user) => 'group',
            // A singer on (or the owner of) a project made from it: the song they're singing. Not
            // an imported one: that stays with its owner and their choir groups.
            $user !== null && ! $song->isImported() && $this->inProject($song, $user) => 'project',
            default => throw new Refusal(404, 'not_found'),
        };

        $number = $request->validated('version');
        $version = $number !== null ? TemplateVersion::query()->where('template_id', $song->id)->where('version', (int) $number)->first() : $song->currentVersion;

        if ($version === null) {
            throw new Refusal(404, 'not_found');
        }

        $recordings = PersonsTemplate::recordings($song);
        // The voices' links last 2 hours; the hour in the ETag means a cached answer is never older
        // than one, so its links still work.
        $shelf = $library->view($song, $user);
        $etag = '"'.hash('sha256', $version->sha256.'|'.$grant.'|'.now()->format('YmdH').'|'.$recordings->map(fn (TemplateRecording $r): string => $r->uuid)->implode(',').'|'.json_encode($shelf)).'"';

        if ($request->headers->get('If-None-Match') === $etag) {
            return response()->noContent(304)->setEtag(trim($etag, '"'));
        }

        return response()->json(['data' => PersonsTemplate::payload($version, $recordings, $grant, $shelf)])->setEtag(trim($etag, '"'));
    }

    private function inProject(Template $song, User $user): bool
    {
        return Project::query()->where('template_slug', $song->slug)->whereNull('binned_at')
            ->where(fn ($query) => $query->where('user_id', $user->id)->orWhereHas('members', fn ($members) => $members->where('user_id', $user->id)))
            ->exists();
    }
}
