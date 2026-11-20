<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An own-choir song's row outlives its template (the security review): a singer's own song purged from the bin took
 * its rows with it by cascade, orphaning their private files and handing back the quota. Now its songs are purged first
 * (PurgeBin) and the emptied rows stay, the version set to null, still counting. Safe to run again.
 */
return new class extends Migration
{
    public function up(): void
    {
        $column = collect(Schema::getColumns('own_choir_requests'))->firstWhere('name', 'template_version_id');

        if ($column === null) {
            return;
        }

        // Done already: nullable, with its key set to null on delete.
        if (($column['nullable'] ?? false) === true && (self::foreignKey()['on_delete'] ?? null) === 'set null') {
            return;
        }

        // Nullable but its key missing (a run stopped after the change): only the key is left to add.
        if (($column['nullable'] ?? false) === true && self::foreignKey() === null) {
            Schema::table('own_choir_requests', function (Blueprint $table): void {
                $table->foreign('template_version_id')->references('id')->on('template_versions')->nullOnDelete();
            });

            return;
        }

        // Each step guarded on its own, so a run stopped between them is finished by the next one.
        if (self::foreignKey() !== null) {
            Schema::table('own_choir_requests', function (Blueprint $table): void {
                $table->dropForeign(['template_version_id']);
            });
        }

        Schema::table('own_choir_requests', function (Blueprint $table): void {
            $table->unsignedBigInteger('template_version_id')->nullable()->change();
        });
        Schema::table('own_choir_requests', function (Blueprint $table): void {
            $table->foreign('template_version_id')->references('id')->on('template_versions')->nullOnDelete();
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function foreignKey(): ?array
    {
        $key = collect(Schema::getForeignKeys('own_choir_requests'))->first(static fn (array $key): bool => $key['columns'] === ['template_version_id']);

        return is_array($key) ? $key : null;
    }

    public function down(): void
    {
        // Left nullable: rows whose song is gone have no version to restore.
    }
};
