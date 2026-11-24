<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Growth\GrowthCsv;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GrowthExportRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/** admin.growth.export: a Growth page's numbers as CSV, for an admin or a growth viewer, each download audit-logged. */
final class GrowthExportController extends Controller
{
    public function __invoke(GrowthExportRequest $request, GrowthCsv $csv, #[CurrentUser] User $user): JsonResponse
    {
        $page = (string) $request->validated('page');
        /** @var array{period?: string|null, days?: int|null, experiment?: string|null} $params */
        $params = array_filter($request->safe()->only(['period', 'days', 'experiment']), static fn (mixed $v): bool => $v !== null);
        $made = $csv->build($page, $params);

        AuditLog::record('admin.growth.export', null, $user, after: ['page' => $page, ...$params, 'from' => $made['from'], 'to' => $made['to'], 'rows' => $made['rows']], ip: $request->ip());

        return response()->json(['data' => ['filename' => $made['filename'], 'csv' => $made['csv'], 'rows' => $made['rows']]]);
    }
}
