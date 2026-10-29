<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Launch readiness (contract/api.md, "Counts, the network panel and What's next (L)"): each
    // song's nightly counts for What's next, and the gallery opt-in. A new table and a nullable
    // column, so it's safe on the live database.
    public function up(): void
    {
        Schema::create('template_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('finishers');
            $table->json('also_learned');
            $table->timestamp('computed_at');
            $table->timestamps();
        });

        Schema::table('harmonies', function (Blueprint $table) {
            // "Show it in the gallery when it opens" (N1): counted for the network panel.
            $table->timestamp('gallery_opt_in_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('harmonies', function (Blueprint $table) {
            $table->dropColumn('gallery_opt_in_at');
        });

        Schema::dropIfExists('template_stats');
    }
};
