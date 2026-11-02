<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hillel's calls on the school offer (2026-11-01): Admin approves each teacher before their codes
 * work, and a teacher has 300 seats over all their codes until Admin allows more.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_teachers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('seat_allowance')->default(300);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_teachers');
    }
};
