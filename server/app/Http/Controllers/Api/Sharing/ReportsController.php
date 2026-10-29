<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Sharing;

use App\Actions\Sharing\FileReport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sharing\ReportRequest;
use App\Models\User;
use App\Support\Http\Hosts;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

/**
 * reports.create: anyone, signed in or not.
 */
final class ReportsController extends Controller
{
    public function __invoke(ReportRequest $request, FileReport $report, #[CurrentUser] ?User $user): Response
    {
        /** @var array{harmonyId?: string|null, commentId?: string|null, handle?: string|null, templateId?: string|null, ideaId?: string|null, choirVoiceId?: string|null, requestId?: string|null, reason: string, note?: string|null, website?: mixed} $data */
        $data = $request->validated();

        // Sent from another site's page (a form posting on its visitors' behalf, each a new
        // network): answered the same, and recorded nowhere.
        if (Hosts::fromOurPages($request)) {
            $report->handle($user, $data, (string) $request->ip());
        }

        return response()->noContent();
    }
}
