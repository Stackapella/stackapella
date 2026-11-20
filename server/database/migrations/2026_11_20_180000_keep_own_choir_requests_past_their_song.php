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

        if ($column === null || ($column['nullable'] ?? false) === true) {
            return;
        }

        Schema::table('own_choir_requests', function (Blueprint $table): void {
            $table->dropForeign(['template_version_id']);
        });
        Schema::table('own_choir_requests', function (Blueprint $table): void {
            $table->unsignedBigInteger('template_version_id')->nullable()->change();
            $table->foreign('template_version_id')->references('id')->on('template_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Left nullable: rows whose song is gone have no version to restore.
    }
};
