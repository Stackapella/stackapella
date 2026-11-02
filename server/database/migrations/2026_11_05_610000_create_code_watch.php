<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Code watch (docs/code-watch-plan.md): public repositories holding our marker strings, and other
 * websites calling our API, counted per domain per day. Nothing about any person.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('code_watch_hits', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 8); // marker or domain
            $table->string('key', 190); // owner/repo, or a domain
            $table->string('url', 500)->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('dismissed_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['kind', 'key']);
        });

        Schema::create('code_watch_domain_days', function (Blueprint $table): void {
            $table->date('day');
            $table->string('domain', 190);
            $table->unsignedInteger('count')->default(0);
            $table->primary(['day', 'domain']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('code_watch_domain_days');
        Schema::dropIfExists('code_watch_hits');
    }
};
