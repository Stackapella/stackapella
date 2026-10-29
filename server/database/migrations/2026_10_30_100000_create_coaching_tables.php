<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Coaching, without the directory (contract/api.md, M8): applications, coach profiles with
    // their Stripe Express accounts, what they sell, engagements and the review workspace,
    // messages, ratings, exercise packs, and Send to my teacher. New tables and one nullable
    // column, so it's safe on the live database.
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // An approved, active coach: Plus comes free while it's set (F1).
            $table->timestamp('coach_active_at')->nullable();
        });

        Schema::create('coach_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('answers');
            $table->json('sample_review');
            $table->unsignedInteger('agreement_version');
            $table->string('status', 16);
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('coach_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 16);
            $table->text('bio');
            $table->json('voice_types');
            $table->json('styles');
            $table->json('languages');
            $table->string('timezone', 64);
            $table->unsignedSmallInteger('cancellation_hours')->default(24);
            $table->string('stripe_account_id')->nullable()->unique();
            $table->boolean('charges_enabled')->default(false);
            $table->boolean('payouts_enabled')->default(false);
            $table->boolean('details_submitted')->default(false);
            $table->decimal('rating_average', 3, 2)->nullable();
            $table->unsignedInteger('rating_count')->default(0);
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('coach_offerings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('title', 80);
            $table->text('description');
            $table->unsignedInteger('price_cents');
            $table->char('currency', 3);
            $table->unsignedTinyInteger('turnaround_days');
            $table->unsignedInteger('max_seconds')->nullable();
            $table->unsignedTinyInteger('reviews_per_month')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['coach_id', 'archived_at']);
        });

        Schema::create('exercise_packs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('coach_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 80);
            $table->text('description');
            $table->unsignedInteger('price_cents');
            $table->char('currency', 3);
            $table->json('exercises');
            // What Hillel approved: what buyers get, while a change waits for review.
            $table->string('approved_title', 80)->nullable();
            $table->json('approved_exercises')->nullable();
            $table->string('status', 16);
            $table->text('note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'updated_at']);
        });

        Schema::create('engagements', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 16);
            // Kept when either side deletes their account (the money's record stays, anonymous).
            $table->foreignId('singer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('coach_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('offering_id')->nullable()->constrained('coach_offerings')->nullOnDelete();
            $table->foreignId('pack_id')->nullable()->constrained('exercise_packs')->nullOnDelete();
            $table->string('status', 16);
            $table->unsignedInteger('price_cents')->default(0);
            $table->char('currency', 3)->nullable();
            $table->unsignedInteger('fee_cents')->default(0);
            $table->unsignedInteger('refunded_cents')->default(0);
            // A package: what's been refunded of the month paid last (each month is its own charge).
            $table->unsignedInteger('period_refunded_cents')->default(0);
            $table->timestamp('disputed_at')->nullable();
            $table->unsignedTinyInteger('turnaround_days')->nullable();
            $table->unsignedTinyInteger('reviews_per_month')->nullable();
            $table->unsignedTinyInteger('reviews_left')->nullable();
            $table->string('stripe_checkout_id')->nullable()->unique();
            $table->string('stripe_payment_intent')->nullable();
            $table->string('stripe_charge')->nullable();
            $table->string('stripe_transfer')->nullable();
            $table->string('stripe_subscription')->nullable()->unique();
            $table->timestamp('accept_by')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('period_ends_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('decline_reason', 300)->nullable();
            $table->timestamps();

            $table->index(['singer_id', 'id']);
            $table->index(['coach_id', 'id']);
            $table->index(['status', 'accept_by']);
            $table->index(['status', 'due_at']);
        });

        Schema::create('review_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('engagement_id')->constrained()->cascadeOnDelete();
            $table->string('status', 16);
            // The server's own copy of the take, so deleting it later doesn't touch the review.
            $table->string('take_key');
            $table->unsignedInteger('sample_rate');
            $table->unsignedInteger('frames');
            $table->string('template_slug', 80)->nullable();
            $table->unsignedInteger('template_version')->nullable();
            $table->string('part_key', 40)->nullable();
            $table->string('pitch_track_key')->nullable();
            $table->json('scores')->nullable();
            $table->text('question');
            $table->json('draft')->nullable();
            $table->json('delivered')->nullable();
            // A package's or teacher's review: when the coach means to have it back (no refund).
            $table->timestamp('due_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->text('follow_up_question')->nullable();
            $table->timestamp('follow_up_at')->nullable();
            $table->text('follow_up_answer')->nullable();
            $table->uuid('follow_up_media')->nullable();
            $table->timestamp('follow_up_answered_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'delivered_at']);
        });

        Schema::create('review_media', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('review_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('status', 16);
            $table->string('failure', 24)->nullable();
            $table->unsignedInteger('sample_rate');
            $table->unsignedInteger('frames');
            $table->unsignedBigInteger('upload_bytes');
            $table->char('sha256', 64);
            $table->string('upload_key');
            $table->string('file_key')->nullable();
            $table->timestamps();
        });

        Schema::create('coach_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('engagement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('body')->nullable();
            $table->foreignId('media_id')->nullable()->constrained('review_media')->nullOnDelete();
            $table->string('warning', 16)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['engagement_id', 'id']);
        });

        Schema::create('coach_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('engagement_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('singer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('coach_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('stars');
            $table->string('text', 500)->nullable();
            $table->string('reply', 500)->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();

            $table->index(['coach_id', 'removed_at', 'id']);
        });

        Schema::create('pack_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pack_id')->constrained('exercise_packs')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('engagement_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['pack_id', 'user_id']);
        });

        Schema::create('teacher_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('singer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('engagement_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 80);
            $table->char('token_hash', 64)->unique();
            $table->string('status', 16);
            $table->timestamps();
        });

        Schema::create('engagement_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('engagement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('text');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['resolved_at', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['engagement_reports', 'teacher_links', 'pack_purchases', 'coach_ratings', 'coach_messages', 'review_media', 'review_items', 'engagements', 'exercise_packs', 'coach_offerings', 'coach_profiles', 'coach_applications'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('coach_active_at'));
    }
};
