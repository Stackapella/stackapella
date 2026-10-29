<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Plus (contract/api.md, "Plus and billing"): what Cashier doesn't keep (trials, the upgrade
    // moment, how Plus ended, disputes), the songs kept from the song of the month, the score
    // history (replacing take_scores), and practice reminders.
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('trial_used_at')->nullable();
            // The subscription that had this account's trial (a second can't).
            $table->string('trial_subscription_id')->nullable();
            // From Stripe's webhooks, so billing.show never waits on Stripe.
            $table->timestamp('plus_renews_at')->nullable();
            $table->timestamp('past_due_until')->nullable();
            // A Plus payment that went through (trust level 3, while no dispute).
            $table->timestamp('plus_paid_at')->nullable();
            $table->timestamp('plus_ended_at')->nullable();
            $table->string('plus_ended_reason', 16)->nullable();
            $table->string('billing_notice', 24)->nullable();
            $table->timestamp('billing_notice_at')->nullable();
            // Which projects stay editable after a downgrade (null: the most recently edited).
            $table->json('editable_project_ids')->nullable();
            $table->boolean('reminder_enabled')->default(false);
            $table->json('reminder_days')->nullable();
            $table->char('reminder_time', 5)->nullable();
            $table->string('reminder_timezone', 64)->nullable();
            $table->date('reminder_sent_on')->nullable();
        });

        // Cards that have had a trial: their fingerprint only (never the number), from Stripe.
        Schema::create('trial_fingerprints', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint', 64)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // The subscription whose trial the card had: a redelivered event finds its own row.
            $table->string('subscription_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('checkout_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('stripe_id')->unique();
            $table->string('moment', 24);
            $table->string('interval', 8);
            $table->string('currency', 3);
            $table->boolean('trial');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('billing_disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('stripe_id')->unique();
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3);
            $table->string('reason', 48);
            $table->string('status', 32);
            // When the penalty (trust 0, Plus ended) was applied, once the account was known.
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
        });

        // Stripe's event ids already handled: a redelivered event changes nothing twice.
        Schema::create('stripe_events', function (Blueprint $table) {
            $table->id();
            $table->string('stripe_id')->unique();
            $table->string('type', 64);
            $table->timestamp('created_at')->useCurrent();
        });

        // A song started while it was the song of the month stays the person's ("finish your song").
        Schema::create('template_unlocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'template_id']);
        });

        Schema::create('scores', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('take_uuid')->nullable();
            $table->string('template_slug', 80)->nullable();
            $table->unsignedInteger('template_version')->nullable();
            $table->string('part_key', 40)->nullable();
            $table->string('lesson', 60)->nullable();
            $table->timestamp('recorded_at');
            $table->json('summary');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'recorded_at']);
        });

        Schema::dropIfExists('take_scores');

        Schema::create('push_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('endpoint');
            $table->char('endpoint_hash', 64)->unique();
            $table->string('p256dh', 255);
            $table->string('auth', 255);
            $table->char('token_hash', 64)->unique();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_devices');
        Schema::dropIfExists('scores');
        Schema::create('take_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('take_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('scores');
            $table->timestamp('created_at')->useCurrent();
        });
        Schema::dropIfExists('template_unlocks');
        Schema::dropIfExists('stripe_events');
        Schema::dropIfExists('billing_disputes');
        Schema::dropIfExists('checkout_sessions');
        Schema::dropIfExists('trial_fingerprints');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['trial_used_at', 'trial_subscription_id', 'plus_renews_at', 'past_due_until', 'plus_paid_at', 'plus_ended_at', 'plus_ended_reason', 'billing_notice', 'billing_notice_at', 'editable_project_ids', 'reminder_enabled', 'reminder_days', 'reminder_time', 'reminder_timezone', 'reminder_sent_on']);
        });
    }
};
