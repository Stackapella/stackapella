<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\Refusal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateNetworkRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Stats\NetworkReadiness;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * admin.network.*: the network features panel, and its thresholds (in sudo). Opening a feature
 * is its feature flag (admin.flags.update).
 */
final class NetworkController extends Controller
{
    public function show(NetworkReadiness $network): JsonResponse
    {
        return response()->json(['data' => $network->rows()]);
    }

    public function update(UpdateNetworkRequest $request, string $feature, NetworkReadiness $network, #[CurrentUser] User $admin): JsonResponse
    {
        $known = (array) config('network.features.'.$feature.'.thresholds', []);

        if ($known === []) {
            throw new Refusal(404, 'not_found');
        }

        /** @var array<string, int> $thresholds */
        $thresholds = array_map('intval', (array) $request->validated('thresholds'));
        $unknown = array_diff(array_keys($thresholds), array_keys($known));

        if ($unknown !== []) {
            throw ValidationException::withMessages(['thresholds' => __('validation.in', ['attribute' => implode(', ', $unknown)])]);
        }

        NetworkReadiness::setThresholds($feature, $thresholds);
        AuditLog::record('network.thresholds', null, $admin, after: [$feature => $thresholds], ip: $request->ip());

        return response()->json(['data' => $network->rows()]);
    }
}
