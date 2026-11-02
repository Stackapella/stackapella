<?php

declare(strict_types=1);

namespace App\Http\Requests\Experiments;

use App\Actions\Counts\Funnel;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * admin.experiments.create and .update: an experiment's fields (all on create, any while a draft
 * on update) and, on update, a step (`action`).
 */
final class ExperimentRequest extends FormRequest
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
        $creating = $this->isMethod('POST');
        $need = $creating ? 'required' : 'sometimes';
        $metrics = Funnel::kinds();

        return [
            'id' => $creating ? ['required', 'string', 'regex:/^[a-z][a-z0-9-]{1,23}$/', 'unique:experiments,key'] : ['prohibited'],
            'what' => [$need, 'string', 'max:200'],
            'hypothesis' => [$need, 'string', 'max:500'],
            // Two or three arms, a, b (and c), in order; a is always today's app. Weights add up to 100.
            'arms' => [$need, 'array', 'min:2', 'max:3', function (string $attribute, mixed $value, Closure $fail): void {
                $arms = is_array($value) ? array_values($value) : [];
                $names = array_map(static fn (mixed $arm): mixed => is_array($arm) ? ($arm['arm'] ?? null) : null, $arms);

                if ($names !== array_slice(['a', 'b', 'c'], 0, count($arms)) || array_sum(array_map(static fn (mixed $arm): int => is_array($arm) ? (int) ($arm['weight'] ?? 0) : 0, $arms)) !== 100) {
                    $fail(__('validation.experiment_arms'));
                }
            }],
            'arms.*.arm' => ['required_with:arms', 'string', 'in:a,b,c'],
            'arms.*.weight' => ['required_with:arms', 'integer', 'min:1', 'max:99'],
            'metric' => [$need, 'string', Rule::in($metrics)],
            'guardrails' => [$need, 'array', 'max:3'],
            'guardrails.*' => ['string', Rule::in($metrics)],
            'audience' => [$need, 'array:new,skin,size,signedIn,firstWeek'],
            'audience.new' => ['sometimes', 'boolean'],
            'audience.skin' => ['sometimes', 'in:game,serious'],
            'audience.size' => ['sometimes', 'in:phone,desktop'],
            'audience.signedIn' => ['sometimes', 'boolean'],
            // Enrolling a singer in their first week needs Hillel's OK.
            'audience.firstWeek' => ['sometimes', 'boolean'],
            'excludes' => [$need, 'array', 'max:5'],
            'excludes.*' => ['string', 'exists:experiments,key'],
            'plannedPerArm' => [$need, 'integer', 'min:50', 'max:1000000'],
            'action' => $creating ? ['prohibited'] : ['nullable', 'in:start,stop,decide,close'],
            'winner' => ['required_if:action,decide', 'nullable', 'in:a,b,c'],
        ];
    }

    /**
     * The experiment's own fields, without the step.
     *
     * @return array<string, mixed>
     */
    public function fields(): array
    {
        return array_diff_key($this->validated(), array_flip(['id', 'action', 'winner']));
    }
}
