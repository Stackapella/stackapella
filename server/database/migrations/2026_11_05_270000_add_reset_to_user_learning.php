<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Start the course over" (account.learning.reset): an epoch the merge respects (a device's older
 * progress is refused, never merged back in), and a snapshot to undo it for 30 days.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_learning', function (Blueprint $table): void {
            $table->unsignedInteger('epoch')->default(0)->after('version');
            $table->timestamp('reset_at')->nullable()->after('epoch');
            $table->json('reset_snapshot')->nullable()->after('reset_at');
            $table->timestamp('undo_until')->nullable()->after('reset_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('user_learning', function (Blueprint $table): void {
            $table->dropColumn(['epoch', 'reset_at', 'reset_snapshot', 'undo_until']);
        });
    }
};
