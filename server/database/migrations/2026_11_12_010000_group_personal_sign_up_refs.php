<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Hillel, 2026-11-11 (Q8: "Keep the song's count only"): an account keeps nothing that leads back to whoever brought it.
 * The sign-up tags that name a person or a thing of theirs (a shared song's h-<id>, a coach's c-<handle>, a singer's
 * p-<handle>) are kept from now on only as their group (RecordCounts::accountRef); the ones already kept, on the
 * account and in its sign-up's security event, become their group too. Safe to run again: a group never starts so.
 */
return new class extends Migration
{
    /** Each personal tag's prefix (a LIKE pattern) and the group it becomes (RecordCounts::source). */
    private const GROUPS = ['h-%' => 'share-song', 'c-%' => 'coach', 'p-%' => 'profile'];

    public function up(): void
    {
        foreach (self::GROUPS as $pattern => $group) {
            DB::table('users')->where('ref', 'like', $pattern)->update(['ref' => $group]);
            DB::update("update security_events set context = json_set(context, '$.ref', ?) where type = 'sign_up' and json_unquote(json_extract(context, '$.ref')) like ?", [$group, $pattern]);
        }
    }

    public function down(): void
    {
        // What was grouped isn't kept anywhere, so there's nothing to put back.
    }
};
