<?php

declare(strict_types=1);

namespace App\Support\Sync;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The next number in a person's own change sequence (users.sync_seq): every project, part, take
 * or tombstone a change touches is stamped with it, so sync.pull can ask for everything since.
 * One atomic statement (MySQL's LAST_INSERT_ID(expr)), so two changes never share a number.
 */
final class SyncSeq
{
    /**
     * Change numbers for several rows at once: the first of `$count` in a row.
     */
    public function reserve(User|int $user, int $count): int
    {
        $id = $user instanceof User ? $user->id : $user;

        DB::update('update users set sync_seq = last_insert_id(sync_seq + ?) where id = ?', [$count, $id]);

        return (int) DB::selectOne('select last_insert_id() as seq')->seq - $count + 1;
    }

    public function next(User|int $user): int
    {
        $id = $user instanceof User ? $user->id : $user;

        DB::update('update users set sync_seq = last_insert_id(sync_seq + 1) where id = ?', [$id]);

        return (int) DB::selectOne('select last_insert_id() as seq')->seq;
    }
}
