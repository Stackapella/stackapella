<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // The template library (N3; docs/templates-plan.md, "The public library"): a template
    // published to everyone, its licensing declaration (kept with each version), its review and
    // moderation, the facets the library filters on, ratings and use counts, and remixes' parent.
    // New tables and nullable or defaulted columns: safe on the live database.
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('owner_id')->constrained('templates')->nullOnDelete();
            $table->json('declaration')->nullable()->after('library_opt_in');
            $table->timestamp('published_at')->nullable()->after('declaration');
            $table->timestamp('held_at')->nullable()->after('published_at');
            $table->timestamp('hidden_at')->nullable()->after('held_at');
            $table->timestamp('featured_at')->nullable()->after('hidden_at');
            // Read from the current version when it's saved or published, for the filters.
            $table->string('difficulty', 20)->nullable()->after('featured_at');
            $table->json('genres')->nullable()->after('difficulty');
            $table->string('music_key', 8)->nullable()->after('genres');
            $table->unsignedSmallInteger('tempo')->nullable()->after('music_key');
            $table->json('voicings')->nullable()->after('tempo');
            $table->text('search_text')->nullable()->after('voicings');
            $table->unsignedInteger('uses_count')->default(0)->after('search_text');
            $table->unsignedInteger('ratings_count')->default(0)->after('uses_count');
            $table->unsignedInteger('ratings_sum')->default(0)->after('ratings_count');
            $table->decimal('rating_score', 6, 4)->default(0)->after('ratings_sum');

            $table->index(['visibility', 'published_at']);
            $table->index(['visibility', 'uses_count']);
            $table->index(['visibility', 'rating_score']);
            $table->index('featured_at');
        });

        Schema::table('template_versions', function (Blueprint $table) {
            $table->json('declaration')->nullable()->after('reviewed');
        });

        Schema::create('template_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('stars');
            $table->timestamps();

            $table->unique(['template_id', 'user_id']);
        });

        // Each part's range (MIDI), for "fits my voice".
        Schema::create('template_part_ranges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained()->cascadeOnDelete();
            $table->string('part_key', 40);
            $table->unsignedTinyInteger('low');
            $table->unsignedTinyInteger('high');

            $table->index(['template_id', 'low', 'high']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_part_ranges');
        Schema::dropIfExists('template_ratings');

        Schema::table('template_versions', function (Blueprint $table) {
            $table->dropColumn('declaration');
        });

        Schema::table('templates', function (Blueprint $table) {
            $table->dropIndex(['visibility', 'published_at']);
            $table->dropIndex(['visibility', 'uses_count']);
            $table->dropIndex(['visibility', 'rating_score']);
            $table->dropIndex(['featured_at']);
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn(['declaration', 'published_at', 'held_at', 'hidden_at', 'featured_at', 'difficulty', 'genres', 'music_key', 'tempo', 'voicings', 'search_text', 'uses_count', 'ratings_count', 'ratings_sum', 'rating_score']);
        });
    }
};
