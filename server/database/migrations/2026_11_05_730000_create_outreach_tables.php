<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outreach (docs/outreach-plan.md; Hillel, 2026-11-02): organizations Hillel writes to one at a
 * time, each email he sends, the replies, and the addresses never to write to again. About
 * organizations and their published role addresses, never our users.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outreach_targets', function (Blueprint $table): void {
            $table->id();
            // The organization's own ?ref= tag (o-...), which the anonymous counts group by.
            $table->string('ref', 16)->unique();
            $table->string('name', 160);
            $table->string('group', 40);
            $table->char('country', 2)->nullable();
            $table->string('reach', 80)->nullable();
            $table->text('fit')->nullable();
            $table->string('contact_email', 190)->nullable();
            $table->string('contact_form_url', 500)->nullable();
            // Where the contact was published (the organization's own page).
            $table->string('source_url', 500)->nullable();
            $table->string('angle', 24);
            $table->string('status', 24)->default('to_research');
            $table->text('notes')->nullable();
            $table->string('outcome', 16)->nullable();
            $table->string('outcome_note', 500)->nullable();
            $table->timestamps();
            $table->index(['status', 'group']);
            $table->unique(['name', 'country']);
        });

        Schema::create('outreach_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('outreach_target_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 12);
            $table->string('subject', 200);
            $table->text('body');
            $table->string('status', 12)->default('draft');
            $table->string('to_email', 190)->nullable();
            $table->string('message_id', 190)->nullable()->unique();
            $table->string('sent_via', 8)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->string('bounce_note', 500)->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'sent_at']);
        });

        Schema::create('outreach_replies', function (Blueprint $table): void {
            $table->id();
            // Null when a reply matched no organization (Admin can see it and file it).
            $table->foreignId('outreach_target_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('outreach_message_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_email', 190);
            $table->string('subject', 200)->nullable();
            $table->text('body');
            $table->timestamp('received_at');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        // Never written to again: a "no thanks", an unsubscribe, a complaint, a bounce, or Hillel's say.
        Schema::create('outreach_suppressions', function (Blueprint $table): void {
            $table->id();
            $table->string('email', 190)->unique();
            $table->string('reason', 16);
            $table->foreignId('outreach_target_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outreach_suppressions');
        Schema::dropIfExists('outreach_replies');
        Schema::dropIfExists('outreach_messages');
        Schema::dropIfExists('outreach_targets');
    }
};
