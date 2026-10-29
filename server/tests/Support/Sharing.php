<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Harmony;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Sharing as the app does it: a cloud-saved project with a recorded take, then a mix rendered on
 * the device, sent by presigned PUT and completed (ProcessHarmony runs on the sync queue).
 */
trait Sharing
{
    use CloudSave;

    /**
     * A project with one recorded part: [projectId, partId].
     *
     * @return array{0: string, 1: string}
     */
    protected function recordedProject(float $seconds = 2.0): array
    {
        [$project, $part] = $this->projectWithPart();
        $take = $this->recordTake($part, seconds: $seconds);
        $this->savePart($part, $project, 1, ['currentTakeId' => $take->uuid])->assertOk();

        return [$project, $part];
    }

    /**
     * @param  array<string, mixed>  $with
     */
    protected function startHarmony(string $projectId, string $wav, array $with = []): TestResponse
    {
        $channels = unpack('v', substr($wav, 22, 2))[1] ?? 1;
        $frames = intdiv(strlen($wav) - 44, 2 * $channels);
        $outline = base64_encode(str_repeat(chr(200), (int) ceil($frames / 48000 * 20)));

        return $this->postJson('/api/harmonies', [
            'changeId' => (string) Str::uuid(),
            'projectId' => $projectId,
            'partId' => null,
            'title' => 'Amazing Grace for Gran',
            'visibility' => 'unlisted',
            'sampleRate' => 48000,
            'channels' => $channels,
            'frames' => $frames,
            'bytes' => strlen($wav),
            'sha256' => hash('sha256', $wav),
            'beatZeroSeconds' => 0.5,
            'guides' => [],
            'tracks' => [['name' => 'Alto', 'color' => '#e4a94b', 'peaks' => $outline], ['name' => 'Soprano', 'color' => '#6fa8dc', 'peaks' => $outline]],
            ...$with,
        ]);
    }

    /**
     * Share a mix all the way: create, PUT, complete, processed.
     *
     * @param  array<string, mixed>  $with
     */
    protected function shareHarmony(string $projectId, ?string $wav = null, array $with = []): Harmony
    {
        $wav ??= Wav::stereo(2.0);
        $id = (string) $this->startHarmony($projectId, $wav, $with)->assertCreated()->json('data.id');
        $harmony = Harmony::query()->where('public_id', $id)->firstOrFail();
        Storage::disk('private')->put($harmony->upload_key, $wav);
        $this->postJson('/api/harmonies/'.$id.'/complete')->assertOk();

        return $harmony->refresh();
    }
}
