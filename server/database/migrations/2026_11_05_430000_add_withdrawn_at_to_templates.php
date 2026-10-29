<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An official song withdrawn from the library (its file left the courses repo): out of the
 * catalogue, its page and the sitemap, but still served to the projects already made from it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table): void {
            $table->timestamp('withdrawn_at')->nullable()->after('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table): void {
            $table->dropColumn('withdrawn_at');
        });
    }
};
