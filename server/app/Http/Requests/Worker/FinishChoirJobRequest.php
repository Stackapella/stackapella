<?php

declare(strict_types=1);

namespace App\Http\Requests\Worker;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * worker.choir.jobs.finish: null when every part is in, else why it failed.
 */
final class FinishChoirJobRequest extends FormRequest
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
            // The token its claim gave: only the claim holding it acts on the job.
            'claim' => ['required', 'string', 'size:32'],
            'failed' => ['present', 'nullable', 'in:clip_unusable,gates_failed,render_failed,reference_gone'],
            // This claim's GPU time so far (the GPU review): optional, 0 or more.
            'gpuSeconds' => ['sometimes', 'numeric', 'min:0', 'max:86400'],
            // Why a clip couldn't be used (shown to its singer), and anonymous counts' inputs (2026-11-23).
            'detail' => ['sometimes', 'nullable', 'in:length,quiet,loud,noisy,edge,short,notes'],
            'level' => ['sometimes', 'nullable', 'in:quiet,ok,loud'],
            'partTries' => ['sometimes', 'nullable', 'array', 'max:16'],
            'partTries.*.partKey' => ['required', 'string', 'max:40'],
            'partTries.*.tries' => ['required', 'integer', 'min:1', 'max:20'],
            'gateFailures' => ['sometimes', 'nullable', 'array', 'max:64'],
            'gateFailures.*.partKey' => ['required', 'string', 'max:40'],
            'gateFailures.*.reason' => ['sometimes', 'nullable', 'string', 'max:40'],
            // The worst notes' distance from pitch, signed (minus is flat), worst first (2026-11-24): numbers only.
            'gateFailures.*.cents' => ['sometimes', 'nullable', 'array', 'list', 'max:3'],
            'gateFailures.*.cents.*' => ['numeric', 'between:-1200,1200'],
            'partWorst' => ['sometimes', 'nullable', 'array', 'list', 'max:8'],
            'partWorst.*' => ['array:partKey,cents'],
            'partWorst.*.partKey' => ['required', 'string', 'max:40'],
            'partWorst.*.cents' => ['required', 'array', 'list', 'max:3'],
            'partWorst.*.cents.*' => ['numeric', 'between:-1200,1200'],
            // An unusable clip's measures (2026-11-23): numbers only, never audio or text from the take.
            'detailNumbers' => ['sometimes', 'nullable', 'prohibited_unless:failed,clip_unusable', 'array', 'list', 'max:16'],
            'detailNumbers.*' => ['array:name,value'],
            'detailNumbers.*.name' => ['required', 'string', 'regex:/^[A-Za-z][A-Za-z0-9_]{0,31}$/', 'distinct'],
            'detailNumbers.*.value' => ['required', 'numeric', 'between:-1000000000,1000000000'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            static function (Validator $validator): void {
                $numbers = $validator->getData()['detailNumbers'] ?? null;

                if (is_array($numbers) && strlen((string) json_encode($numbers)) > 2048) {
                    $validator->errors()->add('detailNumbers', 'At most 2 KB.');
                }
            },
        ];
    }
}
