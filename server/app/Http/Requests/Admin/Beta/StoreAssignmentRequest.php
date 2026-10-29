<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Beta;

use App\Models\BetaAssignment;
use App\Models\BetaSinger;
use App\Models\Template;
use App\Rules\TemplatePart;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * admin.beta.assignments.create: another part for this singer.
 */
final class StoreAssignmentRequest extends FormRequest
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
            // A song's part, or a course clip (CourseClips) by its slot.
            'templateId' => ['required_without:clipId', 'nullable', 'string', 'exists:templates,slug'],
            'partKey' => ['required_with:templateId', 'nullable', 'string', new TemplatePart($this->input('templateId'))],
            'clipId' => ['required_without:templateId', 'nullable', 'string', Rule::exists('course_clips', 'slot')->whereNull('retired_at')],
            'minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $singer = $this->route('singer');
                $template = Template::query()->official()->where('slug', (string) $this->input('templateId'))->first();

                if ($singer instanceof BetaSinger && $template !== null
                    && BetaAssignment::query()->where('beta_singer_id', $singer->id)->where('template_id', $template->id)->where('part_key', $this->input('partKey'))->exists()) {
                    $validator->errors()->add('partKey', __('validation.already_assigned', ['name' => $singer->name]));
                }
            },
        ];
    }
}
