<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Jobs\BuildExport;
use App\Models\DataExport;
use App\Models\User;

/**
 * Download my data (docs/backend-plan.md, B, after Event Schedule's ExportPersonalData): one at a
 * time, built in the background, then an email with a link that works for 7 days.
 */
final class RequestExport
{
    public function handle(User $user): DataExport
    {
        $preparing = DataExport::query()->where('user_id', $user->id)->where('status', 'preparing')->first();

        // One at a time, but one stuck for an hour (a worker lost it) doesn't block the next.
        if ($preparing !== null && $preparing->created_at?->isAfter(now()->subHour())) {
            return $preparing;
        }

        $preparing?->forceFill(['status' => 'failed'])->save();

        $export = new DataExport(['status' => 'preparing']);
        $export->forceFill(['user_id' => $user->id])->save();
        BuildExport::dispatch($export->id);

        return $export;
    }

    /**
     * The newest export, if any.
     */
    public function latest(User $user): ?DataExport
    {
        return DataExport::query()->where('user_id', $user->id)->orderByDesc('id')->first();
    }
}
