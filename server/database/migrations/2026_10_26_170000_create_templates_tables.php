<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The catalogue (docs/backend-plan.md, D1): a song, and its versions, each one immutable once
 * published. In M2 these are the official templates; M7 adds people's own (owner, visibility).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            // The template's own id ("amazing-grace"), as in its JSON and the app's project copies.
            $table->string('slug', 80)->unique();
            $table->string('title', 160);
            $table->string('access', 10);
            // The song of the month: free to everyone between these dates (inclusive).
            $table->date('free_from')->nullable();
            $table->date('free_until')->nullable();
            // The 20 to 30 second preview mix on `media` (made in admin from M5).
            $table->string('preview_key', 255)->nullable();
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->timestamps();

            $table->index(['free_from', 'free_until']);
        });

        Schema::create('template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->boolean('reviewed')->default(false);
            $table->json('data');
            $table->char('sha256', 64);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['template_id', 'version']);
        });

        Schema::table('templates', function (Blueprint $table) {
            $table->foreign('current_version_id')->references('id')->on('template_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropForeign(['current_version_id']);
        });
        Schema::dropIfExists('template_versions');
        Schema::dropIfExists('templates');
    }
};
