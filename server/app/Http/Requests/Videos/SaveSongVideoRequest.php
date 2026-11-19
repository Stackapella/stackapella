<?php

declare(strict_types=1);

namespace App\Http\Requests\Videos;

use App\Enums\SongVideoHarmony;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * admin.videos.save: a recording's sync map from the sync tool (the admin, by the route's middleware). The shapes here;
 * App\Support\Videos\SyncMap checks the map against its song.
 */
final class SaveSongVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'song' => ['required', 'string', 'max:120'],
            'templateVersion' => ['required', 'integer', 'min:1'],
            'videoId' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{11}$/'],
            'form' => ['required', 'array', 'min:1', 'max:40'],
            'form.*.kind' => ['required', Rule::in(['section', 'gap'])],
            'form.*.section' => ['required_if:form.*.kind,section', 'integer', 'min:0', 'max:200'],
            'form.*.shift' => ['nullable', 'integer', 'between:-11,11'],
            'form.*.cents' => ['nullable', 'numeric', 'between:-50,50'],
            'form.*.words' => ['nullable', 'boolean'],
            'form.*.name' => ['nullable', 'string', 'max:60'],
            'form.*.beats' => ['required_if:form.*.kind,gap', 'numeric', 'gt:0', 'max:400'],
            'anchors' => ['required', 'array', 'min:2', 'max:3000'],
            'anchors.*' => ['required', 'array', 'size:2'],
            'anchors.*.*' => ['required', 'numeric', 'min:0', 'max:100000'],
            'harmony' => ['required', Rule::enum(SongVideoHarmony::class)],
            'otherWords' => ['nullable', 'array', 'max:100'],
            'otherWords.*' => ['required', 'array', 'size:2'],
            'otherWords.*.*' => ['required', 'numeric', 'min:0', 'max:100000'],
        ];
    }

    /**
     * The form as it is kept: each segment's own fields only, numbers as numbers.
     *
     * @return list<array<string, mixed>>
     */
    public function form(): array
    {
        /** @var list<array<string, mixed>> $form */
        $form = (array) $this->validated('form');

        return array_map(static function (array $s): array {
            if ($s['kind'] === 'gap') {
                return ['kind' => 'gap', 'beats' => (float) $s['beats']];
            }

            return array_filter([
                'kind' => 'section',
                'section' => (int) $s['section'],
                'shift' => (int) ($s['shift'] ?? 0),
                'cents' => round((float) ($s['cents'] ?? 0), 1),
                'words' => isset($s['words']) ? (bool) $s['words'] : null,
                'name' => isset($s['name']) && trim((string) $s['name']) !== '' ? trim((string) $s['name']) : null,
            ], static fn (mixed $v): bool => $v !== null);
        }, array_values($form));
    }

    /**
     * @return list<list<float>>
     */
    public function pairs(string $key): array
    {
        /** @var list<list<float|int|string>> $pairs */
        $pairs = (array) ($this->validated($key) ?? []);

        return array_map(static fn (array $p): array => [round((float) $p[0], 3), round((float) $p[1], 6)], array_values($pairs));
    }
}
