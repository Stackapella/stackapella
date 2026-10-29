<?php

declare(strict_types=1);

namespace App\Actions\Projects;

use App\Enums\TemplateAccess;
use App\Exceptions\Refusal;
use App\Models\Project;
use App\Models\Template;
use App\Models\TemplateUnlock;
use App\Models\User;
use App\Support\Sync\SyncSeq;
use App\Support\Templates\Catalogue;
use App\Support\Usage\Plans;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * projects.save: make a project (version 0) or change one (the version it was based on), the
 * device's way (docs/backend-plan.md C5): a stale version is a conflict with the server's copy,
 * never a silent overwrite, and a replayed change (its changeId) answers as it is.
 */
final class SaveProject
{
    public function __construct(private readonly Owned $owned, private readonly Plans $plans, private readonly SyncSeq $seq, private readonly Catalogue $catalogue) {}

    /**
     * @param  array{changeId: string, version: int, title: string|null, bpm: float|int, key?: string|null, timeSignature: string, template?: array{id: string, version: int}|null, settings?: array<string, mixed>, createdAt?: string|null}  $data
     */
    public function handle(User $user, string $uuid, array $data): Project
    {
        try {
            return DB::transaction(function () use ($user, $uuid, $data): Project {
                Owned::lockAccount($user);
                $project = Project::query()->where('uuid', $uuid)->lockForUpdate()->first();

                Owned::ownerOnly($user, $project);

                // The same change again (its answer was lost): answered as it is now.
                if ($project !== null && $project->last_change_id === $data['changeId']) {
                    return $project;
                }

                if ($project === null) {
                    // Only a new project starts at 0; anything else was purged (sync says so).
                    if ($data['version'] !== 0) {
                        throw new Refusal(404, 'not_found');
                    }

                    if (! $this->plans->mayAddProject($user)) {
                        throw new Refusal(403, 'project_limit');
                    }

                    $project = new Project(['device_created_at' => isset($data['createdAt']) ? Carbon::parse($data['createdAt'])->utc() : null]);
                    $project->forceFill(['uuid' => $uuid, 'user_id' => $user->id, 'version' => 0]);
                } else {
                    Owned::live($project);

                    if ($data['version'] !== $project->version) {
                        throw Owned::conflict($project);
                    }

                    // A read-only project still keeps its practice state (its settings), nothing else.
                    if (! $this->onlySettings($project, $data)) {
                        $this->plans->ensureEditable($user, $project);
                    }
                }

                $this->ensureMayName($user, $project, $data['template']['id'] ?? null);

                $project->fill([
                    'title' => trim((string) $data['title']),
                    'bpm' => $data['bpm'],
                    'music_key' => $data['key'] ?? null,
                    'time_signature' => $data['timeSignature'],
                    'template_slug' => $data['template']['id'] ?? null,
                    'template_version' => $data['template']['version'] ?? null,
                    'settings' => $data['settings'] ?? [],
                ]);
                $isNew = ! $project->exists;
                $project->forceFill(['version' => $project->version + 1, 'last_change_id' => $data['changeId'], 'seq' => $this->seq->next($user)])->save();

                // Started from the song of the month: the person keeps it ("finish your song").
                if ($isNew && $project->template_slug !== null) {
                    $song = Template::query()->where('slug', $project->template_slug)->first();

                    if ($song !== null && $song->access === TemplateAccess::Plus && $song->isFreeOn(now())) {
                        TemplateUnlock::query()->firstOrCreate(['user_id' => $user->id, 'template_id' => $song->id]);
                    }
                }

                return $project;
            });
        } catch (UniqueConstraintViolationException) {
            // The same new project saved twice at once: the other save made it (a replay of this
            // very change answers as it is).
            $made = $this->owned->project($user, $uuid);

            if ($made->last_change_id === $data['changeId']) {
                return $made;
            }

            throw Owned::conflict($made);
        }
    }

    /**
     * @param  array{title: string|null, bpm: float|int, key?: string|null, timeSignature: string, template?: array{id: string, version: int}|null}  $data
     */
    private function onlySettings(Project $project, array $data): bool
    {
        return trim((string) $data['title']) === $project->title
            && (float) $data['bpm'] === (float) $project->bpm
            && ($data['key'] ?? null) === $project->music_key
            && $data['timeSignature'] === $project->time_signature
            && ($data['template']['id'] ?? null) === $project->template_slug
            && (isset($data['template']) ? (int) $data['template']['version'] : null) === $project->template_version;
    }

    /**
     * A project names someone's own template only when its owner may open it now (theirs, by its
     * link while unlisted, or by a choir group). The project's singers then open it through the
     * project (templates.show's `project` grant), so naming one is that grant: a private
     * template's id alone must not give it. A project that already names it keeps it.
     */
    private function ensureMayName(User $user, Project $project, ?string $slug): void
    {
        if ($slug === null || ($project->exists && $project->template_slug === $slug)) {
            return;
        }

        $template = Template::query()->where('slug', $slug)->whereNotNull('owner_id')->first();

        if ($template === null || $template->owner_id === $user->id || $template->visibility === 'unlisted' || $this->catalogue->inGroup($template, $user)) {
            return;
        }

        throw ValidationException::withMessages(['template.id' => __('validation.exists', ['attribute' => 'template.id'])]);
    }
}
