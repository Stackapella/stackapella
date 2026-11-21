<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Choir seats (docs/choir-seats-plan.md; Hillel, 2026-11-18): which members of a choir group hold one of the seats its
 * leader bought. A new table only, so nothing existing is touched; filled in join order by App\Support\Groups\ChoirSeats.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('group_seats')) {
            return;
        }

        Schema::create('group_seats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamps();

            $table->unique(['group_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_seats');
    }
};
