<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The admin's TOTP backup and recovery codes (passkeys live in `passkeys`). The secret is encrypted;
 * recovery codes are stored only as HMACs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_second_factors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('totp_secret')->nullable();
            $table->timestamp('totp_confirmed_at')->nullable();
            $table->unsignedBigInteger('totp_last_timestep')->nullable();
            $table->json('recovery_codes')->nullable();
            $table->unsignedInteger('failed_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_second_factors');
    }
};
