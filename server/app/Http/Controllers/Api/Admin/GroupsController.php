<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Support\Admin\GroupPractice;
use App\Support\Stats\ReportDay;
use Illuminate\Http\JsonResponse;

/**
 * Admin > Groups: admin.groups.list, each choir group's weekly practice of its own songs, as totals
 * only (docs/choir-tracks-plan.md, "How we'll know").
 */
final class GroupsController extends Controller
{
    public function index(GroupPractice $practice): JsonResponse
    {
        return response()->json(['data' => [...$practice->show(), 'timezone' => ReportDay::zone()]]);
    }
}
