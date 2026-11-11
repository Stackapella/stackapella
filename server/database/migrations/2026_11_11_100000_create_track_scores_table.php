<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The score's files a director sends with a learning tracks request ("Send us your song",
 * docs/choir-tracks-plan.md, Hillel 2026-11-11): a PDF, MusicXML or photos of the pages, private.
 * Sent before the request (presigned, as takes are), then attached to it; one never attached is
 * deleted after a day, and every one goes with the account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('track_scores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('support_message_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 200);
            $table->string('kind', 12);
            $table->string('content_type', 60);
            $table->unsignedInteger('bytes');
            $table->char('sha256', 64);
            $table->string('status', 12);
            $table->string('upload_key');
            $table->string('file_key')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('track_scores');
    }
};
