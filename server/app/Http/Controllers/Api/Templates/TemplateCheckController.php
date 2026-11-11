<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Templates;

use App\Actions\Templates\OwnTemplates;
use App\Http\Controllers\Controller;
use App\Http\Requests\Templates\CheckTemplateRequest;
use App\Support\Contract\SchemaChecker;
use App\Support\Templates\ArrangementCheck;
use App\Support\Templates\TemplateRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * templates.check (docs/score-import-plan.md, section 3): a draft song checked before it's saved, so a director's
 * import shows what stops it (the template schema, TemplateRules and ArrangementCheck's hard errors: the same that
 * refuse a save or a sync) and what's only advice (the arranger's `--advice`: ranges, leaps, parallels, chords,
 * thirds, long lines with no breath). Nothing is kept.
 */
final class TemplateCheckController extends Controller
{
    public function __invoke(CheckTemplateRequest $request, TemplateRules $rules, ArrangementCheck $check): JsonResponse
    {
        /** @var array<string, mixed> $template */
        $template = (array) $request->validated('template');
        $json = (string) json_encode($template);

        if (strlen($json) > OwnTemplates::MAX_BYTES) {
            throw ValidationException::withMessages(['template' => __('validation.template_size', ['kb' => intdiv(OwnTemplates::MAX_BYTES, 1024)])]);
        }

        $schema = (new SchemaChecker((string) config('templates.schemas')))->check(json_decode($json, false), 'template.json');
        // The arrangement's checks need a template the schema takes.
        $found = $schema === [] ? $check->check($template) : ['hard' => [], 'advice' => []];

        return response()->json(['data' => [
            'problems' => array_values(array_unique([...$schema, ...($schema === [] ? $rules->problems($template) : []), ...$found['hard']])),
            'advice' => array_map(static function (string $line): array {
                [$kind, $text] = str_contains($line, ': ') ? explode(': ', $line, 2) : ['other', $line];

                return ['kind' => $kind, 'text' => $text];
            }, $found['advice']),
        ]]);
    }
}
