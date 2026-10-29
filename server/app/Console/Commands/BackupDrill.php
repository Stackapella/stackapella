<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Backups\DatabaseDump;
use App\Support\Ops\Readiness;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * The restore drill (docs/backend-plan.md A3), monthly: the newest backup is restored into a
 * scratch database on the same server, checked, then dropped. The checks: every table we have is
 * there, with rows where the live one has them; every live row the backup should hold came back
 * (by id); no foreign key in the copy points at nothing; the latest migration is recorded. A
 * backup that can't be restored isn't a backup. On a managed server, RESTORE_DRILL_DATABASE names a scratch database
 * the app's user can fill and empty.
 */
final class BackupDrill extends Command
{
    protected $signature = 'app:backup:drill';

    protected $description = 'Restore the newest backup into a scratch database, check it, and drop it';

    /** How long before a backup a row must have been made to be sure to be in it. */
    private const SETTLE_MINUTES = 10;

    public function handle(DatabaseDump $dump): int
    {
        $newest = collect(Storage::disk('backup')->files('db/daily'))->sort()->last();

        if (! is_string($newest)) {
            $this->error('There is no backup to restore.');

            return self::FAILURE;
        }

        $live = $dump->connection()['database'];
        $madeAt = (preg_match('#(\d{4}-\d{2}-\d{2}-\d{6})#', $newest, $m) === 1 ? Carbon::createFromFormat('Y-m-d-His', $m[1]) : null) ?? now()->subDay();
        // Rows made in the last minutes before the backup may not be in it: a transaction still
        // open when the dump began lands after it. Only rows older than this must have come back.
        $settled = $madeAt->copy()->subMinutes(self::SETTLE_MINUTES);
        $given = (string) config('database.restore_drill_database');
        $scratch = $given !== '' ? $given : $live.'_restore_drill';

        if (($unsafe = self::unsafe($scratch, $live)) !== null) {
            $this->error($unsafe);

            return self::FAILURE;
        }

        $local = storage_path('app/'.Str::random(12).'.sql.gz');
        $problems = [];
        $rows = 0;
        $keys = 0;

        try {
            $stream = Storage::disk('backup')->readStream($newest);
            file_put_contents($local, $stream);
            $this->empty($scratch, $given !== '');
            $dump->load($local, $scratch);

            $latest = DB::table('migrations')->max('migration');
            $restored = DB::select("select max(migration) as latest from `{$scratch}`.`migrations`")[0]->latest ?? null;
            // A deploy since the backup may have added tables it can't have.
            $behind = $restored !== null && (string) $restored < (string) $latest;

            foreach (DB::select('select table_name as name, table_rows as estimate from information_schema.tables where table_schema = ?', [$live]) as $table) {
                $name = (string) $table->name;
                $there = DB::select('select count(*) as total from information_schema.tables where table_schema = ? and table_name = ?', [$scratch, $name])[0]->total ?? 0;

                if ((int) $there === 0) {
                    if ($behind) {
                        $this->warn("{$name} isn't in the backup (made before the latest migration).");
                    } else {
                        $problems[] = "{$name} is missing";
                    }

                    continue;
                }

                $restoredRows = (int) (DB::select("select count(*) as total from `{$scratch}`.`{$name}`")[0]->total ?? 0);
                $rows += $restoredRows;

                // Rows the live table had when the backup was made must have come back.
                if (Schema::hasColumn($name, 'created_at') && $restoredRows === 0 && DB::table($name)->where('created_at', '<', $settled)->exists()) {
                    $problems[] = "{$name} came back empty";

                    continue;
                }

                if (($lost = self::lost($name, $scratch, $settled)) > 0) {
                    $problems[] = "{$name} is missing {$lost} rows the backup should hold";
                }
            }

            [$keys, $dangling] = self::dangling($live, $scratch);
            array_push($problems, ...$dangling);

            if ($restored === null) {
                $problems[] = 'no migrations recorded';
            } elseif ($behind) {
                $this->warn("The backup is from before {$latest} (it has {$restored}).");
            }
        } catch (Throwable $e) {
            report($e);
            $problems[] = $e->getMessage();
        } finally {
            @unlink($local);
            $this->empty($scratch, $given !== '', drop: true);
        }

        if ($problems !== []) {
            $this->error("The drill failed for {$newest}: ".implode('; ', $problems).'.');

            return self::FAILURE;
        }

        Readiness::stamp(Readiness::DRILL, ['at' => now()->utc()->toIso8601ZuluString(), 'file' => $newest, 'rows' => $rows, 'keys' => $keys]);
        $this->info("Restored {$newest} into a scratch database, checked every table ({$rows} rows, {$keys} foreign keys), and dropped it.");

        return self::SUCCESS;
    }

    /**
     * Live rows the backup should hold but doesn't: an id at or below the highest it restored,
     * made before `$settled` (the backup's time, less SETTLE_MINUTES). Tables without an integer
     * id are checked by the counts above only.
     */
    private static function lost(string $table, string $scratch, Carbon $settled): int
    {
        if (! Schema::hasColumn($table, 'id') || ! in_array(Schema::getColumnType($table, 'id'), ['bigint', 'int', 'integer', 'mediumint'], true)) {
            return 0;
        }

        $highest = DB::select("select max(id) as highest from `{$scratch}`.`{$table}`")[0]->highest ?? null;

        if ($highest === null) {
            return 0;
        }

        $recent = Schema::hasColumn($table, 'created_at') ? ' and (l.created_at is null or l.created_at < ?)' : '';
        $bindings = $recent !== '' ? [(int) $highest, $settled] : [(int) $highest];

        return (int) (DB::select("select count(*) as lost from `{$table}` l left join `{$scratch}`.`{$table}` s on s.id = l.id where l.id <= ? and s.id is null{$recent}", $bindings)[0]->lost ?? 0);
    }

    /**
     * Every foreign key the live database has, checked in the copy: no row may point at nothing
     * (the copy is loaded with the checks off, so it would load anyway).
     *
     * @return array{0: int, 1: list<string>}
     */
    private static function dangling(string $live, string $scratch): array
    {
        $keys = DB::select('select table_name as child, column_name as col, referenced_table_name as parent, referenced_column_name as ref from information_schema.key_column_usage where table_schema = ? and referenced_table_name is not null', [$live]);
        $problems = [];

        foreach ($keys as $key) {
            $there = DB::select('select count(*) as total from information_schema.tables where table_schema = ? and table_name in (?, ?)', [$scratch, $key->child, $key->parent])[0]->total ?? 0;

            if ((int) $there < 2) {
                continue;
            }

            $count = (int) (DB::select("select count(*) as total from `{$scratch}`.`{$key->child}` c left join `{$scratch}`.`{$key->parent}` p on p.`{$key->ref}` = c.`{$key->col}` where c.`{$key->col}` is not null and p.`{$key->ref}` is null")[0]->total ?? 0);

            if ($count > 0) {
                $problems[] = "{$count} {$key->child}.{$key->col} point at no {$key->parent}";
            }
        }

        return [count($keys), $problems];
    }

    /**
     * Why a scratch database must not be used, or null when it's safe: never the live database,
     * and one given to us must say it's for the drill (its tables are dropped).
     */
    public static function unsafe(string $scratch, string $live): ?string
    {
        if (strcasecmp($scratch, $live) === 0) {
            return "The drill database is the live database ({$live}): set RESTORE_DRILL_DATABASE to a scratch one.";
        }

        if (preg_match('/drill|restore|scratch/i', $scratch) !== 1) {
            return "The drill database ({$scratch}) must have \"drill\", \"restore\" or \"scratch\" in its name: everything in it is dropped.";
        }

        return null;
    }

    /**
     * A clean scratch database: its tables dropped (one given to us), or the database itself
     * made afresh (or dropped at the end).
     */
    private function empty(string $scratch, bool $given, bool $drop = false): void
    {
        if (! $given) {
            DB::statement("drop database if exists `{$scratch}`");

            if (! $drop) {
                DB::statement("create database `{$scratch}` character set utf8mb4 collate utf8mb4_unicode_ci");
            }

            return;
        }

        DB::statement('set foreign_key_checks = 0');

        foreach (DB::select('select table_name as name from information_schema.tables where table_schema = ?', [$scratch]) as $table) {
            DB::statement("drop table if exists `{$scratch}`.`{$table->name}`");
        }

        DB::statement('set foreign_key_checks = 1');
    }
}
