<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Growth's sources outgrew 16 characters: a free tool's tag (tool-audio-latency-test) and an
 * embed's (embed-<host>, docs/tools-plan.md) failed the whole counts request. 60 characters stay
 * under 255 bytes in utf8mb4, so MySQL widens the column in place, without copying the table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usage_counts', function (Blueprint $table): void {
            $table->string('source', 60)->default('')->change();
        });
    }

    public function down(): void
    {
        Schema::table('usage_counts', function (Blueprint $table): void {
            $table->string('source', 16)->default('')->change();
        });
    }
};
