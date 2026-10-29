<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminMeResource;
use App\Models\User;
use App\Support\Admin\AdminGate;
use App\Support\Admin\SecondFactor;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;

/**
 * admin.me: who is signed in to the admin, and their role (contract/api.md).
 */
final class MeController extends Controller
{
    public function __invoke(Request $request, #[CurrentUser] User $user, AdminGate $gate, SecondFactor $secondFactor): AdminMeResource
    {
        $session = $request->session();

        // The route's `editor` middleware has already checked there is a role.
        return new AdminMeResource($user, $gate->role($user, $session) ?? abort(403), $secondFactor->sudoUntil($session, $user));
    }
}
