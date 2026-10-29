<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Enums\AdminRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * admin.me (contract/schemas/admin.me.json).
 *
 * @property User $resource
 */
final class AdminMeResource extends JsonResource
{
    public function __construct(User $user, private readonly AdminRole $role, private readonly ?Carbon $sudoUntil)
    {
        parent::__construct($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->sqid(),
            'name' => $this->resource->name,
            'email' => (string) $this->resource->email,
            'role' => $this->role->value,
            'sudoUntil' => $this->sudoUntil?->utc()->toIso8601ZuluString(),
        ];
    }
}
