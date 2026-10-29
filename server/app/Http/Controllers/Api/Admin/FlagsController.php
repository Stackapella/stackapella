<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateFlagRequest;
use App\Http\Resources\Admin\FlagResource;
use App\Models\User;
use App\Support\Flags;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * admin.flags.list and admin.flags.update: the switches for an attack and the dark features.
 */
final class FlagsController extends Controller
{
    public function index(Flags $flags): AnonymousResourceCollection
    {
        return FlagResource::collection($flags->all());
    }

    public function update(UpdateFlagRequest $request, string $name, Flags $flags, #[CurrentUser] User $admin): FlagResource
    {
        $flags->set($name, (string) $request->validated('value'), $admin, (string) $request->validated('reason'), $request->ip());

        $flag = collect($flags->all())->firstWhere('name', $name);

        return new FlagResource($flag);
    }
}
