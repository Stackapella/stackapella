<?php

declare(strict_types=1);

namespace App\Support\Admin;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The people who hear about admin security events (a sign-in, a new factor, a lockout): users
 * flagged admin whose Google ID is on the list. The email check needs a live session, so it
 * can't apply here.
 */
final class Admins
{
    /**
     * @return Collection<int, User>
     */
    public function all(): Collection
    {
        $ids = (array) config('admin.admin_google_ids');

        if ($ids === []) {
            return new Collection;
        }

        return User::query()
            ->where('is_admin', true)
            ->whereNull('suspended_at')
            ->whereNotNull('email')
            ->whereIn('google_id', $ids)
            ->get();
    }
}
