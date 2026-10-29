<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Launch readiness (contract/api.md, "Support, notices and refunds (L)"): the support inbox and
    // its answers, and the statements of reasons with their appeals. New tables only, so it's safe
    // on the live database.
    public function up(): void
    {
        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('category', 16);
            $table->text('body');
            $table->json('context')->nullable();
            $table->string('status', 12);
            $table->string('ip_hash', 64)->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('support_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // The private link's token, hashed (the link is emailed when the person can't sign in).
            $table->char('token_hash', 64)->unique();
            $table->string('kind', 24);
            $table->string('subject_type', 40)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('rule', 32);
            $table->text('note')->nullable();
            $table->boolean('automatic')->default(false);
            $table->text('appeal_text')->nullable();
            $table->timestamp('appealed_at')->nullable();
            $table->string('decision', 12)->nullable();
            $table->text('decision_note')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['appealed_at', 'decided_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notices');
        Schema::dropIfExists('support_replies');
        Schema::dropIfExists('support_messages');
    }
};
