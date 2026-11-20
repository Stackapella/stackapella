<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI guide voices (docs/ai-voices-build-plan.md, phase 1): the label's name, who it's based on, their consent (the day,
 * the text's version, a payment noted), whether they chose to be named on the label, and whether it's withdrawn. A voice is never deleted: withdrawing it retires
 * everything it sang, and the row stays as the record of what was agreed and when it ended.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_voices')) {
            return;
        }

        Schema::create('ai_voices', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 80);
            $table->string('based_on', 120)->nullable();
            $table->date('consent_date')->nullable();
            $table->string('consent_version', 40)->nullable();
            $table->boolean('payment_noted')->default(false);
            // The singer's choice, as with real voices: named ("AI voice based on <name>'s singing") or not.
            $table->boolean('credited')->default(false);
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_voices');
    }
};
