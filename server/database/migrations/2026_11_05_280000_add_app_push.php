<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Push to the native apps (APNs and FCM; contract/api.md, "account.devices.push.update"): each
 * app device may hold one push token, the person's push settings, and the pushes waiting to go
 * (one for each new bell item, held through quiet hours, dropped after 12 hours).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_devices', function (Blueprint $table): void {
            $table->string('push_service', 8)->nullable()->after('revoked_at');
            $table->text('push_token')->nullable()->after('push_service');
            $table->char('push_token_hash', 64)->nullable()->unique()->after('push_token');
            $table->string('push_environment', 12)->nullable()->after('push_token_hash');
            $table->timestamp('push_registered_at')->nullable()->after('push_environment');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->json('push_settings')->nullable();
        });

        Schema::create('app_pushes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bell_item_id')->constrained()->cascadeOnDelete();
            $table->timestamp('send_after');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('dropped_at')->nullable();
            $table->string('outcome', 40)->nullable();
            $table->timestamps();

            $table->unique('bell_item_id');
            $table->index(['sent_at', 'dropped_at', 'send_after']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_pushes');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('push_settings');
        });

        Schema::table('app_devices', function (Blueprint $table): void {
            $table->dropUnique(['push_token_hash']);
            $table->dropColumn(['push_service', 'push_token', 'push_token_hash', 'push_environment', 'push_registered_at']);
        });
    }
};
