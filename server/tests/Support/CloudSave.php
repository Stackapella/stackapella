<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Jobs\ProcessTake;
use App\Models\Take;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Cloud save as the app's outbox does it: projects, parts and takes by their device UUIDs.
 */
trait CloudSave
{
    protected function owner(): User
    {
        // As a request loads it: every column (a new factory model has only what it set).
        $user = User::factory()->create()->fresh() ?? throw new \LogicException('No user.');
        $this->actingAs($user);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $with
     */
    protected function saveProject(string $id, int $version = 0, array $with = []): TestResponse
    {
        return $this->putJson('/api/projects/'.$id, ['changeId' => (string) Str::uuid(), 'version' => $version, 'title' => 'Amazing Grace', 'bpm' => 80, 'key' => 'G', 'timeSignature' => '3/4', 'template' => ['id' => 'amazing-grace', 'version' => self::amazingGraceVersion()], 'settings' => ['practice' => ['focus' => null]], 'createdAt' => '2026-10-20T09:00:00Z', ...$with]);
    }

    /** The bundled Amazing Grace's version, so a fix to the song never strands these tests on an old one. */
    protected static function amazingGraceVersion(): int
    {
        $template = json_decode((string) file_get_contents(base_path('../web/templates/amazing-grace.json')), true, 512, JSON_THROW_ON_ERROR);

        return is_array($template) && is_int($template['version'] ?? null) ? $template['version'] : throw new \LogicException('Amazing Grace has no version.');
    }

    /**
     * @param  array<string, mixed>  $with
     */
    protected function savePart(string $id, string $projectId, int $version = 0, array $with = []): TestResponse
    {
        return $this->putJson('/api/parts/'.$id, ['changeId' => (string) Str::uuid(), 'projectId' => $projectId, 'version' => $version, 'order' => 0, 'name' => 'Alto', 'color' => '#e4a94b', 'volume' => 1, 'pan' => 0, 'muted' => false, 'solo' => false, 'nudgeFrames' => 0, 'trimStartFrames' => 0, 'trimEndFrames' => 0, 'templatePartKey' => 'alto', 'currentTakeId' => null, 'settings' => ['useGuide' => false], 'createdAt' => '2026-10-20T09:05:00Z', ...$with]);
    }

    /**
     * A project with one part, both saved: [projectId, partId].
     *
     * @return array{0: string, 1: string}
     */
    protected function projectWithPart(): array
    {
        $project = (string) Str::uuid();
        $part = (string) Str::uuid();
        $this->saveProject($project)->assertOk();
        $this->savePart($part, $project)->assertOk();

        return [$project, $part];
    }

    /**
     * @param  array<string, mixed>  $with
     */
    protected function startTake(string $id, string $partId, string $wav, array $with = []): TestResponse
    {
        $frames = intdiv(strlen($wav) - 44, 2);

        return $this->postJson('/api/takes', ['id' => $id, 'partId' => $partId, 'sampleRate' => 48000, 'frames' => $frames, 'offsetFrames' => -960, 'bytes' => strlen($wav), 'sha256' => hash('sha256', $wav), 'scores' => ['pitchPercent' => 90], ...$with]);
    }

    /**
     * Record, upload, complete and process a take, as the outbox and the worker would.
     */
    protected function recordTake(string $partId, ?string $id = null, float $seconds = 1.0): Take
    {
        $id ??= (string) Str::uuid();
        $wav = Wav::tone($seconds);
        $this->startTake($id, $partId, $wav)->assertCreated();
        $take = Take::query()->where('uuid', $id)->firstOrFail();
        Storage::disk('private')->put($take->upload_key, $wav);
        $this->postJson('/api/takes/'.$id.'/complete')->assertOk();
        app()->call([new ProcessTake($take->id), 'handle']);

        return $take->refresh();
    }

    protected function pull(?string $cursor = null): TestResponse
    {
        return $this->getJson('/api/sync'.($cursor !== null ? '?cursor='.$cursor : ''));
    }
}
