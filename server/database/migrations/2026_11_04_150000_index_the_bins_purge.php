<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // The hourly bin purge looks takes and projects up by when they were binned, and orphans by
    // what replaces them: indexes for each, so it stays quick as the tables grow. Indexes only.
    public function up(): void
    {
        Schema::table('takes', function (Blueprint $table) {
            $table->index('binned_at');
            $table->index('replaces_take_id');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->index('binned_at');
        });
    }

    public function down(): void
    {
        Schema::table('takes', function (Blueprint $table) {
            $table->dropIndex(['binned_at']);
            $table->dropIndex(['replaces_take_id']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['binned_at']);
        });
    }
};
