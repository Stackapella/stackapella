<?php

declare(strict_types=1);

namespace App\Actions\Corrections;

use App\Models\Correction;

/**
 * A reader's correction, kept for the team (docs/corrections-plan.md): what they wrote and on which page, nothing about
 * them, even when signed in.
 */
final class SubmitCorrection
{
    /**
     * @param  array{kind: string, text: string, source?: string|null, page: array{type: string, path: string, title: string}, lang: string}  $input
     */
    public function handle(array $input): Correction
    {
        return Correction::query()->create([
            'kind' => $input['kind'],
            'text' => trim($input['text']),
            'source' => ($input['source'] ?? null) !== null && trim((string) $input['source']) !== '' ? trim((string) $input['source']) : null,
            'page_type' => $input['page']['type'],
            'page_path' => $input['page']['path'],
            'page_title' => trim($input['page']['title']),
            'lang' => $input['lang'],
        ]);
    }
}
