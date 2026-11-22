<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A friend who leaves a choir song stays on record (choir together's security and privacy review): when they left and
 * why, their first name kept for the owner's notice (so it outlives their account), and when the owner saw it. Their
 * parts and files still go. Guarded; safe to run again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('own_choir_members', function (Blueprint $table): void {
            if (! Schema::hasColumn('own_choir_members', 'left_at')) {
                $table->string('name', 80)->default('')->after('user_id');
                $table->timestamp('left_at')->nullable()->after('seen_at');
                $table->string('left_reason', 20)->nullable()->after('left_at');
                $table->timestamp('owner_seen_at')->nullable()->after('left_reason');
            }
        });

        // The account can go and its row stay (the owner's notice): the user's key set to null, not the row deleted.
        $column = collect(Schema::getColumns('own_choir_members'))->firstWhere('name', 'user_id');

        if ($column !== null && ($column['nullable'] ?? false) !== true) {
            $key = collect(Schema::getForeignKeys('own_choir_members'))->first(static fn (array $k): bool => $k['columns'] === ['user_id']);

            if ($key !== null) {
                Schema::table('own_choir_members', function (Blueprint $table): void {
                    $table->dropForeign(['user_id']);
                });
            }

            Schema::table('own_choir_members', function (Blueprint $table): void {
                $table->unsignedBigInteger('user_id')->nullable()->change();
            });
        }

        if (collect(Schema::getForeignKeys('own_choir_members'))->first(static fn (array $k): bool => $k['columns'] === ['user_id']) === null) {
            Schema::table('own_choir_members', function (Blueprint $table): void {
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        // Names for members who joined before.
        DB::table('own_choir_members')->where('name', '')->whereNotNull('user_id')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                $name = (string) DB::table('users')->where('id', $row->user_id)->value('name');
                DB::table('own_choir_members')->where('id', $row->id)->update(['name' => mb_substr(trim(explode(' ', trim($name))[0] ?? ''), 0, 80)]);
            }
        });
    }

    public function down(): void
    {
        // Left as it is: rows of friends whose accounts are gone have no user to restore.
    }
};
