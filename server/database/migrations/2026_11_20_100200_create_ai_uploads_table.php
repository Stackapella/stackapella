<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An AI guide's master on its way from the batch renderer (the worker API, contract/api.md "The AI voices worker"):
 * the declared file, the renderer's checks and settings (for Admin), its status, and the guide it became. The master is
 * kept on the private bucket, as a beta take's is, and deleted with the voice.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_uploads')) {
            return;
        }

        Schema::create('ai_uploads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_version_id')->constrained()->cascadeOnDelete();
            $table->string('part_key', 40);
            $table->foreignId('ai_voice_id')->constrained('ai_voices')->restrictOnDelete();
            $table->string('status', 16)->default('uploading');
            $table->string('failure', 40)->nullable();
            $table->string('upload_key', 120);
            $table->string('master_key', 120)->nullable();
            $table->unsignedInteger('sample_rate');
            $table->unsignedInteger('frames');
            $table->unsignedBigInteger('bytes');
            $table->char('sha256', 64);
            $table->json('checks');
            $table->json('renderer');
            $table->foreignId('template_guide_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['template_version_id', 'part_key', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_uploads');
    }
};
