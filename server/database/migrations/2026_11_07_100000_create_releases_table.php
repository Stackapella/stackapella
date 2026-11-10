<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each deployed commit and when the scheduler first ran it (App\Support\Ops\Releases), so the
 * growth data can line a change in the numbers up with the deploy that made it (docs/growth-data.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('releases', function (Blueprint $table): void {
            $table->id();
            $table->string('build', 40)->unique();
            $table->timestamp('first_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('releases');
    }
};
