<?php

declare(strict_types=1);

namespace App\Http\Requests\Sharing;

use App\Models\Report;
use Illuminate\Foundation\Http\FormRequest;

/**
 * reports.create: why, an optional note, and the honeypot (`website`, left empty by people).
 */
final class ReportRequest extends FormRequest
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
            // One of: a shared harmony, a comment in the gallery, a public profile (N1), a template
            // in the library (N3), an idea (Ideas).
            'harmonyId' => ['required_without_all:commentId,handle,templateId,ideaId,choirVoiceId,requestId,boardEntryId', 'nullable', 'string', 'max:32'],
            'commentId' => ['required_without_all:harmonyId,handle,templateId,ideaId,choirVoiceId,requestId,boardEntryId', 'nullable', 'string', 'max:32'],
            'handle' => ['required_without_all:harmonyId,commentId,templateId,ideaId,choirVoiceId,requestId,boardEntryId', 'nullable', 'string', 'max:30'],
            'templateId' => ['required_without_all:harmonyId,commentId,handle,ideaId,choirVoiceId,requestId,boardEntryId', 'nullable', 'string', 'max:80'],
            'requestId' => ['required_without_all:harmonyId,commentId,handle,templateId,ideaId,choirVoiceId,boardEntryId', 'nullable', 'string', 'max:32'],
            'choirVoiceId' => ['required_without_all:harmonyId,commentId,handle,templateId,ideaId,requestId,boardEntryId', 'nullable', 'uuid'],
            'ideaId' => ['required_without_all:harmonyId,commentId,handle,templateId,choirVoiceId,requestId,boardEntryId', 'nullable', 'string', 'max:32'],
            // A name on a part leaderboard (signed in only).
            'boardEntryId' => ['required_without_all:harmonyId,commentId,handle,templateId,ideaId,choirVoiceId,requestId', 'nullable', 'string', 'max:32'],
            'reason' => ['required', 'string', 'in:'.implode(',', Report::REASONS)],
            'note' => ['nullable', 'string', 'max:500'],
            // The honeypot: anything in it is a bot, answered as any report is (never a 422).
            'website' => ['nullable'],
        ];
    }
}
