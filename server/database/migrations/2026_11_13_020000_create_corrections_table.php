<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Readers' corrections and suggestions (docs/corrections-plan.md; Hillel, 2026-11-12): what they wrote and on which page,
 * and the team's decision. Nothing about the reader: no account, email or address.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('corrections', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 16);
            $table->string('state', 16)->default('new');
            $table->text('text');
            $table->string('source', 2048)->nullable();
            $table->string('page_type', 16);
            $table->string('page_path', 255);
            $table->string('page_title', 200);
            $table->string('lang', 8);
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['state', 'id']);
            $table->index(['kind', 'state', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corrections');
    }
};
