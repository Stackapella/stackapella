<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Support\Ids;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A server record addressed by its Sqid: in routes ({user} binds a Sqid) and in API resources.
 *
 * @mixin Model
 */
trait HasSqid
{
    public function sqid(): string
    {
        return Ids::encode((int) $this->getKey());
    }

    /**
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        if ($field !== null) {
            return parent::resolveRouteBinding($value, $field);
        }

        $id = is_string($value) ? Ids::decode($value) : null;

        return $id === null ? null : static::query()->whereKey($id)->first();
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWhereSqid(Builder $query, string $sqid): Builder
    {
        return $query->whereKey(Ids::decode($sqid) ?? 0);
    }
}
