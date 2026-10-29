<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateLimitRequest;
use App\Http\Resources\Admin\LimitResource;
use App\Models\User;
use App\Support\Trust\Limits;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * admin.limits.list and admin.limits.update: every limiter's rules, changed live.
 */
final class LimitsController extends Controller
{
    public function index(Limits $limits): AnonymousResourceCollection
    {
        $changes = $limits->changes();
        $rows = [];
        foreach ($limits->all() as $limiter => $rules) {
            foreach ($rules as $rule => $values) {
                $rows[] = ['limiter' => $limiter, 'rule' => $rule, ...$values, 'change' => $changes["{$limiter}.{$rule}"] ?? null];
            }
        }

        return LimitResource::collection($rows);
    }

    public function update(UpdateLimitRequest $request, string $limiter, string $rule, Limits $limits, #[CurrentUser] User $admin): LimitResource
    {
        $max = $request->validated('max');
        $limits->override($limiter, $rule, $max === null ? null : (int) $max, $admin, (string) $request->validated('reason'), $request->ip());

        return new LimitResource(['limiter' => $limiter, 'rule' => $rule, ...$limits->all()[$limiter][$rule], 'change' => $limits->changes()["{$limiter}.{$rule}"] ?? null]);
    }
}
