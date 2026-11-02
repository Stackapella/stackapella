<?php

declare(strict_types=1);

namespace App\Support\Admin;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who has Plus now, as one query (App\Support\Usage\Plans per person, for lists): the demo plan, an
 * active coach, an invoice, a class code's year, a store, or a running Stripe subscription.
 */
final class PlusQuery
{
    /**
     * @param  Builder<User>  $query
     */
    public static function apply(Builder $query): void
    {
        $now = now();
        // Each date with its own "is not null": a NULL compared with now is unknown, and `whereNot`
        // around an unknown matches nobody, so the free side would be empty.
        $until = static fn (Builder $q, string $column) => $q->orWhere(fn (Builder $w) => $w->whereNotNull($column)->where($column, '>', $now));
        $query->where(function (Builder $q) use ($now, $until): void {
            $q->where('plan', 'plus')->orWhereNotNull('coach_active_at');
            $until($q, 'plus_paid_until');
            $until($q, 'school_plus_until');
            $until($q, 'store_plus_until');
            $q->orWhereExists(fn ($s) => $s->selectRaw('1')->from('subscriptions')->whereColumn('subscriptions.user_id', 'users.id')->where('type', 'default')
                ->whereIn('stripe_status', ['active', 'trialing', 'past_due'])->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>', $now)));
        });
    }
}
