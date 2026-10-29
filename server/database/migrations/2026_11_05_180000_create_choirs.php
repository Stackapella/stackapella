<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // The virtual choir (N6; docs/more-features-plan.md, 6): one choir per song of ours, its
    // current mix, and the voices in it (each a copy of a singer's take, levelled). New tables:
    // safe on the live database.
    public function up(): void
    {
        Schema::create('choirs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('mix_key', 255)->nullable();
            $table->unsignedInteger('voices_count')->default(0);
            $table->timestamp('mixed_at')->nullable();
            $table->timestamp('changed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('choir_voices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('choir_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('take_id')->nullable()->constrained()->nullOnDelete();
            $table->string('part_key', 40);
            $table->string('status', 12);
            $table->string('credit', 12);
            $table->unsignedTinyInteger('score');
            $table->string('file_key', 255)->nullable();
            $table->unsignedInteger('sample_rate');
            $table->unsignedInteger('frames');
            $table->integer('offset_frames')->default(0);
            $table->decimal('gain_db', 5, 2)->default(0);
            $table->timestamp('rights_confirmed_at');
            $table->timestamp('in_mix_at')->nullable();
            $table->timestamp('hidden_at')->nullable();
            $table->timestamps();

            $table->unique(['choir_id', 'user_id']);
            $table->index(['choir_id', 'status', 'part_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('choir_voices');
        Schema::dropIfExists('choirs');
    }
};
