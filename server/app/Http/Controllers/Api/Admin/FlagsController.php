<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\Refusal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateFlagRequest;
use App\Http\Resources\Admin\FlagResource;
use App\Models\User;
use App\Support\Flags;
use App\Support\Legal\DmcaAgent;
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
        // Sheet music import goes on only with the DMCA agent named (docs/sheet-import-legal.md).
        if ($name === 'sheet_import' && $request->validated('value') !== 'off' && ! DmcaAgent::registered()) {
            throw new Refusal(409, 'dmca_agent_missing');
        }

        $flags->set($name, (string) $request->validated('value'), $admin, (string) $request->validated('reason'), $request->ip());

        $flag = collect($flags->all())->firstWhere('name', $name);

        return new FlagResource($flag);
    }
}
