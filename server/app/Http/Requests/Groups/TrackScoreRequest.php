<?php

declare(strict_types=1);

namespace App\Http\Requests\Groups;

use App\Actions\Groups\TrackScores;
use App\Models\TrackScore;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * groups.tracks.scores.create: one file of the score, before it's sent (a PDF, MusicXML or a photo
 * of a page, at most 25 MB).
 */
final class TrackScoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:200'],
            'contentType' => ['required', 'string', Rule::in(array_keys(TrackScore::KINDS))],
            'bytes' => ['required', 'integer', 'min:1', 'max:'.TrackScores::MAX_BYTES],
            'sha256' => ['required', 'string', 'regex:/^[a-fA-F0-9]{64}$/'],
        ];
    }
}
