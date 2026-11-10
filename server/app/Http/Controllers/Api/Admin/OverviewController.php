<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Admin\Overview;
use App\Support\Outreach\Outreach;
use App\Support\Stats\ReportDay;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * admin.overview: the numbers that matter, from our own records.
 */
final class OverviewController extends Controller
{
    public function __invoke(Overview $overview, #[CurrentUser] User $user): JsonResponse
    {
        // Outreach is the admin's alone: its follow-ups due and replies unread, for "What needs you now".
        $outreach = $user->is_admin ? ['followUpsDue' => Outreach::followUpsDue(), 'unreadReplies' => Outreach::unreadReplies(), 'sendBySoon' => Outreach::sendBySoonCount()] : null;

        return response()->json(['data' => [...$overview->numbers(), 'outreach' => $outreach, 'timezone' => ReportDay::zone()]]);
    }
}
