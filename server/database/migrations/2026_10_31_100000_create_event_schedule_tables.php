<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Event Schedule together (contract/api.md, M9): each account's connection, live lessons booked
    // in ES, the webhook deliveries already handled, and choir groups linked to an ES schedule.
    // New tables and nullable columns only, so it's safe on the live database.
    public function up(): void
    {
        Schema::create('es_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('mode', 16);
            $table->string('base_url');
            $table->string('es_user_id', 64);
            $table->string('es_user_name');
            // Encrypted (the model's casts): an OAuth access token, or a selfhosted API key.
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->string('status', 16);
            $table->string('schedule', 64)->nullable();
            $table->string('schedule_name')->nullable();
            $table->string('schedule_timezone', 64)->nullable();
            $table->string('webhook_id', 64)->nullable();
            $table->text('webhook_secret')->nullable();
            $table->timestamp('webhook_failing_at')->nullable();
            $table->timestamp('failing_since')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'failing_since']);
        });

        Schema::create('lesson_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('engagement_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('es_connection_id')->nullable()->constrained()->nullOnDelete();
            $table->string('appointment_type_id', 64);
            $table->string('es_booking_id', 64)->nullable();
            $table->string('es_event_id', 64)->nullable();
            $table->string('status', 20);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('timezone', 64);
            $table->string('guest_timezone', 64);
            $table->text('notes')->nullable();
            // The coach's policy when it was bought: what the singer agreed to.
            $table->unsignedSmallInteger('cancellation_hours');
            // Until when the singer's own cancellation is refunded: set when bought, moved only by
            // the coach's reschedule (a singer's reschedule can't push it later).
            $table->timestamp('refund_until');
            $table->string('manage_url', 2048)->nullable();
            $table->string('ical_url', 2048)->nullable();
            $table->string('location_url', 2048)->nullable();
            $table->string('changed_by', 8)->nullable();
            // Who asked us to cancel it, kept before ES is asked (its webhook says only "app").
            $table->string('cancel_requested_by', 8)->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->string('problem', 16)->nullable();
            $table->string('problem_note')->nullable();
            $table->timestamp('problem_at')->nullable();
            $table->timestamps();

            $table->index(['es_connection_id', 'es_booking_id']);
            $table->index(['status', 'starts_at']);
            $table->index('problem_at');
        });

        Schema::create('es_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('es_connection_id')->constrained()->cascadeOnDelete();
            $table->string('delivery_id', 64);
            $table->string('type', 40);
            $table->timestamp('created_at')->nullable();

            $table->unique(['es_connection_id', 'delivery_id']);
        });

        Schema::table('coach_offerings', function (Blueprint $table) {
            $table->string('es_appointment_type_id', 64)->nullable();
            $table->string('es_schedule', 64)->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->foreignId('es_connection_id')->nullable()->constrained()->nullOnDelete();
            $table->string('es_schedule', 64)->nullable();
            $table->string('es_schedule_name')->nullable();
        });

        Schema::create('group_event_songs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->string('es_event_id', 64);
            $table->foreignId('group_song_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['group_id', 'es_event_id', 'group_song_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_event_songs');

        Schema::table('groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('es_connection_id');
            $table->dropColumn(['es_schedule', 'es_schedule_name']);
        });

        Schema::table('coach_offerings', function (Blueprint $table) {
            $table->dropColumn(['es_appointment_type_id', 'es_schedule', 'duration_minutes']);
        });

        Schema::dropIfExists('es_deliveries');
        Schema::dropIfExists('lesson_bookings');
        Schema::dropIfExists('es_connections');
    }
};
