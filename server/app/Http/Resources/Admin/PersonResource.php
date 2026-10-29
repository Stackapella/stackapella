<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\User;

/**
 * Who did something in the admin (contract/schemas/admin.person.json): a Sqid and a name, or null.
 */
final class PersonResource
{
    /**
     * @return array{id: string, name: string}|null
     */
    public static function of(?User $user): ?array
    {
        return $user === null ? null : ['id' => $user->sqid(), 'name' => $user->name];
    }
}
