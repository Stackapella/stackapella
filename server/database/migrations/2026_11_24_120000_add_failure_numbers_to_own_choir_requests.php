<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An unusable clip's measures from the worker (worker.choir.jobs.finish `detailNumbers`), numbers only, for Admin >
 * Choir to tune the checks.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('own_choir_requests', 'failure_numbers')) {
            return;
        }

        Schema::table('own_choir_requests', function (Blueprint $table): void {
            $table->json('failure_numbers')->nullable()->after('failure_detail');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('own_choir_requests', 'failure_numbers')) {
            Schema::table('own_choir_requests', function (Blueprint $table): void {
                $table->dropColumn('failure_numbers');
            });
        }
    }
};
