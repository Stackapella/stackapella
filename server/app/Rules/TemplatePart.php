<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Template;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A part key that exists in this template's current version (the template given by its slug).
 */
final class TemplatePart implements ValidationRule
{
    public function __construct(private readonly mixed $templateSlug) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $template = is_string($this->templateSlug) ? Template::query()->official()->with('currentVersion')->where('slug', $this->templateSlug)->first() : null;

        if ($template === null) {
            return; // The templateId's own rule reports a missing template.
        }

        $keys = array_column((array) ($template->currentVersion->data['parts'] ?? []), 'key');

        if (! in_array($value, $keys, true)) {
            $fail(__('validation.part_missing', ['template' => $template->title, 'part' => is_string($value) ? $value : '']));
        }
    }
}
