<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Editor routes (the queues): the same rules as EnsureAdmin, with editors allowed too.
 */
final class EnsureEditor extends EnsureAdmin
{
    protected function allowed(User $user, Request $request): bool
    {
        return $this->gate->isEditor($user, $request->session());
    }
}
