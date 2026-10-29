<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // From the review of N3 and Ideas: an idea's writer is told once that it's complete (in the
    // bell and by email), whatever happens to its voices after; and a template whose idea was
    // hidden or held by reports keeps that mark, so deleting the idea and sharing it again waits
    // for review. Nullable columns: safe on the live database.
    public function up(): void
    {
        Schema::table('ideas', function (Blueprint $table) {
            $table->timestamp('complete_told_at')->nullable()->after('completed_at');
            $table->timestamp('complete_emailed_at')->nullable()->after('complete_told_at');
        });

        Schema::table('templates', function (Blueprint $table) {
            $table->timestamp('idea_review_at')->nullable()->after('featured_at');
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn('idea_review_at');
        });

        Schema::table('ideas', function (Blueprint $table) {
            $table->dropColumn(['complete_told_at', 'complete_emailed_at']);
        });
    }
};
