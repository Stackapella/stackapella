<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A group's join link kept for its director to copy again (groups.show's `link.url`), encrypted;
 * `link_hash` stays what a link is found by.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table): void {
            $table->text('link_token')->nullable()->after('link_hash');
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table): void {
            $table->dropColumn('link_token');
        });
    }
};
